<?php

declare(strict_types=1);

use App\Actions\Channels\CheckConnectionHealth;
use App\Enums\ConnectionStatus;
use App\Models\ChannelConnection;
use App\Models\User;
use Database\Seeders\TenantRoleSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\Fixtures\Trendyol\Fixture;

beforeEach(function (): void {
    $this->seed(TenantRoleSeeder::class);

    $this->manager = User::factory()->create()->assignRole('Yönetici');
});

function trendyolConnection(bool $stage = false): ChannelConnection
{
    return ChannelConnection::factory()->create([
        'marketplace' => 'trendyol',
        'status' => ConnectionStatus::Paused,
        'credentials' => [
            'seller_id' => '4321',
            'api_key' => 'key',
            'api_secret' => 'secret',
            'integrator' => 'SelfIntegration',
            'stage' => $stage,
            'listing_tier' => '50k',
        ],
    ]);
}

it('marks the connection active and stamps the check when Trendyol answers', function (): void {
    Http::fake(['*' => Http::response(Fixture::json('brands'))]);

    $connection = trendyolConnection();

    $result = app(CheckConnectionHealth::class)->handle($connection);

    expect($result['ok'])->toBeTrue()
        ->and($connection->refresh()->status)->toBe(ConnectionStatus::Active)
        ->and($connection->last_health_check_at)->not->toBeNull()
        ->and($connection->settings['last_health_error'])->toBeNull();

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Basic '.base64_encode('key:secret'))
        && $request->hasHeader('User-Agent', '4321 - SelfIntegration'));
});

it('really calls Trendyol for every connection instead of reusing a cached answer', function (): void {
    Http::fake(['*' => Http::response(Fixture::json('brands'))]);

    $check = app(CheckConnectionHealth::class);
    $check->handle(trendyolConnection());
    $check->handle(trendyolConnection());

    Http::assertSentCount(2);
});

it('turns each Trendyol failure into an instruction the seller can act on', function (
    string $fixture,
    int $status,
    bool $stage,
    string $expected,
): void {
    Sleep::fake();
    Http::fake(['*' => Http::response(Fixture::json($fixture), $status)]);

    $connection = trendyolConnection($stage);
    $result = app(CheckConnectionHealth::class)->handle($connection);

    expect($result['ok'])->toBeFalse()
        ->and($result['message'])->toContain($expected)
        ->and($connection->refresh()->status)->toBe(ConnectionStatus::Error)
        ->and($connection->settings['last_health_error'])->toBe($result['message']);
})->with([
    'wrong key pair' => ['error-401', 401, false, 'API anahtarı veya gizli anahtar hatalı'],
    'missing user agent' => ['error-400', 403, false, '4321 - SelfIntegration'],
    'stage ip allow list' => ['error-400', 503, true, 'IP izin listesine'],
    'throttled' => ['error-429', 429, false, 'istek limiti aşıldı'],
]);

it('keeps the raw Trendyol error key in the message for anything unexpected', function (): void {
    Sleep::fake();
    Http::fake(['*' => Http::response(Fixture::json('error-400'), 400)]);

    $result = app(CheckConnectionHealth::class)->handle(trendyolConnection());

    expect($result['message'])->toContain('invalid.barcode');
});

it('runs the check from the manual button and reports it as a flash toast', function (): void {
    Http::fake(['*' => Http::response(Fixture::json('brands'))]);

    $connection = trendyolConnection();

    $this->actingAs($this->manager)
        ->post(route('connections.health', $connection))
        ->assertRedirect();

    expect($connection->refresh()->status)->toBe(ConnectionStatus::Active);
});

/**
 * Hepsiburada baglantilari sonda listesinde yoktu: her kaydetme "Bağlantı
 * denenemedi: Unknown marketplace [hepsiburada]" ile hatali isaretleniyordu.
 */
it('probes Hepsiburada with its own basic credentials and bare user agent', function (): void {
    Http::fake(['*' => Http::response(['success' => true, 'code' => 0, 'data' => ['items' => []]])]);

    $connection = ChannelConnection::factory()->create([
        'marketplace' => 'hepsiburada',
        'status' => ConnectionStatus::Paused,
        'credentials' => [
            'merchant_id' => '3f1b2c8a-0d4e-4f6a-9b2c-7d8e9f0a1b2c',
            'service_key' => 'hb-secret',
            'integrator_user_agent' => 'kobiconnect',
            'sit' => false,
        ],
    ]);

    $result = app(CheckConnectionHealth::class)->handle($connection);

    expect($result['ok'])->toBeTrue()
        ->and($connection->refresh()->status)->toBe(ConnectionStatus::Active);

    Http::assertSent(fn (Request $request): bool => $request->hasHeader(
        'Authorization',
        'Basic '.base64_encode('3f1b2c8a-0d4e-4f6a-9b2c-7d8e9f0a1b2c:hb-secret'),
        // Trendyol'un "{id} - Ad" bicimi burada 401/403 dondurur.
    ) && $request->hasHeader('User-Agent', 'kobiconnect'));
});

it('tells the seller to regenerate the service key when Hepsiburada answers 401', function (): void {
    Http::fake(['*' => Http::response(['message' => 'Unauthorized'], 401)]);

    $connection = ChannelConnection::factory()->create([
        'marketplace' => 'hepsiburada',
        'credentials' => [
            'merchant_id' => '3f1b2c8a-0d4e-4f6a-9b2c-7d8e9f0a1b2c',
            'service_key' => 'stale',
            'integrator_user_agent' => 'kobiconnect',
            'sit' => false,
        ],
    ]);

    $result = app(CheckConnectionHealth::class)->handle($connection);

    expect($result['ok'])->toBeFalse()
        ->and($result['message'])->toContain('Servis Anahtarı')
        ->and($connection->refresh()->status)->toBe(ConnectionStatus::Error);
});
