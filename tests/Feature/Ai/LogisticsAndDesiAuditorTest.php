<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Actions\Logistics\Ai\AuditCarrierDesiOvercharges;
use App\Actions\Logistics\Ai\RouteOrderShipment;
use App\Actions\Logistics\Ai\ScoreOrderReturnRisk;
use App\Ai\Agents\ReconciliationAuditorAgent;
use App\Ai\Agents\ReturnRiskScorerAgent;
use App\Ai\Agents\SmartCarrierRouterAgent;
use App\Marketplaces\Data\Enums\CanonicalOrderStatus;
use App\Models\ChannelConnection;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShipmentPackage;
use App\Models\User;
use Database\Seeders\TenantRoleSeeder;

beforeEach(function (): void {
    $this->seed(TenantRoleSeeder::class);
});

it('audits carrier desi overcharges and creates formal dispute letter', function (): void {
    ReconciliationAuditorAgent::fake([
        [
            'total_detected_loss' => 462.50,
            'currency' => 'TRY',
            'discrepancies' => [
                [
                    'type' => 'Kargo Desi Aşımı',
                    'reference_id' => 'TRK-9876543210',
                    'expected_value' => '1.0 Desi',
                    'charged_value' => '3.5 Desi',
                    'financial_loss' => 46.25,
                    'description' => 'Tişört ürünü 1 desi ebatında olmasına rağmen kargo faturasında 3.5 desi yazılmıştır.',
                ],
            ],
            'dispute_summary' => 'Toplam 10 gönderide 25 desi fazladan faturalandırılmıştır.',
            'formal_dispute_letter' => "KARGO DESİ TAHKİM VE DÜZELTME TALEBİ\nSayın İlgili,\nAşağıda barkodları ve sipariş numaraları belirtilen gönderilerimizde, ürün ebatlarımıza göre hesaplanan desi ile faturalandırılan desi arasında 2.5 kat fark tespit edilmiştir. Fazla kesilen 462.50 TL tutarın tarafımıza iadesini arz ederiz.",
        ],
    ]);

    $connection = ChannelConnection::factory()->create();
    $order = Order::query()->forceCreate([
        'connection_id' => $connection->id,
        'remote_id' => 'PKG-1001',
        'remote_order_number' => 'ORD-1001',
        'status' => CanonicalOrderStatus::Delivered,
        'placed_at' => now(),
    ]);

    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->for($product)->create([
        'dimensions' => ['width' => 20, 'length' => 25, 'height' => 3],
        'weight' => '0.250',
    ]);

    OrderLine::query()->forceCreate([
        'order_id' => $order->id,
        'variant_id' => $variant->id,
        'remote_line_id' => 'L-1',
        'sku' => $variant->sku,
        'quantity' => 1,
        'unit_price' => '150.00',
        'status' => CanonicalOrderStatus::Delivered,
    ]);

    $package = ShipmentPackage::query()->forceCreate([
        'order_id' => $order->id,
        'remote_package_id' => 'PKG-1001',
        'status' => CanonicalOrderStatus::Delivered->value,
    ]);

    $auditor = new AuditCarrierDesiOvercharges;
    $result = $auditor([$package]);

    expect($result['total_detected_loss'])->toBe(462.50)
        ->and($result['formal_dispute_letter'])->toContain('KARGO DESİ TAHKİM');
});

it('scores return risk and generates packaging instructions', function (): void {
    ReturnRiskScorerAgent::fake([
        [
            'risk_level' => 'high',
            'risk_score' => 85,
            'risk_factors' => [
                'Müşterinin son 30 günde 3 adet beden uyumsuzluğu iadesi var',
                'Bu abiye kategorisinde beden uyuşmazlığı iade oranı %42',
            ],
            'packaging_instruction' => 'Paket içine "Lütfen ürünü denemeden önce beden ölçü tablosunu kontrol ediniz" notu ve emniyet kilidi ekleyiniz.',
            'fraud_prevention_checklist' => [
                'Kargo paketleme anının barkodlu fotoğrafı',
                'Ürün güvenlik şeridinin sağlamlık kontrolü',
            ],
        ],
    ]);

    $connection = ChannelConnection::factory()->create();
    $order = Order::query()->forceCreate([
        'connection_id' => $connection->id,
        'remote_id' => 'PKG-2002',
        'remote_order_number' => 'ORD-2002',
        'status' => CanonicalOrderStatus::Created,
        'placed_at' => now(),
    ]);

    $scorer = new ScoreOrderReturnRisk;
    $risk = $scorer($order);

    expect($risk['risk_level'])->toBe('high')
        ->and($risk['risk_score'])->toBe(85)
        ->and($risk['packaging_instruction'])->toContain('emniyet kilidi');
});

it('routes order to optimal carrier based on speed and cost', function (): void {
    SmartCarrierRouterAgent::fake([
        [
            'recommended_carrier' => 'Trendyol Express',
            'estimated_cost' => 38.00,
            'estimated_delivery_days' => 1,
            'cost_savings_vs_default' => 14.00,
            'routing_reason' => 'Kadıköy bölgesi için Trendyol Express 1 gün teslimat ve en uygun desi fiyatını sunmaktadır.',
            'alternatives' => [
                ['carrier' => 'HepsiJet', 'cost' => 39.00, 'delivery_days' => 1, 'note' => 'Hızlı alternatif'],
                ['carrier' => 'Yurtiçi Kargo', 'cost' => 52.00, 'delivery_days' => 2, 'note' => 'Yüksek maliyet'],
            ],
        ],
    ]);

    $connection = ChannelConnection::factory()->create();
    $order = Order::query()->forceCreate([
        'connection_id' => $connection->id,
        'remote_id' => 'PKG-3003',
        'remote_order_number' => 'ORD-3003',
        'status' => CanonicalOrderStatus::Created,
        'placed_at' => now(),
    ]);

    $router = new RouteOrderShipment;
    $routing = $router($order, 1.2);

    expect($routing['recommended_carrier'])->toBe('Trendyol Express')
        ->and($routing['estimated_cost'])->toBe(38.00)
        ->and($routing['cost_savings_vs_default'])->toBe(14.00);
});

it('exposes logistics ai endpoints to authenticated users', function (): void {
    ReconciliationAuditorAgent::fake([
        [
            'total_detected_loss' => 100,
            'currency' => 'TRY',
            'discrepancies' => [],
            'dispute_summary' => 'Özet',
            'formal_dispute_letter' => 'Dilekçe metni',
        ],
    ]);

    $user = User::factory()->create()->assignRole('Yönetici');

    $response = $this->actingAs($user)->getJson(route('ai.logistics.desi-audit'));

    $response->assertOk()
        ->assertJsonPath('success', true);
});
