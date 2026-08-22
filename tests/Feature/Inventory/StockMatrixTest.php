<?php

declare(strict_types=1);

use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\TenantRoleSeeder;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->seed(TenantRoleSeeder::class);

    $this->keeper = User::factory()->create()->assignRole('Depo');
    $this->main = Warehouse::factory()->create(['name' => 'Merkez', 'is_default' => true]);
    $this->branch = Warehouse::factory()->create(['name' => 'Şube']);
    $this->variant = ProductVariant::factory()->create(['sku' => 'SKU-1']);
});

it('renders a cell per warehouse even where no inventory row exists', function (): void {
    InventoryItem::factory()->create([
        'variant_id' => $this->variant->id,
        'warehouse_id' => $this->main->id,
        'on_hand' => 12,
        'reserved' => 2,
        'safety_stock' => 5,
    ]);

    $this->actingAs($this->keeper)
        ->get(route('stock.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('inventory/stock/index')
            ->has('warehouses', 2)
            ->has('variants.data', 1)
            ->has('variants.data.0.cells', 2)
            ->where('variants.data.0.cells.0.onHand', 12)
            ->where('variants.data.0.cells.0.available', 10)
            ->where('variants.data.0.cells.1.onHand', 0));
});

it('creates the inventory row on first write to a warehouse', function (): void {
    $this->actingAs($this->keeper)
        ->patch(route('stock.update', [$this->variant, $this->branch]), [
            'on_hand' => 30,
            'reason' => 'Açılış sayımı',
        ])
        ->assertRedirect();

    expect(InventoryItem::query()
        ->where('variant_id', $this->variant->id)
        ->where('warehouse_id', $this->branch->id)
        ->value('on_hand'))->toBe(30);
});

it('demands a reason before moving on hand stock', function (): void {
    $this->actingAs($this->keeper)
        ->patch(route('stock.update', [$this->variant, $this->main]), ['on_hand' => 5])
        ->assertSessionHasErrors('reason');

    expect(InventoryItem::query()->count())->toBe(0);
});

it('leaves an audit trail naming the user, the delta and the reason', function (): void {
    InventoryItem::factory()->create([
        'variant_id' => $this->variant->id,
        'warehouse_id' => $this->main->id,
        'on_hand' => 10,
    ]);

    Log::shouldReceive('info')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            return $message === 'inventory.stock_adjusted'
                && $context['on_hand_before'] === 10
                && $context['on_hand_after'] === 4
                && $context['delta'] === -6
                && $context['reason'] === 'Kırık ürün ayrıldı'
                && $context['sku'] === 'SKU-1'
                && $context['user_name'] === $this->keeper->name;
        });

    $this->actingAs($this->keeper)
        ->patch(route('stock.update', [$this->variant, $this->main]), [
            'on_hand' => 4,
            'reason' => 'Kırık ürün ayrıldı',
        ])
        ->assertRedirect();
});

it('rejects any attempt to write the generated available column', function (): void {
    $this->actingAs($this->keeper)
        ->patch(route('stock.update', [$this->variant, $this->main]), [
            'on_hand' => 5,
            'reason' => 'Sayım',
            'available' => 999,
        ])
        ->assertSessionHasErrors('available');

    expect(InventoryItem::query()->count())->toBe(0);
});

it('keeps available derived when reserved changes on its own', function (): void {
    InventoryItem::factory()->create([
        'variant_id' => $this->variant->id,
        'warehouse_id' => $this->main->id,
        'on_hand' => 20,
        'reserved' => 0,
    ]);

    // Rezerve gerekce istemez: sistemsel bir sayidir, elle duzeltme degil.
    $this->actingAs($this->keeper)
        ->patch(route('stock.update', [$this->variant, $this->main]), ['reserved' => 6])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $item = InventoryItem::query()->sole();

    expect($item->on_hand)->toBe(20)
        ->and($item->reserved)->toBe(6)
        ->and($item->available)->toBe(14);
});

it('updates safety stock without touching anything else', function (): void {
    InventoryItem::factory()->create([
        'variant_id' => $this->variant->id,
        'warehouse_id' => $this->main->id,
        'on_hand' => 9,
        'reserved' => 1,
        'safety_stock' => 0,
    ]);

    $this->actingAs($this->keeper)
        ->patch(route('stock.update', [$this->variant, $this->main]), ['safety_stock' => 3])
        ->assertRedirect();

    $item = InventoryItem::query()->sole();

    expect($item->safety_stock)->toBe(3)
        ->and($item->on_hand)->toBe(9)
        ->and($item->available)->toBe(8);
});

it('filters down to variants that broke their safety stock', function (): void {
    $safe = ProductVariant::factory()->create(['sku' => 'SKU-SAFE']);

    InventoryItem::factory()->create([
        'variant_id' => $this->variant->id,
        'warehouse_id' => $this->main->id,
        'on_hand' => 2,
        'safety_stock' => 5,
    ]);
    InventoryItem::factory()->create([
        'variant_id' => $safe->id,
        'warehouse_id' => $this->main->id,
        'on_hand' => 50,
        'safety_stock' => 5,
    ]);

    $this->actingAs($this->keeper)
        ->get(route('stock.index', ['low' => 1]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('variants.data', 1)
            ->where('variants.data.0.sku', 'SKU-1'));
});

it('searches by sku, barcode and product name', function (): void {
    $product = Product::factory()->create(['name' => 'Kablosuz Kulaklık']);
    ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => 'SKU-2']);

    $this->actingAs($this->keeper)
        ->get(route('stock.index', ['search' => 'Kulaklık']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('variants.data', 1)
            ->where('variants.data.0.sku', 'SKU-2'));
});

it('separates reading the matrix from writing to it', function (): void {
    $accountant = User::factory()->create()->assignRole('Muhasebe');

    $this->actingAs($accountant)
        ->get(route('stock.index'))
        ->assertOk();

    $this->actingAs($accountant)
        ->patch(route('stock.update', [$this->variant, $this->main]), [
            'on_hand' => 1,
            'reason' => 'Deneme',
        ])
        ->assertForbidden();
});
