<?php

declare(strict_types=1);

use App\Enums\AdjustmentType;
use App\Enums\PriceListType;
use App\Enums\PriceRuleField;
use App\Enums\RoundingMethod;
use App\Models\Brand;
use App\Models\Price;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\TenantRoleSeeder;

beforeEach(function (): void {
    $this->seed(TenantRoleSeeder::class);

    $this->manager = User::factory()->create()->assignRole('Yönetici');
});

it('creates currency-based price list and converts prices accurately', function (): void {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
    Price::factory()->create([
        'variant_id' => $variant->id,
        'currency' => 'TRY',
        'list_price' => 100.00,
    ]);

    $this->actingAs($this->manager)
        ->post(route('price-lists.store'), [
            'name' => 'Dolar Listesi',
            'type' => PriceListType::Currency->value,
            'source_currency' => 'TRY',
            'target_currency' => 'USD',
            'exchange_rate' => 0.03, // 100 * 0.03 = 3.00 USD
            'rounding_method' => RoundingMethod::Round->value,
            'is_active' => true,
        ])
        ->assertRedirect();

    $priceList = PriceList::query()->first();
    expect($priceList)->not->toBeNull()
        ->and($priceList->items)->toHaveCount(1)
        ->and((float) $priceList->items->first()->list_price)->toBe(3.00)
        ->and($priceList->items->first()->currency)->toBe('USD');
});

it('creates dynamic price list with category rule', function (): void {
    $brand = Brand::factory()->create(['name' => 'Apple']);
    $product = Product::factory()->create(['brand_id' => $brand->id]);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
    Price::factory()->create([
        'variant_id' => $variant->id,
        'currency' => 'TRY',
        'list_price' => 1000.00,
    ]);

    $this->actingAs($this->manager)
        ->post(route('price-lists.store'), [
            'name' => 'Pazaryeri %20 Zamlı',
            'type' => PriceListType::Dynamic->value,
            'source_currency' => 'TRY',
            'target_currency' => 'TRY',
            'rounding_method' => RoundingMethod::None->value,
            'is_active' => true,
            'rules' => [
                [
                    'field' => PriceRuleField::Brand->value,
                    'condition_value' => $brand->id,
                    'adjustment_type' => AdjustmentType::Percentage->value,
                    'adjustment_value' => 20, // 1000 + %20 = 1200
                    'position' => 0,
                ],
            ],
        ])
        ->assertRedirect();

    $priceList = PriceList::query()->first();
    expect($priceList)->not->toBeNull()
        ->and($priceList->items)->toHaveCount(1)
        ->and((float) $priceList->items->first()->list_price)->toBe(1200.00);
});

it('updates individual item in manual price list', function (): void {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
    Price::factory()->create([
        'variant_id' => $variant->id,
        'currency' => 'TRY',
        'list_price' => 100.00,
    ]);

    $priceList = PriceList::factory()->create([
        'type' => PriceListType::Manual,
    ]);

    $item = $priceList->items()->create([
        'variant_id' => $variant->id,
        'list_price' => 100.00,
        'currency' => 'TRY',
    ]);

    $this->actingAs($this->manager)
        ->patchJson(route('price-lists.update-item', [$priceList, $item]), [
            'list_price' => 150.00,
            'sale_price' => 125.00,
        ])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'item' => [
                'list_price' => 150.00,
                'sale_price' => 125.00,
            ],
        ]);

    expect($item->refresh()->list_price)->toBe('150.00')
        ->and($item->sale_price)->toBe('125.00');
});
