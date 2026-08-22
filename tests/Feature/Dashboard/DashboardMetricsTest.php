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
function dashboardProp(string $prop): TestResponse
{
    return test()->actingAs(dashboardUser())->get(route('dashboard'), [
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
        );
});

it('formats sales totals on the server and keeps last week out of today', function (): void {
    seedDashboardOrder(['totals' => json_encode(['net' => '150.5000'])]);
    seedDashboardOrder([
        'placed_at' => now()->subDays(20),
        'totals' => json_encode(['net' => '999.0000']),
    ]);

    $response = dashboardProp('sales')->assertOk();

    expect($response->json('props.sales.today.count'))->toBe(1)
        ->and($response->json('props.sales.today.total'))->toContain('150,50')
        // Bicimlendirme sunucuda; istemciye ham sayi gitmiyor.
        ->and($response->json('props.sales.today.total'))->toContain('₺');
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
        ->and($response->json('props.connections.items.1.statusLabel'))->toBe('Bağlı');
});
