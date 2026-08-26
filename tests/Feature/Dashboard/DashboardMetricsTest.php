<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\ChannelConnection;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\TenantRoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->seed(TenantRoleSeeder::class);
});

function dashboardUser(): User
{
    /** @var User $user */
    $user = User::factory()->create()->assignRole('Yönetici');

    return $user;
}

/**
 * Deferred prop'lar ilk boyamada gonderilmez; kismi yeniden yukleme ile gelir.
 * Yanit XHR JSON'udur, `assertInertia` yalnizca view yanitlarini okur.
 */
/**
 * @param  array<string, string>  $query
 */
function dashboardProp(string $prop, array $query = []): TestResponse
{
    return test()->actingAs(dashboardUser())->get(route('dashboard', $query), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'dashboard',
        'X-Inertia-Partial-Data' => $prop,
    ]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function seedDashboardOrder(array $attributes = []): int
{
    return (int) DB::table('orders')->insertGetId(array_merge([
        'connection_id' => ChannelConnection::factory()->create()->getKey(),
        'remote_id' => (string) fake()->unique()->numberBetween(1000000, 9999999),
        'remote_order_number' => (string) fake()->unique()->numberBetween(1000000000, 9999999999),
        'status' => 'created',
        'external_status' => 'Created',
        'currency' => 'TRY',
        'placed_at' => now(),
        'remote_last_modified_at' => now(),
        'totals' => json_encode(['net' => '100.0000']),
        'created_at' => now(),
        'updated_at' => now(),
    ], $attributes));
}

it('answers the setup question synchronously and defers every heavy widget', function (): void {
    $this->actingAs(dashboardUser())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('dashboard')
            ->where('setup.hasConnections', false)
            ->where('setup.hasProducts', false)
            ->where('setup.hasOrders', false)
            ->missing('sales')
            ->missing('unmatched')
            ->missing('syncHealth')
            ->missing('criticalStock')
            ->missing('connections')
            ->missing('kpis')
            ->missing('salesTrend')
            ->missing('channelShare')
            ->missing('orderVolume')
            ->missing('salesTarget')
        );
});

it('sums sales over the default last-30-day range on the server', function (): void {
    seedDashboardOrder(['totals' => json_encode(['net' => '150.5000'])]);
    // Donem disi siparis toplama girmez.
    seedDashboardOrder([
        'placed_at' => now()->subDays(45),
        'totals' => json_encode(['net' => '999.0000']),
    ]);

    $response = dashboardProp('sales')->assertOk();

    expect($response->json('props.sales.count'))->toBe(1)
        ->and($response->json('props.sales.total'))->toContain('150,50')
        // Bicimlendirme sunucuda; istemciye ham sayi gitmiyor.
        ->and($response->json('props.sales.total'))->toContain('₺')
        ->and($response->json('props.sales.today'))->toBe(1);
});

it('answers for any explicit from/to range', function (): void {
    seedDashboardOrder([
        'placed_at' => now()->subDays(45),
        'totals' => json_encode(['net' => '999.0000']),
    ]);

    $response = dashboardProp('sales', [
        'from' => now('Europe/Istanbul')->subDays(50)->toDateString(),
        'to' => now('Europe/Istanbul')->subDays(40)->toDateString(),
    ])->assertOk();

    expect($response->json('props.sales.count'))->toBe(1)
        ->and($response->json('props.sales.total'))->toContain('999');
});

it('counts unmatched order lines and the orders they belong to', function (): void {
    $orderId = seedDashboardOrder();

    DB::table('order_lines')->insert([
        [
            'order_id' => $orderId, 'remote_line_id' => '1', 'variant_id' => null,
            'sku' => 'KOBI-1', 'barcode' => 'KOBI1', 'quantity' => 1,
            'unit_price' => '10.0000', 'discounts' => json_encode([]),
            'status' => 'created', 'external_status' => 'Created',
            'created_at' => now(), 'updated_at' => now(),
        ],
        [
            'order_id' => $orderId, 'remote_line_id' => '2', 'variant_id' => null,
            'sku' => 'KOBI-2', 'barcode' => 'KOBI2', 'quantity' => 1,
            'unit_price' => '10.0000', 'discounts' => json_encode([]),
            'status' => 'created', 'external_status' => 'Created',
            'created_at' => now(), 'updated_at' => now(),
        ],
    ]);

    $response = dashboardProp('unmatched')->assertOk();

    expect($response->json('props.unmatched.lines'))->toBe(2)
        ->and($response->json('props.unmatched.orders'))->toBe(1);
});

it('lists variants that fell to or below their safety stock', function (): void {
    $product = Product::factory()->create(['name' => 'Termos']);
    $low = ProductVariant::factory()->for($product)->create(['sku' => 'TRM-1']);
    $healthy = ProductVariant::factory()->for($product)->create(['sku' => 'TRM-2']);

    InventoryItem::factory()->create([
        'variant_id' => $low->getKey(), 'on_hand' => 5, 'reserved' => 0, 'safety_stock' => 5,
    ]);
    InventoryItem::factory()->create([
        'variant_id' => $healthy->getKey(), 'on_hand' => 50, 'reserved' => 0, 'safety_stock' => 5,
    ]);

    $response = dashboardProp('criticalStock')->assertOk();

    expect($response->json('props.criticalStock.count'))->toBe(1)
        ->and($response->json('props.criticalStock.items.0.sku'))->toBe('TRM-1')
        ->and($response->json('props.criticalStock.items.0.product'))->toBe('Termos');
});

it('surfaces errored connections so a silently stopped channel is visible', function (): void {
    ChannelConnection::factory()->create(['name' => 'Trendyol Ana', 'status' => 'error']);
    ChannelConnection::factory()->create(['name' => 'Trendyol Yedek', 'status' => 'active']);

    $response = dashboardProp('connections')->assertOk();

    expect($response->json('props.connections.errored'))->toBe(1)
        // Isme gore sirali: "Trendyol Ana" once gelir.
        ->and($response->json('props.connections.items.0.statusLabel'))->toBe('Hata')
        ->and($response->json('props.connections.items.1.statusLabel'))->toBe('Aktif');
});

/*
|--------------------------------------------------------------------------
| Grafikler — ornek veri (App\Support\DashboardDemoData)
|--------------------------------------------------------------------------
|
| Gercek sorgulara gecildiginde bu blok da gider; sozlesme (series/rows) ayni
| kaldigi surece frontend'e dokunulmaz.
|
*/

it('draws chart series from the tenant own marketplaces', function (): void {
    ChannelConnection::factory()->create(['marketplace' => 'hepsiburada']);

    $response = dashboardProp('salesTrend')->assertOk();

    expect($response->json('props.salesTrend.series'))->toBe([
        ['key' => 'hepsiburada', 'label' => 'Hepsiburada', 'logo' => '/apps/hepsiburada.svg'],
    ]);
});

it('falls back to showcase marketplaces when no channel is connected', function (): void {
    $response = dashboardProp('salesTrend')->assertOk();

    expect(array_column((array) $response->json('props.salesTrend.series'), 'key'))
        ->toBe(['trendyol', 'hepsiburada'])
        // Varsayilan donem son 30 gun.
        ->and($response->json('props.salesTrend.rows'))->toHaveCount(30)
        // Her satir her seriyi tasir; eksik anahtar grafikte bosluk acar.
        ->and($response->json('props.salesTrend.rows.0'))
        ->toHaveKeys(['date', 'trendyol', 'hepsiburada']);
});

it('trims the sales charts to the selected range', function (): void {
    $response = dashboardProp('salesTrend', [
        'from' => now('Europe/Istanbul')->subDays(6)->toDateString(),
        'to' => now('Europe/Istanbul')->toDateString(),
    ])->assertOk();

    expect($response->json('props.salesTrend.rows'))->toHaveCount(7);
});

it('returns identical numbers on every request', function (): void {
    // Rastgelelik olsaydi sunumda her yenilemede grafik ziplardi; Octane'de de
    // global rastgelelik durumu istekler arasinda sizardi.
    $first = dashboardProp('salesTrend')->json('props.salesTrend');
    $second = dashboardProp('salesTrend')->json('props.salesTrend');

    expect($first)->toBe($second);
});

it('feeds every chart from the same daily matrix', function (): void {
    $trend = (array) dashboardProp('salesTrend')->json('props.salesTrend.rows');
    $share = (array) dashboardProp('channelShare')->json('props.channelShare.items');

    foreach ($share as $slice) {
        $fromTrend = array_sum(array_column($trend, $slice['key']));

        // Pasta dilimi trendin ayni donemdeki toplamiyla BIREBIR tutmali;
        // tutmazsa panel kendi icinde celisir.
        expect(round($fromTrend, 2))->toBe(round((float) $slice['value'], 2));
    }
});

it('formats money and percentages on the server', function (): void {
    $kpis = (array) dashboardProp('kpis')->json('props.kpis.items');
    $target = (array) dashboardProp('salesTarget')->json('props.salesTarget');

    expect($kpis)->toHaveCount(4)
        ->and($kpis[0]['value'])->toContain('₺')
        ->and($kpis[3]['value'])->toContain('%')
        ->and($target['overall']['target'])->toContain('₺')
        ->and($target['items'][0]['percent'])->toBeLessThanOrEqual(100);
});

it('totals order volume on the server for the header switches', function (): void {
    $volume = (array) dashboardProp('orderVolume')->json('props.orderVolume');

    expect($volume['rows'])->toHaveCount(30)
        ->and($volume['totals']['revenue'])->toContain('₺')
        ->and($volume['rows'][0]['orders'])->toBeGreaterThan(0);
});
