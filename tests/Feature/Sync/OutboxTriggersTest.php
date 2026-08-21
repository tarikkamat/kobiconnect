<?php

declare(strict_types=1);

use App\Enums\AllocationType;
use App\Enums\ConnectionStatus;
use App\Enums\MarkupType;
use App\Enums\RuleScope;
use App\Jobs\Sync\DrainChannelOperations;
use App\Marketplaces\Data\Enums\OperationType;
use App\Marketplaces\Data\Enums\SyncState;
use App\Models\Category;
use App\Models\ChannelConnection;
use App\Models\ChannelListing;
use App\Models\ChannelOperation;
use App\Models\ChannelPriceRule;
use App\Models\ChannelStockRule;
use App\Models\InventoryItem;
use App\Models\Price;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Observers\ChannelListingObserver;
use App\Observers\InventoryItemObserver;
use App\Observers\PriceObserver;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->grantActiveLicense();

    Queue::fake();

    // app/Providers is not this wave's file; the three registrations are
    // reported for AppServiceProvider::boot() and wired by hand here.
    InventoryItem::observe(InventoryItemObserver::class);
    Price::observe(PriceObserver::class);
    ChannelListing::observe(ChannelListingObserver::class);

    $this->connection = ChannelConnection::factory()->create();
});

function listedVariant(
    ChannelConnection $connection,
    int $onHand = 100,
    string $listPrice = '100.00',
    ?string $salePrice = null,
    ?Product $product = null,
): ProductVariant {
    $variant = ProductVariant::factory()->create([
        'barcode' => '868 0000000 001',
        'product_id' => $product?->getKey() ?? Product::factory(),
    ]);

    InventoryItem::factory()->create(['variant_id' => $variant->getKey(), 'on_hand' => $onHand]);
    Price::factory()->create([
        'variant_id' => $variant->getKey(),
        'list_price' => $listPrice,
        'sale_price' => $salePrice,
    ]);

    ChannelListing::factory()->create([
        'connection_id' => $connection->getKey(),
        'variant_id' => $variant->getKey(),
    ]);

    return $variant;
}

/**
 * @return array<string, mixed>
 */
function desiredState(ChannelConnection $connection, OperationType $operation): array
{
    $row = ChannelOperation::query()
        ->where('connection_id', $connection->getKey())
        ->where('operation', $operation->value)
        ->where('status', SyncState::Pending)
        ->sole();

    return $row->desired_state;
}

it('writes a stock and a price intent the moment a variant becomes listed', function (): void {
    listedVariant($this->connection);

    // The barcode is normalised once, here, so the ledger reference, the wire
    // barcode and the batch result echo are the same string (TRENDYOL.md 9.2).
    expect(desiredState($this->connection, OperationType::StockUpdate))
        ->toMatchArray(['reference' => '8680000000001', 'quantity' => 100, 'barcode' => '8680000000001'])
        ->and(desiredState($this->connection, OperationType::PriceUpdate))
        ->toMatchArray(['listPrice' => '100.00', 'salePrice' => '100.00']);

    Queue::assertPushed(DrainChannelOperations::class);
});

it('records the new quantity when stock moves', function (): void {
    $variant = listedVariant($this->connection);

    InventoryItem::query()->where('variant_id', $variant->getKey())->first()?->update(['on_hand' => 42]);

    // The pending row coalesces: one row carrying the last state, not two.
    expect(desiredState($this->connection, OperationType::StockUpdate)['quantity'])->toBe(42);
});

it('reads the reservation, not just what is on the shelf', function (): void {
    $variant = listedVariant($this->connection, onHand: 100);

    InventoryItem::query()->where('variant_id', $variant->getKey())->first()?->update([
        'reserved' => 30,
        'safety_stock' => 10,
    ]);

    // available is a generated column (on_hand - reserved) and safety stock
    // never leaves the shelf: 100 - 30 - 10.
    expect(desiredState($this->connection, OperationType::StockUpdate)['quantity'])->toBe(60);
});

it('never queues a variant that is not listed anywhere', function (): void {
    $variant = ProductVariant::factory()->create(['barcode' => '8690000000002']);
    InventoryItem::factory()->create(['variant_id' => $variant->getKey(), 'on_hand' => 10]);
    Price::factory()->create(['variant_id' => $variant->getKey()]);

    expect(ChannelOperation::query()->count())->toBe(0);
});

it('leaves a paused connection alone', function (): void {
    $this->connection->update(['status' => ConnectionStatus::Paused]);

    listedVariant($this->connection);

    expect(ChannelOperation::query()->count())->toBe(0);
});

it('takes the buffer off before the channel takes its percentage', function (): void {
    ChannelStockRule::factory()->create([
        'connection_id' => $this->connection->getKey(),
        'allocation_type' => AllocationType::Percent,
        'allocation_value' => 50,
        'buffer' => 20,
    ]);

    listedVariant($this->connection, onHand: 100);

    // (100 - 20) * 50%
    expect(desiredState($this->connection, OperationType::StockUpdate)['quantity'])->toBe(40);
});

it('never promises more than the pool under a fixed cap', function (): void {
    ChannelStockRule::factory()->create([
        'connection_id' => $this->connection->getKey(),
        'allocation_type' => AllocationType::Fixed,
        'allocation_value' => 500,
        'buffer' => 0,
    ]);

    listedVariant($this->connection, onHand: 7);

    expect(desiredState($this->connection, OperationType::StockUpdate)['quantity'])->toBe(7);
});

it('gives the whole remainder after the buffer', function (): void {
    ChannelStockRule::factory()->create([
        'connection_id' => $this->connection->getKey(),
        'allocation_type' => AllocationType::Remaining,
        'allocation_value' => null,
        'buffer' => 90,
    ]);

    listedVariant($this->connection, onHand: 100);

    expect(desiredState($this->connection, OperationType::StockUpdate)['quantity'])->toBe(10);
});

it('prefers the rule scoped to the variant over the connection default', function (): void {
    $connection = $this->connection;

    ChannelStockRule::factory()->create([
        'connection_id' => $connection->getKey(),
        'scope_type' => RuleScope::Connection,
        'allocation_type' => AllocationType::Percent,
        'allocation_value' => 10,
    ]);

    $variant = ProductVariant::factory()->create(['barcode' => '8690000000003']);

    ChannelStockRule::factory()->create([
        'connection_id' => $connection->getKey(),
        'scope_type' => RuleScope::Variant,
        'scope_id' => $variant->getKey(),
        'allocation_type' => AllocationType::Percent,
        'allocation_value' => 90,
    ]);

    InventoryItem::factory()->create(['variant_id' => $variant->getKey(), 'on_hand' => 100]);
    ChannelListing::factory()->create([
        'connection_id' => $connection->getKey(),
        'variant_id' => $variant->getKey(),
    ]);

    expect(desiredState($connection, OperationType::StockUpdate)['quantity'])->toBe(90);
});

it('prefers a category rule over the connection default', function (): void {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->getKey()]);

    ChannelPriceRule::factory()->create([
        'connection_id' => $this->connection->getKey(),
        'scope_type' => RuleScope::Connection,
        'markup_type' => MarkupType::Percent,
        'markup_value' => 10,
    ]);

    ChannelPriceRule::factory()->create([
        'connection_id' => $this->connection->getKey(),
        'scope_type' => RuleScope::Category,
        'scope_id' => $category->getKey(),
        'markup_type' => MarkupType::Fixed,
        'markup_value' => 25,
    ]);

    listedVariant($this->connection, listPrice: '100.00', product: $product);

    expect(desiredState($this->connection, OperationType::PriceUpdate)['salePrice'])->toBe('125.00');
});

it('applies the markup and then the rounding step', function (): void {
    ChannelPriceRule::factory()->create([
        'connection_id' => $this->connection->getKey(),
        'markup_type' => MarkupType::Percent,
        'markup_value' => 15,
        'round_to' => '0.50',
    ]);

    listedVariant($this->connection, listPrice: '99.90', salePrice: '89.90');

    // 89.90 * 1.15 = 103.385 -> 103.50 ; 99.90 * 1.15 = 114.885 -> 115.00
    expect(desiredState($this->connection, OperationType::PriceUpdate))
        ->toMatchArray(['salePrice' => '103.50', 'listPrice' => '115.00']);
});

it('never lets its own markup break the list price rule', function (): void {
    ChannelPriceRule::factory()->create([
        'connection_id' => $this->connection->getKey(),
        'markup_type' => MarkupType::Percent,
        'markup_value' => 0,
    ]);

    // A catalogue row where the sale price was set above the list price: the
    // marketplace rejects listPrice < salePrice outright (TRENDYOL.md 9.5).
    listedVariant($this->connection, listPrice: '80.00', salePrice: '120.00');

    $state = desiredState($this->connection, OperationType::PriceUpdate);

    expect($state['salePrice'])->toBe('120.00')->and($state['listPrice'])->toBe('120.00');
});
