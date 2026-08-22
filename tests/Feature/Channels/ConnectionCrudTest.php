<?php

declare(strict_types=1);

use App\Enums\ConnectionStatus;
use App\Marketplaces\Support\Capability;
use App\Marketplaces\Support\MarketplaceManager;
use App\Models\ChannelConnection;
use App\Models\User;
use Database\Seeders\TenantRoleSeeder;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->seed(TenantRoleSeeder::class);

    $this->manager = User::factory()->create()->assignRole('Yönetici');

    // Kaydetme her zaman bir saglik kontrolu tetikler.
    Http::fake(['*' => Http::response(['brands' => []])]);
});

function connectionPayload(array $overrides = []): array
{
    return [
        'name' => 'Ana mağaza',
        'marketplace' => 'trendyol',
        'seller_id' => '123456',
        'api_key' => 'public-key',
        'api_secret' => 'top-secret-value',
        'integrator' => 'SelfIntegration',
        'listing_tier' => '50k',
        'stage' => '0',
        ...$overrides,
    ];
}

it('validates a hepsiburada connection against hepsiburada rules, not trendyol ones', function (): void {
    $this->actingAs($this->manager)
        ->post(route('connections.store'), [
            'name' => 'HB mağaza',
            'marketplace' => 'hepsiburada',
            'merchant_id' => '123456',
            'service_key' => 'hb-secret',
            'integrator_user_agent' => 'kobi connect',
        ])
        ->assertSessionHasErrors(['merchant_id', 'integrator_user_agent'])
        // Trendyol'un alanlari hic sorulmaz.
        ->assertSessionDoesntHaveErrors(['seller_id', 'api_key', 'api_secret', 'listing_tier']);

    $this->actingAs($this->manager)
        ->post(route('connections.store'), [
            'name' => 'HB mağaza',
            'marketplace' => 'hepsiburada',
            'merchant_id' => '3f1b2c8a-0d4e-4f6a-9b2c-7d8e9f0a1b2c',
            'service_key' => 'hb-secret',
            'integrator_user_agent' => 'kobiconnect',
            'sit' => '1',
        ])
        ->assertRedirect();

    $connection = ChannelConnection::query()->sole();

    expect($connection->credentials['service_key'])->toBe('hb-secret')
        ->and($connection->credentials['sit'])->toBeTrue()
        ->and($connection->external_seller_id)->toBe('3f1b2c8a-0d4e-4f6a-9b2c-7d8e9f0a1b2c')
        // Trendyol'un anahtarlari kayda hic girmez.
        ->and($connection->credentials->toArray())->not->toHaveKey('api_key');
});

it('stores the credentials encrypted behind an unguessable webhook token', function (): void {
    $this->actingAs($this->manager)
        ->post(route('connections.store'), connectionPayload())
        ->assertRedirect();

    $connection = ChannelConnection::query()->sole();

    expect($connection->credentials['api_secret'])->toBe('top-secret-value')
        ->and($connection->external_seller_id)->toBe('123456')
        ->and($connection->webhook_token)->toHaveLength(48)
        ->and($connection->status)->toBe(ConnectionStatus::Active);
});

it('snapshots the capabilities the driver actually implements', function (): void {
    $this->actingAs($this->manager)->post(route('connections.store'), connectionPayload());

    // Beklenen liste elle yazilmaz: surucu bir Supports* arayuzu daha implement
    // ettiginde anlik goruntu onunla birlikte buyumeli.
    $expected = array_map(
        fn (Capability $capability): string => $capability->value,
        app(MarketplaceManager::class)->driver('trendyol')->capabilities(),
    );

    expect(ChannelConnection::query()->sole()->capabilities)->toBe($expected)
        ->and($expected)->toContain('brand_catalog');
});

it('never sends a stored secret back to the browser', function (): void {
    $this->actingAs($this->manager)->post(route('connections.store'), connectionPayload());

    $this->actingAs($this->manager)
        ->get(route('apps.index'))
        ->assertDontSee('top-secret-value')
        ->assertDontSee('public-key')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('connections.0.credentials.secretsStored', true)
            ->missing('connections.0.credentials.values.api_secret')
            ->missing('connections.0.credentials.values.api_key')
        );
});

it('keeps the stored secret when the form leaves the field blank', function (): void {
    $this->actingAs($this->manager)->post(route('connections.store'), connectionPayload());
    $connection = ChannelConnection::query()->sole();

    $this->actingAs($this->manager)
        ->patch(route('connections.update', $connection), connectionPayload([
            'name' => 'Yeni ad',
            'api_key' => '',
            'api_secret' => '',
        ]))
        ->assertRedirect();

    $connection->refresh();

    expect($connection->name)->toBe('Yeni ad')
        ->and($connection->credentials['api_secret'])->toBe('top-secret-value')
        ->and($connection->credentials['api_key'])->toBe('public-key');
});

it('refuses a seller id that is not numeric and an integrator Trendyol would reject', function (): void {
    $this->actingAs($this->manager)
        ->post(route('connections.store'), connectionPayload([
            'seller_id' => 'A-123',
            'integrator' => 'Kobi Connect!',
        ]))
        ->assertSessionHasErrors(['seller_id', 'integrator']);

    expect(ChannelConnection::query()->count())->toBe(0);
});

it('refuses a marketplace that has no driver', function (): void {
    // Kayitli OLMAYAN bir anahtar; 'hepsiburada' artik gercek bir surucu.
    $this->actingAs($this->manager)
        ->post(route('connections.store'), connectionPayload(['marketplace' => 'amazon']))
        ->assertSessionHasErrors('marketplace');
});

it('deletes a connection', function (): void {
    $connection = ChannelConnection::factory()->create();

    $this->actingAs($this->manager)
        ->delete(route('connections.destroy', $connection))
        ->assertRedirect();

    expect(ChannelConnection::query()->count())->toBe(0);
});

it('blocks every connection route for a role without channels.manage', function (): void {
    $warehouse = User::factory()->create()->assignRole('Depo');
    $connection = ChannelConnection::factory()->create();

    $this->actingAs($warehouse)->get(route('apps.index'))->assertForbidden();
    $this->actingAs($warehouse)->get(route('apps.index'))->assertForbidden();
    $this->actingAs($warehouse)->post(route('connections.store'), connectionPayload())->assertForbidden();
    $this->actingAs($warehouse)->patch(route('connections.update', $connection), connectionPayload())->assertForbidden();
    $this->actingAs($warehouse)->post(route('connections.health', $connection))->assertForbidden();
    $this->actingAs($warehouse)->delete(route('connections.destroy', $connection))->assertForbidden();

    expect(ChannelConnection::query()->count())->toBe(1);
});

it('names the connection itself when the form leaves the name out', function (): void {
    $payload = connectionPayload();
    unset($payload['name']);

    $this->actingAs($this->manager)->post(route('connections.store'), $payload)->assertRedirect();
    $this->actingAs($this->manager)->post(route('connections.store'), [...$payload, 'seller_id' => '654321'])->assertRedirect();

    expect(ChannelConnection::query()->orderBy('id')->pluck('name')->all())
        ->toBe(['trendyol-connection', 'trendyol-connection-2']);
});
