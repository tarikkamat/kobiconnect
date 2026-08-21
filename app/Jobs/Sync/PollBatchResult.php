<?php

declare(strict_types=1);

namespace App\Jobs\Sync;

use App\Actions\Sync\ApplyBatchResult;
use App\Marketplaces\Contracts\SupportsProductSync;
use App\Marketplaces\Data\Enums\OperationType;
use App\Marketplaces\Data\Enums\SyncState;
use App\Models\ChannelConnection;
use App\Models\ChannelOperation;
use App\Support\Sync\ConnectionDriver;
use App\Support\Sync\ReadsBatchResults;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

/**
 * Reads the item level result of an accepted batch - TRENDYOL.md 6.
 *
 * Three facts shape this job:
 *
 * - `COMPLETED` does not mean "all succeeded" and inventory batches never
 *   return a top level status at all, so the decision is per item. That belongs
 *   to the driver, which hands back a PushResult; here we only act on it.
 * - The result exists for four hours and then it is gone for good. When the
 *   window closes the operation is not a success and not a plain failure: it is
 *   unknown, and only reconciliation can settle it.
 * - Polling shares the marketplace's read budget, so the tempo backs off hard
 *   instead of hammering every three seconds.
 *
 * There is no in-process waiting: the job releases itself back to Redis with
 * the next delay, so a worker is never parked on a sleep.
 */
final class PollBatchResult implements ShouldQueue
{
    use Queueable;

    /**
     * Trendyol's own agent polls every 3 seconds ten times over; the tail here
     * keeps a cheap eye on the batch until its four hour window is nearly up.
     *
     * @var list<int>
     */
    private const SCHEDULE = [3, 5, 10, 30, 60, 300, 900, 3600, 3600];

    /**
     * How long a batch result survives remotely when nothing says otherwise.
     */
    private const DEFAULT_RESULT_TTL = 14400;

    public int $tries = 10;

    public function __construct(
        public readonly int $connectionId,
        public readonly string $remoteBatchId,
        public readonly OperationType $operation,
    ) {
        $this->onQueue('sync-products');
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new WithoutOverlapping("batch:{$this->connectionId}:{$this->remoteBatchId}")];
    }

    public function handle(ConnectionDriver $drivers, ApplyBatchResult $apply): void
    {
        $operations = $this->operations();

        if ($operations->isEmpty()) {
            return;
        }

        $connection = ChannelConnection::query()->find($this->connectionId);

        if ($connection === null) {
            return;
        }

        if ($this->windowClosed($operations, $connection->marketplace)) {
            $this->reconcile($operations, 'batch_result_expired');

            return;
        }

        $driver = $drivers->for($connection);

        // A stock or price batch is read back from the same endpoint a product
        // batch is (TRENDYOL.md 6.2), but the driver must not have to claim
        // `product_sync` to say so - hence the narrower ReadsBatchResults.
        $result = match (true) {
            $driver instanceof ReadsBatchResults => $driver->batchResult($this->remoteBatchId),
            $driver instanceof SupportsProductSync => $driver->productPushResult($this->remoteBatchId),
            default => null,
        };

        if ($result === null) {
            $this->reconcile($operations, 'no_batch_result_reader');

            return;
        }

        if (! $result->accepted) {
            $this->reconcile($operations, $result->failureReason ?? 'batch_result_unavailable');

            return;
        }

        if ($result->isPending()) {
            $this->release($this->nextDelay());

            return;
        }

        $apply($operations, $result, final: true);
    }

    /**
     * Attempts ran out with the batch still running: unknown, not failed.
     */
    public function failed(?Throwable $exception): void
    {
        $operations = $this->operations();

        if ($operations->isNotEmpty()) {
            $this->reconcile($operations, 'batch_result_not_read');
        }
    }

    /**
     * @return Collection<int, ChannelOperation>
     */
    private function operations(): Collection
    {
        /** @var Collection<int, ChannelOperation> $operations */
        $operations = ChannelOperation::query()
            ->where('connection_id', $this->connectionId)
            ->where('remote_batch_id', $this->remoteBatchId)
            ->where('status', SyncState::InFlight)
            ->get();

        return $operations;
    }

    /**
     * @param  Collection<int, ChannelOperation>  $operations
     */
    private function windowClosed(Collection $operations, string $marketplace): bool
    {
        $sentAt = $operations->min('sent_at');

        if (! $sentAt instanceof CarbonInterface) {
            return false;
        }

        $ttl = config("marketplaces.{$marketplace}.batch_result_ttl", self::DEFAULT_RESULT_TTL);

        return now()->greaterThan($sentAt->copy()->addSeconds(is_numeric($ttl) ? (int) $ttl : self::DEFAULT_RESULT_TTL));
    }

    /**
     * The outcome is unknown and the marketplace will not tell us any more.
     *
     * ponytail: the full reconciliation diff is not written in this phase. This
     * is its inbox - a failed row carrying `reconciliation_required` is exactly
     * the set a diff has to settle against the marketplace's ground truth
     * (getProductBase / filterApprovedProductsInventoryAndPrice).
     *
     * @param  Collection<int, ChannelOperation>  $operations
     */
    private function reconcile(Collection $operations, string $reason): void
    {
        ChannelOperation::query()->whereKey($operations->modelKeys())->update([
            'status' => SyncState::Failed->value,
            'completed_at' => now(),
            'error' => (string) json_encode([
                'code' => 'reconciliation_required',
                'reason' => $reason,
                'message' => 'Pazaryeri sonucu okunamadı; mutabakat gerekiyor.',
            ]),
            'updated_at' => now(),
        ]);
    }

    private function nextDelay(): int
    {
        return self::SCHEDULE[$this->attempts() - 1] ?? self::SCHEDULE[count(self::SCHEDULE) - 1];
    }
}
