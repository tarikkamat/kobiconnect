<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\TenantRoleSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->seed(TenantRoleSeeder::class);

    $this->manager = User::factory()->create()->assignRole('Yönetici');
});

it('creates a unit with name and short name', function (): void {
    $this->actingAs($this->manager)
        ->post(route('units.store'), [
            'name' => 'Kilogram',
            'short_name' => 'kg',
        ])
        ->assertRedirect();

    expect(Unit::query()->first())
        ->name->toBe('Kilogram')
        ->short_name->toBe('kg');
});

it('lists units with product counts', function (): void {
    $unit = Unit::factory()->create(['name' => 'Servis', 'short_name' => 'srv']);
    Product::factory()->create(['unit_id' => $unit->id]);

    $this->actingAs($this->manager)
        ->get(route('units.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('catalog/units/index')
            ->where('units.0.name', 'Servis')
            ->where('units.0.shortName', 'srv')
            ->where('units.0.productCount', 1)
        );
});

it('nulls product unit_id when unit is deleted', function (): void {
    $unit = Unit::factory()->create();
    $product = Product::factory()->create(['unit_id' => $unit->id]);

    $this->actingAs($this->manager)
        ->delete(route('units.destroy', $unit))
        ->assertRedirect();

    expect(Unit::query()->count())->toBe(0)
        ->and($product->refresh()->unit_id)->toBeNull();
});
