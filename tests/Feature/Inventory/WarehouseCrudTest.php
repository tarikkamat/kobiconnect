<?php

declare(strict_types=1);

use App\Models\InventoryItem;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\TenantRoleSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->seed(TenantRoleSeeder::class);

    $this->keeper = User::factory()->create()->assignRole('Depo');
});

it('makes the first warehouse default whatever the form says', function (): void {
    $this->actingAs($this->keeper)
        ->post(route('warehouses.store'), ['name' => 'Merkez', 'code' => 'WH-01'])
        ->assertRedirect();

    expect(Warehouse::query()->sole()->is_default)->toBeTrue();
});

it('moves the default flag instead of allowing two defaults', function (): void {
    $first = Warehouse::factory()->create(['is_default' => true]);

    $this->actingAs($this->keeper)
        ->post(route('warehouses.store'), ['name' => 'İkinci', 'code' => 'WH-02', 'is_default' => '1'])
        ->assertRedirect();

    expect(Warehouse::query()->where('is_default', true)->count())->toBe(1)
        ->and($first->refresh()->is_default)->toBeFalse();
});

it('refuses to strip the default flag without a replacement', function (): void {
    $default = Warehouse::factory()->create(['is_default' => true]);
    Warehouse::factory()->create();

    $this->actingAs($this->keeper)
        ->patch(route('warehouses.update', $default), ['name' => $default->name, 'code' => $default->code])
        ->assertSessionHasErrors('is_default');

    expect($default->refresh()->is_default)->toBeTrue();
});

it('still edits the default warehouse when the flag comes back unchanged', function (): void {
    $default = Warehouse::factory()->create(['is_default' => true]);

    $this->actingAs($this->keeper)
        ->patch(route('warehouses.update', $default), [
            'name' => 'Merkez Depo',
            'code' => $default->code,
            'is_default' => '1',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($default->refresh()->name)->toBe('Merkez Depo')
        ->and($default->is_default)->toBeTrue();
});

it('never lets the default warehouse be deleted', function (): void {
    $default = Warehouse::factory()->create(['is_default' => true]);
    Warehouse::factory()->create();

    $this->actingAs($this->keeper)
        ->delete(route('warehouses.destroy', $default))
        ->assertSessionHasErrors('warehouse');

    expect(Warehouse::query()->count())->toBe(2);
});

it('keeps at least one warehouse', function (): void {
    // Tek depo zaten varsayilandir; kural yine de kendi basina tutmali.
    $only = Warehouse::factory()->create(['is_default' => false]);

    $this->actingAs($this->keeper)
        ->delete(route('warehouses.destroy', $only))
        ->assertSessionHasErrors('warehouse');

    expect(Warehouse::query()->count())->toBe(1);
});

it('refuses to delete a warehouse that still holds stock', function (): void {
    Warehouse::factory()->create(['is_default' => true]);
    $secondary = Warehouse::factory()->create();

    InventoryItem::factory()->create([
        'variant_id' => ProductVariant::factory(),
        'warehouse_id' => $secondary->id,
        'on_hand' => 7,
    ]);

    $this->actingAs($this->keeper)
        ->delete(route('warehouses.destroy', $secondary))
        ->assertSessionHasErrors('warehouse');

    expect(Warehouse::query()->count())->toBe(2)
        ->and(InventoryItem::query()->count())->toBe(1);
});

it('deletes an empty non-default warehouse', function (): void {
    Warehouse::factory()->create(['is_default' => true]);
    $secondary = Warehouse::factory()->create();

    $this->actingAs($this->keeper)
        ->delete(route('warehouses.destroy', $secondary))
        ->assertRedirect();

    expect(Warehouse::query()->count())->toBe(1);
});

it('lets accounting read the warehouse list but not change it', function (): void {
    Warehouse::factory()->create(['is_default' => true]);
    $accountant = User::factory()->create()->assignRole('Muhasebe');

    $this->actingAs($accountant)
        ->get(route('warehouses.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('inventory/warehouses/index')
            ->has('warehouses', 1));

    $this->actingAs($accountant)
        ->post(route('warehouses.store'), ['name' => 'Yeni', 'code' => 'WH-09'])
        ->assertForbidden();
});

it('reports how much stock each warehouse carries', function (): void {
    $warehouse = Warehouse::factory()->create(['is_default' => true]);

    InventoryItem::factory()->create(['warehouse_id' => $warehouse->id, 'on_hand' => 4]);
    InventoryItem::factory()->create(['warehouse_id' => $warehouse->id, 'on_hand' => 6]);

    $this->actingAs($this->keeper)
        ->get(route('warehouses.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('warehouses.0.itemCount', 2)
            ->where('warehouses.0.onHandTotal', 10));
});
