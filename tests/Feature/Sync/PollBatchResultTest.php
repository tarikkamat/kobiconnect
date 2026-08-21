<?php

declare(strict_types=1);

use App\Jobs\Sync\PollBatchResult;
use App\Marketplaces\Contracts\MarketplaceDriver;
use App\Marketplaces\Data\Enums\OperationType;
use App\Marketplaces\Data\Enums\SyncState;
use App\Marketplaces\Data\PushResult;
use App\Marketplaces\Support\Capability;
use App\Marketplaces\Support\MarketplaceManager;
use App\Models\ChannelConnection;
use App\Models\ChannelOperation;
use App\Models\ProductVariant;
use App\Support\Sync\MarketplaceWindow;
use App\Support\Sync\ReadsBatchResults;
use Tests\Feature\Sync\Fixtures\FakePushDriver;

beforeEach(function (): void {
    $this->driver = new FakePushDriver;

    $driver = $this->driver;

    app(MarketplaceManager::class)->extend('fake', fn (): FakePushDriver => $driver);

    $this->connection = ChannelConnection::factory()->create(['marketplace' => 'fake']);
});

function inFlight(ChannelConnection $connection, string $reference, string $batchId = 'batch-77'): ChannelOperation
{
    return ChannelOperation::factory()->create([
        'connection_id' => $connection->getKey(),
        'entity_type' => ProductVariant::class,
        'entity_id' => 1,
        'operation' => OperationType::StockUpdate->value,
        'desired_state' => ['reference' => $reference, 'quantity' => 5],
        'idempotency_key' => $reference,
        'status' => SyncState::InFlight,
        'remote_batch_id' => $batchId,
        'sent_at' => now(),
        'attempts' => 1,
    ]);
}

function poll(ChannelConnection $connection, string $batchId = 'batch-77'): void
{
    app()->call([
        new PollBatchResult($connection->getKey(), $batchId, OperationType::StockUpdate),
        'handle',
    ]);
}

it('settles each item on its own result, not on the batch envelope', function (): void {
    $ok = inFlight($this->connection, 'variant-1');
    $bad = inFlight($this->connection, 'variant-2');

    $this->driver->batchResult = PushResult::accepted('batch-77')->withItemResults([
        'variant-1' => ['accepted' => true, 'code' => null, 'message' => null],
        'variant-2' => ['accepted' => false, 'code' => 'PIM-1001', 'message' => 'Barkod bulunamadı'],
    ]);

    poll($this->connection);

    expect($ok->refresh()->status)->toBe(SyncState::Completed)
        ->and($bad->refresh()->status)->toBe(SyncState::Failed)
        ->and($bad->error['message'])->toBe('Barkod bulunamadı')
        ->and($bad->remote_result['code'])->toBe('PIM-1001');
});

it('sends the operation to reconciliation once the result window has closed', function (): void {
    config(['marketplaces.fake.batch_result_ttl' => 14400]);

    $operation = inFlight($this->connection, 'variant-1');
    $operation->update(['sent_at' => now()->subHours(5)]);

    poll($this->connection);

    $operation->refresh();

    // Unknown is not success: only reconciliation can settle this row.
    expect($operation->status)->toBe(SyncState::Failed)
        ->and($operation->error['code'])->toBe('reconciliation_required')
        ->and($operation->error['reason'])->toBe('batch_result_expired');
});

it('leaves the operation open while the batch is still running', function (): void {
    $operation = inFlight($this->connection, 'variant-1');

    $this->driver->batchResult = PushResult::accepted('batch-77');

    poll($this->connection);

    expect($operation->refresh()->status)->toBe(SyncState::InFlight);
});

it('marks an item the marketplace never reported on', function (): void {
    $missing = inFlight($this->connection, 'variant-9');

    $this->driver->batchResult = PushResult::accepted('batch-77')->withItemResults([
        'variant-1' => ['accepted' => true, 'code' => null, 'message' => null],
    ]);

    poll($this->connection);

    expect($missing->refresh()->status)->toBe(SyncState::Failed)
        ->and($missing->error['code'])->toBe('missing_item_result');
});

it('clears the marketplace window when the item was refused', function (): void {
    config(['marketplaces.fake.dedup_window_seconds' => 900]);

    $operation = inFlight($this->connection, 'variant-1');
    $window = app(MarketplaceWindow::class);
    $window->remember($operation, 'fake');

    $this->driver->batchResult = PushResult::accepted('batch-77')->withItemResults([
        'variant-1' => ['accepted' => false, 'code' => 'PIM-1001', 'message' => 'Barkod bulunamadı'],
    ]);

    poll($this->connection);

    // The marketplace is not holding these values, so a corrected retry must
    // not be mistaken for a repeat inside the window.
    expect($window->suppresses($operation, 'fake'))->toBeFalse();
});

it('polls a driver that can read a batch without claiming product sync', function (): void {
    // Trendyol reads stock and price batches from the product batch endpoint
    // but must not claim product_sync to do it (TRENDYOL.md 6.2, Ek A #1).
    $reader = new class implements MarketplaceDriver, ReadsBatchResults
    {
        public function identifier(): string
        {
            return 'reader';
        }

        public function displayName(): string
        {
            return 'Batch reader';
        }

        /** @return list<Capability> */
        public function capabilities(): array
        {
            return Capability::supportedBy($this);
        }

        /** @return list<array{name: string, label: string, type: 'text'|'secret'|'select'|'checkbox', rules: list<string>}> */
        public function credentialFields(): array
        {
            return [];
        }

        public function batchResult(string $remoteBatchId): PushResult
        {
            return PushResult::accepted($remoteBatchId)->withItemResults([
                'variant-1' => ['accepted' => true, 'code' => null, 'message' => null],
            ]);
        }
    };

    app(MarketplaceManager::class)->extend('reader', fn (): MarketplaceDriver => $reader);

    $connection = ChannelConnection::factory()->create(['marketplace' => 'reader']);
    $operation = inFlight($connection, 'variant-1', 'batch-99');

    poll($connection, 'batch-99');

    expect($operation->refresh()->status)->toBe(SyncState::Completed)
        ->and($reader->capabilities())->not->toContain(Capability::ProductSync);
});
