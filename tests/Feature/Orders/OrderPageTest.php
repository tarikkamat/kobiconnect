<?php

declare(strict_types=1);

use App\Models\ChannelConnection;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\TenantRoleSeeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->seed(TenantRoleSeeder::class);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function seedOrder(array $attributes = []): int
{
    $connection = ChannelConnection::factory()->create(['name' => 'Trendyol Ana']);

    $id = DB::table('orders')->insertGetId(array_merge([
        'connection_id' => $connection->getKey(),
        'remote_id' => '1234567',
        'remote_order_number' => '1084507121',
        'status' => 'created',
        'external_status' => 'Created',
        'currency' => 'TRY',
        'placed_at' => now(),
        'remote_last_modified_at' => now(),
        'totals' => json_encode(['gross' => '449.9000', 'net' => '419.9000']),
        'customer' => Crypt::encryptString(json_encode([
            'firstName' => 'Ayşe',
            'lastName' => 'Yılmaz',
            'email' => 'ayse.yilmaz@example.com',
            'phone' => '05001234567',
            'identityNumber' => '12345678901',
            'shippingAddress' => [
                'city' => 'İstanbul',
                'district' => 'Ataşehir',
                'fullAddress' => 'Barbaros Mah. Ihlamur Sok. No:12 D:4',
                'latitude' => '40.9901',
            ],
        ])),
        'raw' => Crypt::encryptString(json_encode(['identityNumber' => '12345678901'])),
        'created_at' => now(),
        'updated_at' => now(),
    ], $attributes));

    DB::table('order_lines')->insert([
        [
            'order_id' => $id, 'remote_line_id' => '90001', 'variant_id' => null,
            'sku' => 'KOBI-001', 'barcode' => 'KOBI001', 'quantity' => 2,
            'unit_price' => '149.9000', 'discounts' => json_encode(['total' => '30.0000']),
            'commission' => '12.5000', 'vat_rate' => '20.0000',
            'status' => 'created', 'external_status' => 'Created',
            'created_at' => now(), 'updated_at' => now(),
        ],
    ]);

    DB::table('shipment_packages')->insert([[
        'order_id' => $id, 'remote_package_id' => '1234567',
        'cargo_provider' => 'Trendyol Express',
        'tracking_number' => '7318429576123456789',
        'tracking_link' => 'https://kargotakip.trendyol.com/?trackingNumber=7318429576123456789',
        'status' => 'created', 'external_status' => 'Created', 'deci' => 3.5,
        'shipped_at' => null, 'delivered_at' => null,
        'created_at' => now(), 'updated_at' => now(),
    ]]);

    DB::table('order_status_history')->insert([[
        'order_id' => $id, 'package_id' => null, 'from_status' => null,
        'to_status' => 'Created', 'occurred_at' => now(), 'source' => 'pull',
        'created_at' => now(),
    ]]);

    return $id;
}

it('lists orders with money and dates already formatted', function (): void {
    seedOrder();

    $this->actingAs(User::factory()->create()->assignRole('Depo'))
        ->get(route('orders.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('orders/index')
            ->has('orders.data', 1)
            ->where('orders.data.0.orderNumber', '1084507121')
            ->where('orders.data.0.statusLabel', 'Gönderime hazır')
            // Ham pazaryeri durumu bilgi olarak tasinir, filtre kanoniktir.
            ->where('orders.data.0.externalStatus', 'Created')
            ->where('orders.data.0.total', fn (string $total): bool => str_contains($total, '419,90'))
            ->where('orders.data.0.unmatchedCount', 1)
        );
});

it('never puts raw personal data into an inertia prop', function (): void {
    seedOrder();

    $response = $this->actingAs(User::factory()->create()->assignRole('Depo'))
        ->get(route('orders.index'));

    // JSON_UNESCAPED_UNICODE SART: varsayilan kacis "Yilmaz"i \u0131 ile
    // yazar ve negatif assertion'lar sizinti olsa bile ASLA eslesmez.
    $props = json_encode($response->viewData('page')['props'], JSON_UNESCAPED_UNICODE);

    // Ad kisaltilir, TCKN / tam adres / koordinat / e-posta hic gecmez.
    expect($props)
        ->toContain('Ayşe Y.')
        ->not->toContain('12345678901')
        ->not->toContain('Yılmaz')
        ->not->toContain('ayse.yilmaz@example.com')
        ->not->toContain('Barbaros Mah')
        ->not->toContain('40.9901');
});

it('filters the orders that carry an unmatched line', function (): void {
    seedOrder();

    $user = User::factory()->create()->assignRole('Depo');

    $this->actingAs($user)
        ->get(route('orders.index', ['unmatched' => 1]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('orders.data', 1)
            ->where('unmatchedTotal', 1)
        );

    // Satir katalogla eslestirildiginde kuyruktan duser.
    DB::table('order_lines')->update([
        'variant_id' => ProductVariant::factory()->create(['barcode' => 'KOBI001'])->getKey(),
    ]);

    $this->actingAs($user)
        ->get(route('orders.index', ['unmatched' => 1]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('orders.data', 0)
            ->where('unmatchedTotal', 0)
        );
});

it('filters on canonical status, not the marketplace one', function (): void {
    seedOrder();

    $user = User::factory()->create()->assignRole('Depo');

    $this->actingAs($user)
        ->get(route('orders.index', ['status' => 'created']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('orders.data', 1));

    $this->actingAs($user)
        ->get(route('orders.index', ['status' => 'shipped']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('orders.data', 0));

    // Ham pazaryeri degeri kanonik filtre olarak kabul edilmez.
    $this->actingAs($user)
        ->get(route('orders.index', ['status' => 'Created']))
        ->assertSessionHasErrors('status');
});

it('shows lines, package, tracking and status history on the detail page', function (): void {
    $id = seedOrder();

    $this->actingAs(User::factory()->create()->assignRole('Depo'))
        ->get(route('orders.show', ['order' => $id]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('orders/show')
            ->where('order.customer.name', 'Ayşe Y.')
            ->where('order.customer.email', 'a***@example.com')
            ->where('order.customer.phone', '*******4567')
            ->where('order.customer.city', 'İstanbul')
            ->has('lines', 1)
            ->where('lines.0.matched', false)
            ->has('packages', 1)
            // Kargo takip numarasi int64'u asar: string olarak tasinir.
            ->where('packages.0.trackingNumber', '7318429576123456789')
            ->has('history', 1)
        );
});

it('refuses a user without the orders.view permission', function (): void {
    seedOrder();

    $this->actingAs(User::factory()->create())
        ->get(route('orders.index'))
        ->assertForbidden();
});

it('sends the marketplace code so the list can show the source logo', function (): void {
    seedOrder();

    $this->actingAs(User::factory()->create()->assignRole('Yönetici'))
        ->get(route('orders.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('orders.data.0.marketplace', 'trendyol')
            ->where('orders.data.0.connection', 'Trendyol Ana')
        );
});
