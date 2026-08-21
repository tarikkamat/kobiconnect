<?php

declare(strict_types=1);

use App\Marketplaces\Data\Enums\CanonicalClaimStatus;
use App\Models\Claim;
use App\Models\ClaimItem;
use App\Models\User;
use Database\Seeders\TenantRoleSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->seed(TenantRoleSeeder::class);
    $this->grantActiveLicense();
});

function claimUser(string $role = 'Yönetici'): User
{
    /** @var User $user */
    $user = User::factory()->create()->assignRole($role);

    return $user;
}

it('lists claims with the actionable count the operator looks at first', function (): void {
    Claim::factory()->create();
    Claim::factory()->accepted()->create();

    $this->actingAs(claimUser())
        ->get(route('claims.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('claims/index')
            ->has('claims.data', 2)
            ->where('actionableTotal', 1)
            ->where('claims.data.0.statusLabel', fn (string $label): bool => in_array(
                $label, ['Aksiyon bekliyor', 'Kabul edildi'], true,
            ))
        );
});

it('filters by canonical status', function (): void {
    Claim::factory()->create();
    Claim::factory()->accepted()->create();

    $this->actingAs(claimUser())
        ->get(route('claims.index', ['status' => CanonicalClaimStatus::Accepted->value]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('claims.data', 1)
            ->where('claims.data.0.statusLabel', 'Kabul edildi')
        );
});

it('searches by claim and order number', function (): void {
    $claim = Claim::factory()->create(['remote_claim_id' => '5550001']);
    Claim::factory()->create(['remote_claim_id' => '9990001']);

    $this->actingAs(claimUser())
        ->get(route('claims.index', ['search' => '5550001']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('claims.data', 1)
            ->where('claims.data.0.id', $claim->getKey())
        );
});

it('shows a claim with its items and links back to the order', function (): void {
    $claim = Claim::factory()->create(['reason' => 'Ürün beklediğim gibi değil']);

    $lineId = DB::table('order_lines')->insertGetId([
        'order_id' => $claim->order_id, 'remote_line_id' => '900', 'variant_id' => null,
        'sku' => 'KOBI-9', 'barcode' => 'KOBI9', 'quantity' => 1,
        'unit_price' => '199.9000', 'discounts' => json_encode([]),
        'status' => 'delivered', 'external_status' => 'Delivered',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    ClaimItem::factory()->create([
        'claim_id' => $claim->getKey(),
        'order_line_id' => $lineId,
        'quantity' => 1,
    ]);

    $this->actingAs(claimUser())
        ->get(route('claims.show', $claim))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('claims/show')
            ->where('claim.reason', 'Ürün beklediğim gibi değil')
            ->where('order.id', $claim->order_id)
            ->has('items', 1)
            ->where('items.0.sku', 'KOBI-9')
            // Para sunucuda bicimlenir — FRONTEND-PLAN §7.
            ->where('items.0.unitPrice', fn (string $price): bool => str_contains($price, '199,90'))
        );
});

it('keeps the encrypted raw payload out of the props', function (): void {
    $claim = Claim::factory()->create();
    ClaimItem::factory()->create(['claim_id' => $claim->getKey()]);

    $response = $this->actingAs(claimUser())->get(route('claims.show', $claim))->assertOk();

    /** @var array<string, mixed> $props */
    $props = $response->viewData('page')['props'];

    // JSON_UNESCAPED_UNICODE sart: aksi halde Turkce iceren negatif
    // assertion'lar hicbir zaman eslesmez — .ai/rules/tests.md.
    expect(json_encode($props, JSON_UNESCAPED_UNICODE))->not->toContain('"raw"');
});

it('does not expose any write route for claims', function (): void {
    expect(collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_contains((string) $route->getName(), 'claims.'))
        ->flatMap(fn ($route): array => $route->methods())
        ->unique()
        ->values()
        ->all()
    )->toBe(['GET', 'HEAD']);
});

it('does not let an accountant without order access read claims', function (): void {
    $claim = Claim::factory()->create();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('claims.show', $claim))
        ->assertForbidden();
});
