<?php

declare(strict_types=1);

use App\Enums\ConnectionStatus;
use App\Jobs\Sync\DrainChannelOperations;
use App\Jobs\Sync\PollBatchResult;
use App\Marketplaces\Data\Enums\OperationType;
use App\Marketplaces\Data\Enums\SyncState;
use App\Marketplaces\Data\PushResult;
use App\Marketplaces\Data\StockData;
use App\Marketplaces\Support\MarketplaceManager;
use App\Models\ChannelConnection;
use App\Models\ChannelOperation;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Sync\Fixtures\FakePushDriver;

beforeEach(function (): void {
    Queue::fake();

    $this->driver = new FakePushDriver;

    $driver = $this->driver;

    app(MarketplaceManager::class)->extend('fake', fn (): FakePushDriver => $driver);

    $this->connection = ChannelConnection::factory()->create([
        'marketplace' => 'fake',
        'credentials' => ['api_key' => 'k', 'api_secret' => 's'],
    ]);
});

function pendingStock(ChannelConnection $connection, int $quantity, string $reference = 'variant-1'): ChannelOperation
{
    $state = (array) json_decode((string) json_encode(
        new StockData(reference: $reference, quantity: $quantity, barcode: '8690000000001'),
    ), true);

    return ChannelOperation::factory()->create([
        'connection_id' => $connection->getKey(),
        'entity_type' => ProductVariant::class,
        'entity_id' => 1,
        'operation' => OperationType::StockUpdate->value,
        'desired_state' => $state,
        'payload_hash' => hash('sha256', (string) json_encode($state)),
        'idempotency_key' => bin2hex(random_bytes(16)),
        'status' => SyncState::Pending,
        'scheduled_at' => now()->subMinute(),
    ]);
}

function dispatchDrain(ChannelConnection $connection, OperationType $operation): void
{
    // Queue::fake() swallows the dispatch, so the job is run in place.
    app()->call([new DrainChannelOperations($connection->getKey(), $operation), 'handle']);
}

it('sends pending operations and waits for the item result', function (): void {
    $operation = pendingStock($this->connection, 5);

    $this->driver->pushResult = PushResult::accepted('batch-77');

    dispatchDrain($this->connection, OperationType::StockUpdate);

    $operation->refresh();

    // HTTP 200 is acceptance, never success.
    expect($operation->status)->toBe(SyncState::InFlight)
        ->and($operation->remote_batch_id)->toBe('batch-77')
        ->and($operation->attempts)->toBe(1)
        ->and($operation->sent_at)->not->toBeNull()
        ->and($this->driver->stockPushes[0][0])->toBeInstanceOf(StockData::class)
        ->and($this->driver->stockPushes[0][0]->quantity)->toBe(5)
        ->and($this->driver->credentials['api_key'])->toBe('k');

    Queue::assertPushed(PollBatchResult::class);
});

it('closes the operation when item results come back with the push', function (): void {
    $operation = pendingStock($this->connection, 5);

    $this->driver->pushResult = PushResult::accepted('batch-77')->withItemResults([
        'variant-1' => ['accepted' => true, 'code' => null, 'message' => null],
    ]);

    dispatchDrain($this->connection, OperationType::StockUpdate);

    expect($operation->refresh()->status)->toBe(SyncState::Completed);

    Queue::assertNotPushed(PollBatchResult::class);
});

it('drops a push the marketplace would silently swallow', function (): void {
    config(['marketplaces.fake.dedup_window_seconds' => 900]);

    $first = pendingStock($this->connection, 5);
    $this->driver->pushResult = PushResult::accepted('batch-77');

    dispatchDrain($this->connection, OperationType::StockUpdate);

    // Same barcode, same values, inside the window: never sent again.
    $repeat = pendingStock($this->connection, 5, reference: 'variant-1');

    dispatchDrain($this->connection, OperationType::StockUpdate);

    expect($this->driver->stockPushes)->toHaveCount(1)
        ->and($repeat->refresh()->status)->toBe(SyncState::Completed)
        ->and($repeat->remote_result['skipped'])->toBe('marketplace_dedup_window')
        ->and($first->refresh()->status)->toBe(SyncState::InFlight);
});

it('returns operations to pending when the driver throws', function (): void {
    $operation = pendingStock($this->connection, 5);

    $this->driver->failWith = new RuntimeException('connection reset');

    expect(fn () => dispatchDrain($this->connection, OperationType::StockUpdate))
        ->toThrow(RuntimeException::class);

    $operation->refresh();

    expect($operation->status)->toBe(SyncState::Pending)
        ->and($operation->attempts)->toBe(1)
        ->and($operation->sent_at)->toBeNull()
        ->and($operation->error['message'])->toBe('connection reset');
});

it('fails operations the marketplace rejected outright', function (): void {
    $operation = pendingStock($this->connection, 5);

    $this->driver->pushResult = PushResult::rejected('barcode is not registered');

    dispatchDrain($this->connection, OperationType::StockUpdate);

    $operation->refresh();

    expect($operation->status)->toBe(SyncState::Failed)
        ->and($operation->error['message'])->toBe('barcode is not registered');
});

it('leaves an inactive connection alone', function (): void {
    $this->connection->update(['status' => ConnectionStatus::Paused]);

    $operation = pendingStock($this->connection, 5);

    dispatchDrain($this->connection, OperationType::StockUpdate);

    expect($operation->refresh()->status)->toBe(SyncState::Pending)
        ->and($this->driver->stockPushes)->toBe([]);
});
