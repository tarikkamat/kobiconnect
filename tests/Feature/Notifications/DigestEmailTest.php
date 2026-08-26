<?php

declare(strict_types=1);

use App\Mail\Digest\DailySalesSummary;
use App\Mail\Digest\DailyStockAlert;
use App\Mail\Digest\WeeklyOperationsSummary;
use App\Mail\Digest\WeeklyPerformanceReport;
use App\Models\User;
use Database\Seeders\TenantRoleSeeder;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    $this->seed(TenantRoleSeeder::class);

    $this->user = User::factory()->create()->assignRole('Sahip');
});

it('can render DailySalesSummary mailable', function (): void {
    $mailable = new DailySalesSummary([
        'count' => 15,
        'total' => '25.450,00 ₺',
        'average' => '1.696,67 ₺',
        'change' => '+12%',
        'channels' => [
            ['name' => 'Trendyol', 'count' => 10, 'total' => '18.000,00 ₺'],
            ['name' => 'Hepsiburada', 'count' => 5, 'total' => '7.450,00 ₺'],
        ],
        'topSkus' => [
            ['sku' => 'TSHIRT-BLK-M', 'name' => 'Siyah Tişört M', 'quantity' => 8],
        ],
        'cancellations' => 1,
    ]);

    $mailable->assertHasSubject('Günlük Satış Özeti — 15 sipariş, 25.450,00 ₺');
    $mailable->assertSeeInHtml('Günlük Satış Özeti');
    $mailable->assertSeeInHtml('25.450,00 ₺');
    $mailable->assertSeeInHtml('Trendyol');
    $mailable->assertSeeInHtml('TSHIRT-BLK-M');
});

it('can render WeeklyPerformanceReport mailable', function (): void {
    $mailable = new WeeklyPerformanceReport([
        'period' => '19 Ağustos - 25 Ağustos 2026',
        'orders' => [
            'count' => 120,
            'total' => '185.000,00 ₺',
            'average' => '1.541,67 ₺',
            'change' => '+8%',
        ],
        'channels' => [
            ['name' => 'Trendyol', 'count' => 80, 'total' => '120.000,00 ₺'],
        ],
        'topProducts' => [
            ['sku' => 'SKU-001', 'name' => 'Ürün 1', 'quantity' => 25, 'total' => '35.000,00 ₺'],
        ],
        'claims' => [
            'count' => 3,
            'total' => '4.200,00 ₺',
        ],
        'criticalStock' => 4,
        'failedSyncs' => 0,
        'erroredConnections' => 0,
    ]);

    $mailable->assertHasSubject('Haftalık Rapor — 120 sipariş, 185.000,00 ₺');
    $mailable->assertSeeInHtml('Haftalık Performans Raporu');
    $mailable->assertSeeInHtml('185.000,00 ₺');
    $mailable->assertSeeInHtml('4 ürün');
});

it('can render DailyStockAlert mailable', function (): void {
    $mailable = new DailyStockAlert([
        'count' => 2,
        'items' => [
            [
                'sku' => 'SKU-LOW-1',
                'name' => 'Kritik Ürün 1',
                'available' => 2,
                'safetyStock' => 5,
                'warehouse' => 'Ana Depo',
            ],
        ],
    ]);

    $mailable->assertHasSubject('Stok Uyarısı — 2 ürün kritik seviyede');
    $mailable->assertSeeInHtml('SKU-LOW-1');
    $mailable->assertSeeInHtml('Ana Depo');
});

it('can render WeeklyOperationsSummary mailable', function (): void {
    $mailable = new WeeklyOperationsSummary([
        'connections' => [
            ['name' => 'Trendyol', 'marketplace' => 'trendyol', 'status' => 'Bağlı'],
        ],
        'failedSyncs' => 2,
        'rejectedProducts' => 1,
        'webhookIssues' => 0,
    ]);

    $mailable->assertHasSubject('Haftalık Operasyon Özeti');
    $mailable->assertSeeInHtml('Trendyol');
    $mailable->assertSeeInHtml('Başarısız Senkron');
});

it('runs digest email commands successfully', function (): void {
    Mail::fake();

    $this->artisan('email:daily-sales-summary')->assertSuccessful();
    $this->artisan('email:daily-stock-alert')->assertSuccessful();
    $this->artisan('email:weekly-performance')->assertSuccessful();
    $this->artisan('email:weekly-ops-summary')->assertSuccessful();
});
