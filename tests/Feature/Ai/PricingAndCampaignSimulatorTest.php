<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Actions\Inventory\Ai\ForecastStockAndReorders;
use App\Actions\Marketing\Ai\SimulateCampaignProfitability;
use App\Actions\Pricing\Ai\CalculateDynamicPrice;
use App\Ai\Agents\CampaignProfitabilitySimulatorAgent;
use App\Ai\Agents\DynamicPricingAgent;
use App\Ai\Agents\PredictiveStockPlannerAgent;
use App\Models\InventoryItem;
use App\Models\Price;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\TenantRoleSeeder;

beforeEach(function (): void {
    $this->seed(TenantRoleSeeder::class);
});

it('calculates margin-protected dynamic buybox price when competitor stock is low', function (): void {
    DynamicPricingAgent::fake([
        [
            'recommended_price' => 389.90,
            'action' => 'increase_price',
            'projected_margin_percentage' => 32.5,
            'margin_floor_price' => 245.00,
            'competitor_stock_status' => 'low_stock',
            'pricing_rationale' => 'En yakın rakibin stoğu 2 adete düştü. Fiyat 1 TL kırmak yerine 389.90 TL seviyesine yükseltilerek maksimum kâr marjıyla Buybox korundu.',
        ],
    ]);

    $product = Product::factory()->create(['name' => 'Akıllı Saat']);
    $variant = ProductVariant::factory()->for($product)->create(['sku' => 'WATCH-01']);
    Price::factory()->create(['variant_id' => $variant->id, 'list_price' => 349.90, 'cost_price' => 140.00]);

    $calculator = new CalculateDynamicPrice;
    $result = $calculator($variant, 350.00, 'low_stock');

    expect($result['action'])->toBe('increase_price')
        ->and($result['recommended_price'])->toBe(389.90)
        ->and($result['projected_margin_percentage'])->toBe(32.5);
});

it('predicts stockout date and reorder volume based on velocity and lead time', function (): void {
    PredictiveStockPlannerAgent::fake([
        [
            'days_until_stockout' => 8,
            'predicted_stockout_date' => '2026-08-31',
            'recommended_reorder_date' => '2026-08-26',
            'recommended_reorder_quantity' => 150,
            'urgency' => 'critical',
            'sales_velocity_daily' => 5.2,
            'seasonal_impact_factor' => 1.3,
            'action_plan' => 'Ürün 8 gün içinde tükenecektir. Tedarik süresi 5 gün olduğundan, stoksuz kalmamak için en geç 3 gün içinde 150 adet sipariş verilmelidir.',
        ],
    ]);

    $product = Product::factory()->create(['name' => 'Kulaklık']);
    $variant = ProductVariant::factory()->for($product)->create(['sku' => 'EAR-01']);
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'on_hand' => 40, 'reserved' => 0]);

    $forecaster = new ForecastStockAndReorders;
    $forecast = $forecaster($variant, 5, 'Kasım Kampanyası Öncesi');

    expect($forecast['days_until_stockout'])->toBe(8)
        ->and($forecast['recommended_reorder_quantity'])->toBe(150)
        ->and($forecast['urgency'])->toBe('critical');
});

it('simulates campaign profitability to prevent margin erosion', function (): void {
    CampaignProfitabilitySimulatorAgent::fake([
        [
            'recommendation' => 'do_not_participate',
            'projected_net_margin_percentage' => 4.2,
            'projected_unit_profit' => 11.50,
            'breakeven_sales_multiplier' => 3.8,
            'warning' => 'Bu kampanyaya katılım durumunda net kâr marjınız %28\'den %4.2\'ye düşecektir. Satış adedinizin en az 3.8 kat artması gerekmektedir.',
            'counter_strategy' => 'Doğrudan %20 indirim yerine fiyatı 15 TL artırıp 20 TL kupon tanımlayarak katılınız.',
            'simulation_summary' => 'Doğrudan katılım önerilmez, alternatif kupon stratejisi uygulayınız.',
        ],
    ]);

    $product = Product::factory()->create(['name' => 'Sırt Çantası']);
    $variant = ProductVariant::factory()->for($product)->create();
    Price::factory()->create(['variant_id' => $variant->id, 'list_price' => 320.00, 'cost_price' => 120.00]);

    $simulator = new SimulateCampaignProfitability;
    $simulation = $simulator($product, 'Süper İndirim Günleri', 20.0, 0.0);

    expect($simulation['recommendation'])->toBe('do_not_participate')
        ->and($simulation['projected_net_margin_percentage'])->toBe(4.2)
        ->and($simulation['counter_strategy'])->toContain('kupon');
});

it('serves dynamic pricing and campaign simulation via api routes', function (): void {
    DynamicPricingAgent::fake([
        [
            'recommended_price' => 299.90,
            'action' => 'match_buybox',
            'projected_margin_percentage' => 20.0,
            'margin_floor_price' => 200.0,
            'competitor_stock_status' => 'healthy',
            'pricing_rationale' => 'Buybox eşlendi.',
        ],
    ]);

    $user = User::factory()->create()->assignRole('Yönetici');
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->for($product)->create();
    Price::factory()->create(['variant_id' => $variant->id]);

    $response = $this->actingAs($user)->postJson(route('ai.pricing.dynamic-price', $variant), [
        'competitor_price' => 300.0,
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('pricing.recommended_price', 299.90);
});
