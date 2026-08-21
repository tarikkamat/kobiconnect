<?php

declare(strict_types=1);

use App\Actions\Sync\EnqueueOperation;
use App\Jobs\Sync\DrainChannelOperations;
use App\Marketplaces\Data\Enums\OperationType;
use App\Marketplaces\Data\Enums\SyncState;
use App\Marketplaces\Data\StockData;
use App\Models\ChannelConnection;
use App\Models\ChannelOperation;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();

    $this->connection = ChannelConnection::factory()->create();
});

function enqueueStockOperation(ChannelConnection $connection, int $quantity, int $variantId = 1): ChannelOperation
{
    return app(EnqueueOperation::class)(
        $connection,
        OperationType::StockUpdate,
        ProductVariant::class,
        $variantId,
        new StockData(reference: "variant-{$variantId}", quantity: $quantity, barcode: '8690000000001'),
    );
}

it('records the desired state with a deterministic idempotency key', function (): void {
    $operation = enqueueStockOperation($this->connection, 5);

    $expected = hash('sha256', implode('|', [
        (string) $this->connection->getKey(),
        ProductVariant::class.'#1',
        'stock_update',
        $operation->payload_hash,
    ]));

    expect($operation->status)->toBe(SyncState::Pending)
        ->and($operation->idempotency_key)->toBe($expected)
        ->and($operation->desired_state['quantity'])->toBe(5)
        // The wire payload is built at send time, never stored up front.
        ->and($operation->payload)->toBeNull();

    Queue::assertPushed(DrainChannelOperations::class);
});

it('does not queue the same desired state twice', function (): void {
    enqueueStockOperation($this->connection, 5);
    enqueueStockOperation($this->connection, 5);

    expect(ChannelOperation::query()->count())->toBe(1);
});

it('coalesces a changed desired state into the pending row', function (): void {
    $first = enqueueStockOperation($this->connection, 5);
    $second = enqueueStockOperation($this->connection, 9);

    expect(ChannelOperation::query()->count())->toBe(1)
        ->and($second->getKey())->toBe($first->getKey())
        ->and($second->refresh()->desired_state['quantity'])->toBe(9)
        ->and($second->payload_hash)->not->toBe($first->payload_hash);
});

it('never mutates an operation that has already been sent', function (): void {
    $sent = enqueueStockOperation($this->connection, 5);
    $sent->update(['status' => SyncState::InFlight, 'sent_at' => now(), 'remote_batch_id' => 'batch-1']);

    enqueueStockOperation($this->connection, 9);

    expect(ChannelOperation::query()->count())->toBe(2)
        ->and($sent->refresh()->desired_state['quantity'])->toBe(5);
});

it('keeps operations of different entities apart', function (): void {
    enqueueStockOperation($this->connection, 5, variantId: 1);
    enqueueStockOperation($this->connection, 5, variantId: 2);

    expect(ChannelOperation::query()->count())->toBe(2);
});
