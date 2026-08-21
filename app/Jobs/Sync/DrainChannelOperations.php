<?php

declare(strict_types=1);

namespace App\Jobs\Sync;

use App\Actions\Sync\ApplyBatchResult;
use App\Actions\Sync\BuildMappingContext;
use App\Enums\ConnectionStatus;
use App\Marketplaces\Contracts\MarketplaceDriver;
use App\Marketplaces\Contracts\SupportsInventorySync;
use App\Marketplaces\Contracts\SupportsPriceSync;
use App\Marketplaces\Contracts\SupportsProductSync;
use App\Marketplaces\Data\AttributeValueData;
use App\Marketplaces\Data\Enums\CanonicalListingStatus;
use App\Marketplaces\Data\Enums\OperationType;
use App\Marketplaces\Data\Enums\SyncState;
use App\Marketplaces\Data\MappingContext;
use App\Marketplaces\Data\PriceData;
use App\Marketplaces\Data\ProductData;
use App\Marketplaces\Data\PushResult;
use App\Marketplaces\Data\StockData;
use App\Marketplaces\Data\VariantData;
use App\Marketplaces\Support\Exceptions\UnsupportedCapabilityException;
use App\Models\ChannelConnection;
use App\Models\ChannelOperation;
use App\Support\Sync\ConnectionDriver;
use App\Support\Sync\MarketplaceWindow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DebounceFor;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Drains the pending side of the outbox ledger for one connection and one kind
 * of operation - BACKEND-PLAN 7.2.
 *
 * The debounce is the answer to "the product changed forty times in ten
 * seconds": every enqueue pushes the run ten seconds out, `maxWait` stops that
 * from starving the send forever, and only the last dispatch survives to do
 * the work. Coalescing itself happened when the rows were written, so this job
 * always finds one pending row per entity.
 *
 * Rows are claimed with `FOR UPDATE SKIP LOCKED` (Laravel has no skipLocked();
 * the string goes to lock() verbatim), flipped to in_flight inside that same
 * transaction, and only then handed to the driver. A row stays open until its
 * item level result has been read back - HTTP 200 is never a success here.
 */
#[DebounceFor(debounceFor: 10, maxWait: 60)]
final class DrainChannelOperations implements ShouldQueue
{
    use Queueable;

    /**
     * Rows handed to the driver in one run. Trendyol takes up to 1000 items per
     * batch; a smaller claim keeps a failure cheap and the ledger responsive.
     */
    private const CHUNK = 100;

    public int $tries = 3;

    public function __construct(
        public readonly int $connectionId,
        public readonly OperationType $operation,
    ) {
        $this->onQueue($this->queueFor());
    }

    /**
     * Only the newest dispatch for this connection and operation runs.
     */
    public function debounceId(): string
    {
        return $this->connectionId.':'.$this->operation->value;
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("connection:{$this->connectionId}:{$this->operation->value}"))
                ->releaseAfter(10)
                ->expireAfter(300),
        ];
    }

    public function handle(
        ConnectionDriver $drivers,
        MarketplaceWindow $window,
        BuildMappingContext $context,
        ApplyBatchResult $apply,
    ): void {
        $connection = ChannelConnection::query()->find($this->connectionId);

        if ($connection === null || $connection->status !== ConnectionStatus::Active) {
            return;
        }

        $driver = $drivers->for($connection);
        $this->operation->capability()->ensureSupported($driver);

        $operations = $this->claim($connection, $window);

        if ($operations->isEmpty()) {
            return;
        }

        try {
            $result = $this->push($driver, $operations, $context($connection, $this->operation));
        } catch (Throwable $exception) {
            $this->returnToPending($operations, $exception);

            throw $exception;
        }

        if (! $result->accepted) {
            $this->reject($operations, $result->failureReason);

            return;
        }

        foreach ($operations as $operation) {
            $window->remember($operation, $connection->marketplace);
        }

        if ($result->remoteBatchId !== null) {
            ChannelOperation::query()
                ->whereKey($operations->modelKeys())
                ->update(['remote_batch_id' => $result->remoteBatchId, 'updated_at' => now()]);
        }

        if (! $result->isPending()) {
            $apply($operations, $result);

            return;
        }

        if ($result->remoteBatchId === null) {
            // Nothing to poll: a driver that returns neither a batch id nor item
            // results is synchronous, and acceptance is all the truth there is.
            ChannelOperation::query()->whereKey($operations->modelKeys())->update([
                'status' => SyncState::Completed->value,
                'completed_at' => now(),
                'remote_result' => (string) json_encode(['accepted' => true]),
                'updated_at' => now(),
            ]);

            return;
        }

        // Trendyol's own agent polls after three seconds (TRENDYOL.md 6.6).
        PollBatchResult::dispatch($this->connectionId, $result->remoteBatchId, $this->operation)
            ->delay(3);
    }

    /**
     * @return Collection<int, ChannelOperation>
     */
    private function claim(ChannelConnection $connection, MarketplaceWindow $window): Collection
    {
        return DB::transaction(function () use ($connection, $window): Collection {
            /** @var Collection<int, ChannelOperation> $claimed */
            $claimed = ChannelOperation::query()
                ->where('connection_id', $connection->getKey())
                ->where('operation', $this->operation->value)
                ->where('status', SyncState::Pending)
                ->where('scheduled_at', '<=', now())
                ->orderBy('scheduled_at')
                ->orderBy('id')
                ->limit(self::CHUNK)
                // Laravel has no skipLocked(); lock() passes the string through.
                ->lock('for update skip locked')
                ->get();

            [$suppressed, $sendable] = $claimed->partition(
                static fn (ChannelOperation $operation): bool => $window->suppresses($operation, $connection->marketplace),
            );

            if ($suppressed->isNotEmpty()) {
                // Dedup layer 4: the marketplace would silently drop these, so
                // sending them would only teach us a lie about what it holds.
                ChannelOperation::query()->whereKey($suppressed->modelKeys())->update([
                    'status' => SyncState::Completed->value,
                    'completed_at' => now(),
                    'remote_result' => (string) json_encode(['skipped' => 'marketplace_dedup_window']),
                    'updated_at' => now(),
                ]);
            }

            if ($sendable->isNotEmpty()) {
                ChannelOperation::query()->whereKey($sendable->modelKeys())->update([
                    'status' => SyncState::InFlight->value,
                    'sent_at' => now(),
                    'attempts' => DB::raw('attempts + 1'),
                    'updated_at' => now(),
                ]);
            }

            /** @var Collection<int, ChannelOperation> $sendable */
            return $sendable;
        });
    }

    /**
     * @param  Collection<int, ChannelOperation>  $operations
     */
    private function push(MarketplaceDriver $driver, Collection $operations, MappingContext $context): PushResult
    {
        if ($this->operation === OperationType::StockUpdate && $driver instanceof SupportsInventorySync) {
            return $driver->pushStock($this->stock($operations), $context);
        }

        if ($this->operation === OperationType::PriceUpdate && $driver instanceof SupportsPriceSync) {
            return $driver->pushPrices($this->prices($operations), $context);
        }

        if ($driver instanceof SupportsProductSync) {
            if ($this->operation === OperationType::ProductCreate) {
                return $driver->createProducts($this->products($operations), $context);
            }

            if ($this->operation === OperationType::ProductUpdate) {
                return $driver->updateProducts($this->products($operations), $context);
            }
        }

        // ponytail: shipment, claim and question operations are single item
        // driver calls and nothing enqueues them yet - there is no order module.
        // Add them here (claim with CHUNK 1) together with that module.
        throw UnsupportedCapabilityException::for($this->operation->capability(), $driver);
    }

    /**
     * @param  Collection<int, ChannelOperation>  $operations
     * @return list<StockData>
     */
    private function stock(Collection $operations): array
    {
        return array_values(array_map(
            static fn (ChannelOperation $operation): StockData => new StockData(...$operation->desired_state),
            $operations->all(),
        ));
    }

    /**
     * @param  Collection<int, ChannelOperation>  $operations
     * @return list<PriceData>
     */
    private function prices(Collection $operations): array
    {
        return array_values(array_map(
            static fn (ChannelOperation $operation): PriceData => new PriceData(...$operation->desired_state),
            $operations->all(),
        ));
    }

    /**
     * The ledger stores the canonical DTO's own shape, so rebuilding it is a
     * named argument spread. Nested lists are the only part that needs hands.
     *
     * @param  Collection<int, ChannelOperation>  $operations
     * @return list<ProductData>
     */
    private function products(Collection $operations): array
    {
        return array_values(array_map(
            function (ChannelOperation $operation): ProductData {
                $state = $operation->desired_state;
                $status = $state['status'] ?? null;

                return new ProductData(...[
                    ...$state,
                    'status' => is_string($status) ? CanonicalListingStatus::from($status) : null,
                    'variants' => array_map($this->variant(...), $state['variants'] ?? []),
                    'attributes' => array_map($this->attributeValue(...), $state['attributes'] ?? []),
                ]);
            },
            $operations->all(),
        ));
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function variant(array $state): VariantData
    {
        return new VariantData(...[
            ...$state,
            'attributes' => array_map($this->attributeValue(...), $state['attributes'] ?? []),
        ]);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function attributeValue(array $state): AttributeValueData
    {
        return new AttributeValueData(...$state);
    }

    /**
     * The send never happened: the rows go back to pending so the next drain
     * recomputes them. Retrying is never a replay of the same bytes.
     *
     * @param  Collection<int, ChannelOperation>  $operations
     */
    private function returnToPending(Collection $operations, Throwable $exception): void
    {
        ChannelOperation::query()->whereKey($operations->modelKeys())->update([
            'status' => SyncState::Pending->value,
            'sent_at' => null,
            'scheduled_at' => now()->addSeconds(30),
            'error' => (string) json_encode([
                'class' => $exception::class,
                'message' => $exception->getMessage(),
            ]),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  Collection<int, ChannelOperation>  $operations
     */
    private function reject(Collection $operations, ?string $reason): void
    {
        ChannelOperation::query()->whereKey($operations->modelKeys())->update([
            'status' => SyncState::Failed->value,
            'completed_at' => now(),
            'error' => (string) json_encode([
                'code' => 'rejected',
                'message' => $reason ?? 'Pazaryeri isteği reddetti.',
            ]),
            'updated_at' => now(),
        ]);
    }

    /**
     * Stock and price are the latency critical pushes, so they get their own
     * supervisor (config/horizon.php).
     */
    private function queueFor(): string
    {
        return match ($this->operation) {
            OperationType::StockUpdate, OperationType::PriceUpdate => 'push-inventory',
            OperationType::ProductCreate, OperationType::ProductUpdate => 'sync-products',
            default => 'default',
        };
    }
}
