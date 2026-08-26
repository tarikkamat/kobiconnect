<?php

declare(strict_types=1);

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\TenantRoleSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->seed(TenantRoleSeeder::class);

    $this->manager = User::factory()->create()->assignRole('Yönetici');
});

it('derives the brand slug from the name', function (): void {
    $this->actingAs($this->manager)
        ->post(route('brands.store'), ['name' => 'Şarj Aletleri'])
        ->assertRedirect();

    expect(Brand::query()->value('slug'))->toBe('sarj-aletleri');
});

it('refuses a second brand that collides on slug', function (): void {
    Brand::factory()->create(['name' => 'Kablo', 'slug' => 'kablo']);

    $this->actingAs($this->manager)
        ->post(route('brands.store'), ['name' => 'kablo'])
        ->assertSessionHasErrors('slug');

    expect(Brand::query()->count())->toBe(1);
});

it('keeps products when their brand is deleted', function (): void {
    $brand = Brand::factory()->create();
    $product = Product::factory()->create(['brand_id' => $brand->id]);

    $this->actingAs($this->manager)
        ->delete(route('brands.destroy', $brand))
        ->assertRedirect();

    expect(Brand::query()->count())->toBe(0)
        ->and($product->refresh()->brand_id)->toBeNull();
});

it('lists brands with their product counts', function (): void {
    $brand = Brand::factory()->create(['name' => 'Anker']);
    Product::factory()->count(2)->create(['brand_id' => $brand->id]);

    $this->actingAs($this->manager)
        ->get(route('brands.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('catalog/brands/index')
            ->where('brands.0.name', 'Anker')
            ->where('brands.0.productCount', 2)
        );
});

it('builds the materialized path from the parent chain', function (): void {
    $this->actingAs($this->manager)->post(route('categories.store'), ['name' => 'Elektronik']);
    $root = Category::query()->sole();

    $this->actingAs($this->manager)
        ->post(route('categories.store'), ['name' => 'Kablolar', 'parent_id' => $root->id]);

    $child = Category::query()->where('parent_id', $root->id)->sole();

    expect($root->path)->toBe((string) $root->id)
        ->and($child->path)->toBe($root->id.'/'.$child->id);
});

it('removes descendants together with the category', function (): void {
    $this->actingAs($this->manager)->post(route('categories.store'), ['name' => 'Ust']);
    $root = Category::query()->sole();
    $this->actingAs($this->manager)->post(route('categories.store'), ['name' => 'Alt', 'parent_id' => $root->id]);

    $this->actingAs($this->manager)
        ->delete(route('categories.destroy', $root))
        ->assertRedirect();

    expect(Category::query()->count())->toBe(0);
});

it('blocks catalog writes for a role that may only read', function (): void {
    $accountant = User::factory()->create()->assignRole('Muhasebe');
    $brand = Brand::factory()->create();

    $this->actingAs($accountant)->get(route('brands.index'))->assertOk();
    $this->actingAs($accountant)->post(route('brands.store'), ['name' => 'Olmaz'])->assertForbidden();
    $this->actingAs($accountant)->delete(route('brands.destroy', $brand))->assertForbidden();
    $this->actingAs($accountant)->post(route('categories.store'), ['name' => 'Olmaz'])->assertForbidden();

    expect(Brand::query()->count())->toBe(1)
        ->and(Category::query()->count())->toBe(0);
});

it('renders brand create and edit pages', function (): void {
    $brand = Brand::factory()->create(['name' => 'Apple']);

    $this->actingAs($this->manager)
        ->get(route('brands.create'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('catalog/brands/create'));

    $this->actingAs($this->manager)
        ->get(route('brands.edit', $brand))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('catalog/brands/edit')
            ->where('brand.name', 'Apple')
        );
});

it('renders category create and edit pages', function (): void {
    $category = Category::factory()->create(['name' => 'Telefonlar']);

    $this->actingAs($this->manager)
        ->get(route('categories.create'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('catalog/categories/create'));

    $this->actingAs($this->manager)
        ->get(route('categories.edit', $category))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('catalog/categories/edit')
            ->where('category.name', 'Telefonlar')
        );
});
