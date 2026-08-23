<?php

declare(strict_types=1);

use App\Models\ChannelConnection;
use App\Models\User;
use Database\Seeders\TenantRoleSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->seed(TenantRoleSeeder::class);
});

function seedReportOrder(int $connectionId, string $orderNumber, float $unitPrice, int $quantity, float $commissionRate, ?string $date = null): int
{
    $placedAt = $date ?? now()->toDateTimeString();

    $id = DB::table('orders')->insertGetId([
        'connection_id' => $connectionId,
        'remote_id' => 'REMOTE-'.$orderNumber,
        'remote_order_number' => $orderNumber,
        'status' => 'delivered',
        'external_status' => 'Delivered',
        'currency' => 'TRY',
        'placed_at' => $placedAt,
        'remote_last_modified_at' => $placedAt,
        'totals' => json_encode(['gross' => $unitPrice * $quantity]),
        'customer' => null,
        'raw' => null,
        'created_at' => $placedAt,
        'updated_at' => $placedAt,
    ]);

    DB::table('order_lines')->insert([
        [
            'order_id' => $id,
            'remote_line_id' => 'LINE-'.$orderNumber.'-1',
            'variant_id' => null,
            'sku' => 'SKU-'.$orderNumber,
            'barcode' => 'BAR-'.$orderNumber,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discounts' => json_encode([]),
            'commission' => $commissionRate,
            'vat_rate' => 20.0,
            'status' => 'delivered',
            'external_status' => 'Delivered',
            'created_at' => $placedAt,
            'updated_at' => $placedAt,
        ],
    ]);

    return $id;
}

it('refuses unauthenticated guests', function (): void {
    $this->get(route('reports.index'))->assertRedirect(route('login'));
    $this->get(route('reports.channels'))->assertRedirect(route('login'));
    $this->get(route('reports.products'))->assertRedirect(route('login'));
    $this->get(route('reports.penalties'))->assertRedirect(route('login'));
    $this->get(route('reports.orders'))->assertRedirect(route('login'));
});

it('renders financial and sales report page', function (): void {
    $trendyol = ChannelConnection::factory()->create([
        'name' => 'Trendyol Mağaza',
        'marketplace' => 'trendyol',
    ]);
    $hepsiburada = ChannelConnection::factory()->create([
        'name' => 'Hepsiburada Mağaza',
        'marketplace' => 'hepsiburada',
    ]);

    seedReportOrder($trendyol->getKey(), 'TY-001', 100.0, 2, 15.0);
    seedReportOrder($hepsiburada->getKey(), 'HB-001', 300.0, 1, 10.0);

    $user = User::factory()->create()->assignRole('Muhasebe');

    $this->actingAs($user)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('reports/index')
            ->has('range')
            ->has('filters')
            ->has('connections', 2)
            ->where('kpis.orderCount', 2)
            ->where('kpis.itemCount', 3)
            ->where('kpis.rawGrossSales', 500)
            ->has('salesTrend')
        );
});

it('renders channel breakdown report page', function (): void {
    $trendyol = ChannelConnection::factory()->create([
        'name' => 'Trendyol Mağaza',
        'marketplace' => 'trendyol',
    ]);
    $hepsiburada = ChannelConnection::factory()->create([
        'name' => 'Hepsiburada Mağaza',
        'marketplace' => 'hepsiburada',
    ]);

    seedReportOrder($trendyol->getKey(), 'TY-001', 100.0, 2, 15.0);
    seedReportOrder($hepsiburada->getKey(), 'HB-001', 300.0, 1, 10.0);

    $user = User::factory()->create()->assignRole('Muhasebe');

    $this->actingAs($user)
        ->get(route('reports.channels'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('reports/channels')
            ->has('range')
            ->has('filters')
            ->has('connections', 2)
            ->has('channelBreakdown', 2)
            ->where('channelBreakdown.0.name', 'Hepsiburada Mağaza')
        );
});

it('renders product sales report and handles search', function (): void {
    $trendyol = ChannelConnection::factory()->create([
        'name' => 'Trendyol Mağaza',
        'marketplace' => 'trendyol',
    ]);

    seedReportOrder($trendyol->getKey(), 'PROD-ALPHA', 150.0, 4, 15.0);
    seedReportOrder($trendyol->getKey(), 'PROD-BETA', 80.0, 2, 10.0);

    $user = User::factory()->create()->assignRole('Muhasebe');

    $this->actingAs($user)
        ->get(route('reports.products', ['search' => 'ALPHA']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('reports/products')
            ->where('filters.search', 'ALPHA')
            ->has('products', 1)
            ->where('products.0.sku', 'SKU-PROD-ALPHA')
            ->where('products.0.quantitySold', 4)
        );
});

it('renders penalties and deductions report page', function (): void {
    $trendyol = ChannelConnection::factory()->create([
        'name' => 'Trendyol Mağaza',
        'marketplace' => 'trendyol',
    ]);

    $placedAt = now()->toDateTimeString();
    $orderId = DB::table('orders')->insertGetId([
        'connection_id' => $trendyol->getKey(),
        'remote_id' => 'REMOTE-PENALTY-1',
        'remote_order_number' => 'PENALTY-001',
        'status' => 'delivered',
        'currency' => 'TRY',
        'placed_at' => $placedAt,
        'totals' => json_encode([
            'gross' => 500.0,
            'cargo_penalty' => 25.5,
            'late_penalty' => 50.0,
        ]),
        'created_at' => $placedAt,
        'updated_at' => $placedAt,
    ]);

    $user = User::factory()->create()->assignRole('Muhasebe');

    $this->actingAs($user)
        ->get(route('reports.penalties'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('reports/penalties')
            ->has('kpis')
            ->has('penalizedOrders', 1)
            ->where('penalizedOrders.0.orderNumber', 'PENALTY-001')
            ->where('penalizedOrders.0.rawTotalPenalty', 75.5)
        );
});

it('renders order statuses distribution report page', function (): void {
    $trendyol = ChannelConnection::factory()->create([
        'name' => 'Trendyol Mağaza',
        'marketplace' => 'trendyol',
    ]);

    seedReportOrder($trendyol->getKey(), 'ORD-1', 100.0, 1, 15.0);

    $user = User::factory()->create()->assignRole('Muhasebe');

    $this->actingAs($user)
        ->get(route('reports.orders'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('reports/orders')
            ->where('totalOrders', 1)
            ->has('statusDistribution')
        );
});

it('filters reports by channel connection', function (): void {
    $trendyol = ChannelConnection::factory()->create([
        'name' => 'Trendyol Mağaza',
        'marketplace' => 'trendyol',
    ]);
    $hepsiburada = ChannelConnection::factory()->create([
        'name' => 'Hepsiburada Mağaza',
        'marketplace' => 'hepsiburada',
    ]);

    seedReportOrder($trendyol->getKey(), 'TY-100', 100.0, 1, 15.0);
    seedReportOrder($hepsiburada->getKey(), 'HB-100', 400.0, 1, 10.0);

    $user = User::factory()->create()->assignRole('Muhasebe');

    $this->actingAs($user)
        ->get(route('reports.channels', ['connection' => $trendyol->getKey()]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('reports/channels')
            ->where('filters.connection', $trendyol->getKey())
            ->has('channelBreakdown', 1)
            ->where('channelBreakdown.0.name', 'Trendyol Mağaza')
        );
});
