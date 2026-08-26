<?php

declare(strict_types=1);

use App\Enums\DynamicCategoryField;
use App\Enums\DynamicCategoryMatchType;
use App\Enums\DynamicCategoryOperator;
use App\Models\Brand;
use App\Models\DynamicCategory;
use App\Models\Price;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\TenantRoleSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->seed(TenantRoleSeeder::class);

    $this->manager = User::factory()->create()->assignRole('Yönetici');
});

it('creates dynamic category and automatically matches products', function (): void {
    $brand = Brand::factory()->create(['name' => 'Nike']);
    $otherBrand = Brand::factory()->create(['name' => 'Adidas']);

    $p1 = Product::factory()->create(['brand_id' => $brand->id]);
    $p2 = Product::factory()->create(['brand_id' => $otherBrand->id]);

    $this->actingAs($this->manager)
        ->post(route('dynamic-categories.store'), [
            'name' => 'Nike Ürünleri',
            'match_type' => DynamicCategoryMatchType::All->value,
            'conditions' => [
                [
                    'field' => DynamicCategoryField::Brand->value,
                    'operator' => DynamicCategoryOperator::Contains->value,
                    'value' => 'Nike',
                ],
            ],
        ])
        ->assertRedirect();

    $cat = DynamicCategory::query()->first();
    expect($cat)->not->toBeNull()
        ->and($cat->products)->toHaveCount(1)
        ->and($cat->products->first()->id)->toBe($p1->id);
});

it('matches products with ANY match type', function (): void {
    $tag = Tag::factory()->create(['name' => 'Fırsat']);
    $p1 = Product::factory()->create(['name' => 'Ucuz Gömlek']);
    $p1->tags()->attach($tag);

    $p2 = Product::factory()->create(['name' => 'Pahalı Pantolon']);
    $variant = ProductVariant::factory()->create(['product_id' => $p2->id]);
    Price::factory()->create([
        'variant_id' => $variant->id,
        'list_price' => 1500,
    ]);

    $p3 = Product::factory()->create(['name' => 'Normal Ayakkabı']);

    $this->actingAs($this->manager)
        ->post(route('dynamic-categories.store'), [
            'name' => 'Fırsat veya Pahalı Ürünler',
            'match_type' => DynamicCategoryMatchType::Any->value,
            'conditions' => [
                [
                    'field' => DynamicCategoryField::Tag->value,
                    'operator' => DynamicCategoryOperator::Contains->value,
                    'value' => 'Fırsat',
                ],
                [
                    'field' => DynamicCategoryField::Price->value,
                    'operator' => DynamicCategoryOperator::GreaterThan->value,
                    'value' => 1000,
                ],
            ],
        ])
        ->assertRedirect();

    $cat = DynamicCategory::query()->first();
    expect($cat->products)->toHaveCount(2)
        ->and($cat->products->pluck('id')->all())->toContain($p1->id, $p2->id);
});

it('renders dynamic category create and edit pages', function (): void {
    $cat = DynamicCategory::factory()->create(['name' => 'Fırsat Ürünleri']);

    $this->actingAs($this->manager)
        ->get(route('dynamic-categories.create'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('catalog/dynamic-categories/create')
            ->has('fields')
            ->has('operators')
        );

    $this->actingAs($this->manager)
        ->get(route('dynamic-categories.edit', $cat))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('catalog/dynamic-categories/edit')
            ->where('category.name', 'Fırsat Ürünleri')
            ->has('fields')
            ->has('operators')
        );
});
