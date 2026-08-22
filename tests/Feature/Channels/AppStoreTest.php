<?php

declare(strict_types=1);

use App\Marketplaces\Support\MarketplaceManager;
use App\Models\User;
use Database\Seeders\TenantRoleSeeder;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->seed(TenantRoleSeeder::class);

    $this->manager = User::factory()->create()->assignRole('Yönetici');

    Http::fake(['*' => Http::response(['brands' => []])]);
});

it('lists every app in the catalog and marks the ones without a driver as unavailable', function (): void {
    $response = $this->actingAs($this->manager)->get(route('apps.index'));

    $response->assertInertia(fn (AssertableInertia $page) => $page->component('channels/apps/index'));

    $apps = collect($response->viewData('page')['props']['apps'])->keyBy('code');

    // Beklenen liste elle yazilmaz: katalog config'tir, test onu izler.
    expect($apps->keys()->sort()->values()->all())
        ->toBe(collect(array_keys(config('apps.apps')))->sort()->values()->all());

    // Kurulabilirlik surucu kaydindan gelir, katalogtan degil.
    foreach ($apps as $code => $app) {
        expect($app['available'])->toBe(array_key_exists($code, config('marketplaces.drivers')));
    }

    expect($apps['trendyol']['logo'])->toBe('/apps/trendyol.svg')
        ->and($apps['shopify']['available'])->toBeFalse();
});

it('ships each app its own credential form, with no rules leaking to the browser', function (): void {
    $props = $this->actingAs($this->manager)->get(route('apps.index'))->viewData('page')['props'];

    $fields = collect($props['apps'])->keyBy('code')->map(
        fn (array $app): array => collect($app['fields'])->pluck('type', 'name')->all(),
    );

    expect($fields['trendyol'])->toBe([
        'seller_id' => 'text',
        'api_key' => 'secret',
        'api_secret' => 'secret',
        'integrator' => 'text',
        'listing_tier' => 'select',
        'stage' => 'checkbox',
    ])->and($fields['hepsiburada'])->toBe([
        'merchant_id' => 'text',
        'service_key' => 'secret',
        'integrator_user_agent' => 'text',
        'sit' => 'checkbox',
    ])
        // Surucusu olmayan uygulamanin kurulum formu da yoktur.
        ->and($fields['shopify'])->toBe([]);

    expect(collect($props['apps'])->pluck('fields')->flatten(1)->pluck('rules')->filter())->toBeEmpty();
});

it('derives capability badges from the driver, not from a hardcoded list', function (): void {
    $props = $this->actingAs($this->manager)->get(route('apps.index'))->viewData('page')['props'];

    $trendyol = collect($props['apps'])->firstWhere('code', 'trendyol');

    expect(collect($trendyol['capabilities'])->pluck('value')->all())->toBe(array_map(
        fn ($capability): string => $capability->value,
        app(MarketplaceManager::class)->driver('trendyol')->capabilities(),
    ));
});

it('lists installed connections next to the storefront', function (): void {
    $this->actingAs($this->manager)->post(route('connections.store'), [
        'name' => 'Ana mağaza',
        'marketplace' => 'trendyol',
        'seller_id' => '123456',
        'api_key' => 'public-key',
        'api_secret' => 'top-secret-value',
        'integrator' => 'SelfIntegration',
        'listing_tier' => '50k',
        'stage' => '0',
    ])->assertRedirect();

    $this->actingAs($this->manager)
        ->get(route('apps.show', ['app' => 'trendyol']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('channels/apps/show')
            ->where('app.code', 'trendyol')
            ->has('connections', 1)
            ->where('connections.0.name', 'Ana mağaza')
        );

    $this->actingAs($this->manager)
        ->get(route('apps.show', ['app' => 'hepsiburada']))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('connections', 0));
});

it('404s an app that is not in the catalog', function (): void {
    $this->actingAs($this->manager)->get(route('apps.show', ['app' => 'gittigidiyor']))->assertNotFound();
});

it('redirects the old connections screen to the store', function (): void {
    $this->actingAs($this->manager)
        ->get(str_replace('/channels/apps', '/channels/connections', route('apps.index')))
        ->assertRedirect(route('apps.index'));
});
