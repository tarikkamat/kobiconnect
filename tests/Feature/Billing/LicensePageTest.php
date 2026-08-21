<?php

declare(strict_types=1);

use App\Models\License;
use App\Models\UsageCounter;
use App\Models\User;
use Database\Seeders\TenantRoleSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->seed(TenantRoleSeeder::class);

    $this->owner = User::factory()->create()->assignRole('Sahip');
});

it('stays reachable when the tenant has no license at all', function (): void {
    // `grantActiveLicense()` BILEREK cagrilmadi: lisanssiz tenant panelin geri
    // kalanindan 402 alir ama bu ekrani gormek zorundadir.
    $this->actingAs($this->owner)
        ->get(route('license.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('settings/license')
            ->where('license', null)
            ->has('quotas', 4));
});

it('stays reachable when the license expired — otherwise nobody can pay', function (): void {
    License::factory()->forTenant($this->tenant)->expired()->create();

    // Ayni oturumdan panel route'u 402 dondugu halde bu ekran acilmali.
    $this->actingAs($this->owner)->get(route('dashboard'))->assertStatus(402);

    $this->actingAs($this->owner)
        ->get(route('license.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('license.hasAccess', false));
});

it('shows how many days are left in the grace period', function (): void {
    License::factory()->forTenant($this->tenant)->inGracePeriod()->create();

    $this->actingAs($this->owner)
        ->get(route('license.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('license.inGracePeriod', true)
            ->where('license.readOnly', true)
            ->where('license.graceDaysLeft', 7));
});

it('flags a quota yellow at 80 percent and red when it is full', function (): void {
    $license = $this->grantActiveLicense();
    $license->update(['limits' => ['products.max' => 10, 'channels.max' => 2]]);

    UsageCounter::record($license->tenant_id, 'products.max', 8);
    UsageCounter::record($license->tenant_id, 'channels.max', 2);

    $this->actingAs($this->owner)
        ->get(route('license.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('quotas.0.key', 'products.max')
            ->where('quotas.0.used', 8)
            ->where('quotas.0.level', 'warning')
            ->where('quotas.2.key', 'channels.max')
            ->where('quotas.2.level', 'critical'));
});

it('marks a metric the plan does not cap as unlimited', function (): void {
    $this->grantActiveLicense();

    $this->actingAs($this->owner)
        ->get(route('license.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('quotas.0.max', null)
            ->where('quotas.0.level', 'unlimited'));
});

it('counts seats from the users table, not the drifting counter', function (): void {
    $license = $this->grantActiveLicense();
    $license->update(['limits' => ['seats.max' => 5]]);

    User::factory()->create()->assignRole('Depo');
    User::factory()->create()->syncRoles([]);

    $this->actingAs($this->owner)
        ->get(route('license.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('quotas.3.key', 'seats.max')
            // Sahip + Depo; rolsuz kullanici koltuk tuketmez.
            ->where('quotas.3.used', 2)
            ->where('quotas.3.max', 5));
});

it('keeps billing to the owner', function (): void {
    $this->grantActiveLicense();
    $manager = User::factory()->create()->assignRole('Yönetici');

    $this->actingAs($manager)->get(route('license.index'))->assertForbidden();
});
