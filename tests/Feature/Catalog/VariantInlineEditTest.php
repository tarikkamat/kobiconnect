<?php

declare(strict_types=1);

use App\Models\InventoryItem;
use App\Models\Price;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\TenantRoleSeeder;

beforeEach(function (): void {
    $this->seed(TenantRoleSeeder::class);

    $this->variant = ProductVariant::factory()->create();
});

it('creates the inventory row for the default warehouse on first edit', function (): void {
    Warehouse::factory()->create(['is_default' => false]);
    $default = Warehouse::factory()->create(['is_default' => true]);

    $this->actingAs(User::factory()->create()->assignRole('Depo'))
        ->patch(route('variants.stock', $this->variant), ['on_hand' => 42])
        ->assertRedirect();

    expect(InventoryItem::query()
        ->where('variant_id', $this->variant->id)
        ->where('warehouse_id', $default->id)
        ->value('on_hand'))->toBe(42);
});

it('leaves reserved stock alone so available stays derived', function (): void {
    $warehouse = Warehouse::factory()->create(['is_default' => true]);
    InventoryItem::factory()->create([
        'variant_id' => $this->variant->id,
        'warehouse_id' => $warehouse->id,
        'on_hand' => 10,
        'reserved' => 4,
    ]);

    $this->actingAs(User::factory()->create()->assignRole('Depo'))
        ->patch(route('variants.stock', $this->variant), ['on_hand' => 20]);

    $item = InventoryItem::query()->where('variant_id', $this->variant->id)->sole();

    expect($item->on_hand)->toBe(20)
        ->and($item->reserved)->toBe(4)
        ->and($item->available)->toBe(16);
});

it('explains itself instead of failing when no warehouse exists', function (): void {
    $this->actingAs(User::factory()->create()->assignRole('Depo'))
        ->patch(route('variants.stock', $this->variant), ['on_hand' => 5])
        ->assertSessionHasErrors('on_hand');
});

it('upserts the try price row', function (): void {
    Price::factory()->create(['variant_id' => $this->variant->id, 'currency' => 'TRY', 'list_price' => 100]);

    $this->actingAs(User::factory()->create()->assignRole('Yönetici'))
        ->patch(route('variants.price', $this->variant), ['list_price' => 249.5])
        ->assertRedirect();

    expect(Price::query()->where('variant_id', $this->variant->id)->count())->toBe(1)
        ->and((float) Price::query()->where('variant_id', $this->variant->id)->value('list_price'))->toBe(249.5);
});

it('separates the stock permission from the price permission', function (): void {
    Warehouse::factory()->create(['is_default' => true]);
    $warehouseUser = User::factory()->create()->assignRole('Depo');

    $this->actingAs($warehouseUser)
        ->patch(route('variants.stock', $this->variant), ['on_hand' => 3])
        ->assertRedirect();

    $this->actingAs($warehouseUser)
        ->patch(route('variants.price', $this->variant), ['list_price' => 10])
        ->assertForbidden();

    expect(Price::query()->where('variant_id', $this->variant->id)->exists())->toBeFalse();
});

it('rejects a negative stock value', function (): void {
    Warehouse::factory()->create(['is_default' => true]);

    $this->actingAs(User::factory()->create()->assignRole('Depo'))
        ->patch(route('variants.stock', $this->variant), ['on_hand' => -1])
        ->assertSessionHasErrors('on_hand');
});
