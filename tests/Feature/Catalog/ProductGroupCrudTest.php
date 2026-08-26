<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\User;
use Database\Seeders\TenantRoleSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->seed(TenantRoleSeeder::class);

    $this->manager = User::factory()->create()->assignRole('Yönetici');
});

it('creates a product group and attaches products', function (): void {
    $p1 = Product::factory()->create();
    $p2 = Product::factory()->create();

    $this->actingAs($this->manager)
        ->post(route('product-groups.store'), [
            'name' => 'Kombin Setler',
            'description' => 'Birlikte satılan kombin ürünler.',
            'product_ids' => [$p1->id, $p2->id],
        ])
        ->assertRedirect();

    $group = ProductGroup::query()->first();
    expect($group)->not->toBeNull()
        ->and($group->slug)->toBe('kombin-setler')
        ->and($group->products)->toHaveCount(2);
});

it('shows product group detail and product list', function (): void {
    $group = ProductGroup::factory()->create(['name' => 'Yaz Koleksiyonu']);
    $product = Product::factory()->create(['name' => 'Keten Gömlek']);
    $group->products()->attach($product, ['position' => 0]);

    $this->actingAs($this->manager)
        ->get(route('product-groups.show', $group))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('catalog/product-groups/show')
            ->where('group.name', 'Yaz Koleksiyonu')
            ->where('products.0.name', 'Keten Gömlek')
        );
});

it('updates product group products', function (): void {
    $group = ProductGroup::factory()->create();
    $p1 = Product::factory()->create();
    $p2 = Product::factory()->create();
    $group->products()->attach($p1);

    $this->actingAs($this->manager)
        ->patch(route('product-groups.update', $group), [
            'name' => $group->name,
            'product_ids' => [$p2->id],
        ])
        ->assertRedirect();

    expect($group->refresh()->products->pluck('id')->all())->toBe([$p2->id]);
});

it('renders product group create and edit pages', function (): void {
    $group = ProductGroup::factory()->create(['name' => 'Kış Koleksiyonu']);

    $this->actingAs($this->manager)
        ->get(route('product-groups.create'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('catalog/product-groups/create'));

    $this->actingAs($this->manager)
        ->get(route('product-groups.edit', $group))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('catalog/product-groups/edit')
            ->where('group.name', 'Kış Koleksiyonu')
        );
});
