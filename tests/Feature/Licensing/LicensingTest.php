<?php

declare(strict_types=1);

use App\Actions\Licensing\ActivateLicense;
use App\Actions\Licensing\CheckQuota;
use App\Actions\Licensing\RenewLicense;
use App\Actions\Licensing\SuspendLicense;
use App\Enums\LicenseStatus;
use App\Http\Middleware\EnsureLicenseIsActive;
use App\Models\License;
use App\Models\LicenseEvent;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\UsageCounter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Laravel\Pennant\Feature;
use Stancl\Tenancy\Events\TenantCreated;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/*
 * Lisans tablolari `central` baglantisinda yasar; RefreshDatabase yalnizca
 * varsayilan baglantiyi transaction'a alir, bu yuzden central'i elle sariyoruz.
 */
beforeEach(function (): void {
    // TestCase::initializeTestTenancy() zaten gercek bir tenant ('test') kurdu
    // ve tenancy'i baslatti. Buradan sonra olusturulan *ek* tenant'lar yalnizca
    // scope ayrimi icin gerekli.
    //
    // ponytail: lisans tamamen central semada yasar; ek tenant'lar icin sema
    // provision etmek bu testlere hicbir sey katmaz. Ustelik `CREATE SCHEMA`
    // asagidaki central transaction'in disindaki `tenant` baglantisindan
    // gorunmezdi.
    Event::fake([TenantCreated::class]);

    // Bildirim listener'i ShouldQueue'dur: uretimde ayri bir iste kosar ve
    // hatasi tetikleyen istegi KIRMAZ. Testte kuyruk `sync` oldugu icin satir
    // ici kosup semasi olmayan sahte tenant'larda patlardi — kuyrugu sahteleyip
    // uretim davranisini dogru temsil ediyoruz.
    Queue::fake();

    DB::connection('central')->beginTransaction();
});

afterEach(function (): void {
    if (tenancy()->initialized) {
        tenancy()->end();
    }

    DB::connection('central')->rollBack();
});

function makeTenant(): Tenant
{
    return Tenant::create();
}

/**
 * @param  array<string, mixed>  $features
 */
function makePlan(array $features, string $name = 'Profesyonel'): Plan
{
    return Plan::factory()->withFeatures($features)->create(['name' => $name]);
}

function runMiddleware(string $method = 'GET'): Response
{
    return (new EnsureLicenseIsActive)->handle(
        Request::create('/panel', $method),
        fn (): Response => new Response('ok'),
    );
}

/**
 * @return array{0: int, 1: string}
 */
function catchAbort(callable $callback): array
{
    try {
        $callback();
    } catch (HttpException $exception) {
        return [$exception->getStatusCode(), $exception->getMessage()];
    }

    throw new RuntimeException('Beklenen HttpException firlatilmadi.');
}

it('lisans modellerini central baglantiya sabitler', function (): void {
    expect((new License)->getConnectionName())->toBe('central')
        ->and((new Plan)->getConnectionName())->toBe('central')
        ->and((new UsageCounter)->getConnectionName())->toBe('central')
        ->and(config('pennant.stores.database.connection'))->toBe('central');
});

it('plan atadiginda lisansi olusturur, limitleri kopyalar ve olayi kaydeder', function (): void {
    $tenant = makeTenant();
    $plan = makePlan([
        'channels.max' => 3,
        'channels.allowed' => ['trendyol', 'hepsiburada'],
        'products.max' => 10000,
        'seats.max' => 5,
    ]);

    $license = app(ActivateLicense::class)($tenant, $plan);

    expect($license->tenant_id)->toBe($tenant->getTenantKey())
        ->and($license->status)->toBe(LicenseStatus::Active)
        ->and($license->seats)->toBe(5)
        ->and($license->limit('products.max'))->toBe(10000)
        ->and($license->isActive())->toBeTrue()
        ->and($license->isReadOnly())->toBeFalse();

    expect(LicenseEvent::where('license_id', $license->getKey())->pluck('type')->all())
        ->toBe(['license_activated']);
});

it('bir tenant icin tek lisans tutar ve plan degisiminde ustune yazar', function (): void {
    $tenant = makeTenant();
    $starter = makePlan(['products.max' => 1000], 'Baslangic');
    $pro = makePlan(['products.max' => 10000], 'Profesyonel');

    $first = app(ActivateLicense::class)($tenant, $starter);
    $second = app(ActivateLicense::class)($tenant, $pro);

    expect($second->getKey())->toBe($first->getKey())
        ->and(License::where('tenant_id', $tenant->getTenantKey())->count())->toBe(1)
        ->and($second->limit('products.max'))->toBe(10000);
});

it('plan feature kayitlarini tenant scope lu pennant bayraklarina yazar', function (): void {
    $starterTenant = makeTenant();
    $proTenant = makeTenant();

    $starter = makePlan(['channels.allowed' => ['trendyol'], 'products.max' => 1000]);
    $pro = makePlan(['channels.allowed' => ['trendyol', 'hepsiburada'], 'products.max' => 10000]);

    app(ActivateLicense::class)($starterTenant, $starter);
    app(ActivateLicense::class)($proTenant, $pro);

    expect(Feature::for($proTenant)->active('channel.hepsiburada'))->toBeTrue()
        ->and(Feature::for($starterTenant)->active('channel.hepsiburada'))->toBeFalse()
        ->and(Feature::for($starterTenant)->active('channel.trendyol'))->toBeTrue()
        ->and(Feature::for($proTenant)->value('products.max'))->toBe(10000);
});

it('plan dususunde eski bayraklari temizler', function (): void {
    $tenant = makeTenant();
    $pro = makePlan(['channels.allowed' => ['trendyol', 'hepsiburada']]);
    $starter = makePlan(['channels.allowed' => ['trendyol']]);

    app(ActivateLicense::class)($tenant, $pro);
    expect(Feature::for($tenant)->active('channel.hepsiburada'))->toBeTrue();

    app(ActivateLicense::class)($tenant, $starter);
    expect(Feature::for($tenant)->active('channel.hepsiburada'))->toBeFalse();
});

it('aktif lisansla istegi gecirir', function (): void {
    License::factory()->forTenant($this->tenant)->create();

    expect(runMiddleware('POST')->getContent())->toBe('ok');
});

it('grace period de okumaya izin verir, yazmayi 402 ile durdurur', function (): void {
    $license = License::factory()->forTenant($this->tenant)->inGracePeriod()->create();

    expect($license->hasAccess())->toBeTrue()
        ->and($license->isReadOnly())->toBeTrue();

    expect(runMiddleware('GET')->getContent())->toBe('ok');

    [$status] = catchAbort(fn () => runMiddleware('POST'));

    expect($status)->toBe(402);
});

it('grace period bitince her istegi 402 ile durdurur', function (): void {
    License::factory()->forTenant($this->tenant)->expired()->create();

    expect(catchAbort(fn () => runMiddleware('GET'))[0])->toBe(402)
        ->and(catchAbort(fn () => runMiddleware('POST'))[0])->toBe(402);
});

it('askiya alinmis lisansi engeller', function (): void {
    License::factory()->forTenant($this->tenant)->suspended()->create();

    expect(catchAbort(fn () => runMiddleware('GET'))[0])->toBe(402);
});

it('lisanssiz tenant i engeller', function (): void {
    expect(License::where('tenant_id', $this->tenant->getTenantKey())->exists())->toBeFalse()
        ->and(catchAbort(fn () => runMiddleware('GET'))[0])->toBe(402);
});

it('central istegine dokunmaz', function (): void {
    tenancy()->end();

    expect(runMiddleware('POST')->getContent())->toBe('ok');
});

it('kota altinda kullanimi sayar', function (): void {
    $tenant = makeTenant();
    $license = app(ActivateLicense::class)($tenant, makePlan(['products.max' => 10]));

    app(CheckQuota::class)($license, 'products.max', 3);

    expect(UsageCounter::valueFor($tenant->getTenantKey(), 'products.max'))->toBe(3);
});

it('kota asiminda 402 ve anlamli mesaj uretir, sayaci artirmaz', function (): void {
    $tenant = makeTenant();
    $license = app(ActivateLicense::class)($tenant, makePlan(['products.max' => 2], 'Baslangic'));

    app(CheckQuota::class)($license, 'products.max', 2);

    [$status, $message] = catchAbort(fn () => app(CheckQuota::class)($license, 'products.max'));

    expect($status)->toBe(402)
        ->and($message)->toContain('Baslangic')
        ->and($message)->toContain('ürün')
        ->and($message)->toContain('2/2')
        ->and(UsageCounter::valueFor($tenant->getTenantKey(), 'products.max'))->toBe(2);

    expect(LicenseEvent::where('license_id', $license->getKey())->pluck('type')->all())
        ->toContain('quota_exceeded');
});

it('kotanin yuzde 80 ini gecerken bir kez uyarir', function (): void {
    $tenant = makeTenant();
    $license = app(ActivateLicense::class)($tenant, makePlan(['products.max' => 10]));

    app(CheckQuota::class)($license, 'products.max', 7);
    expect(LicenseEvent::where('type', 'quota_warning')->count())->toBe(0);

    app(CheckQuota::class)($license, 'products.max');
    app(CheckQuota::class)($license, 'products.max');

    expect(LicenseEvent::where('type', 'quota_warning')->count())->toBe(1);
});

it('aylik metrikleri donem bazinda sayar', function (): void {
    $tenant = makeTenant();
    $license = app(ActivateLicense::class)($tenant, makePlan(['orders.per_month' => 5]));

    app(CheckQuota::class)($license, 'orders.per_month', 5);

    expect(catchAbort(fn () => app(CheckQuota::class)($license, 'orders.per_month'))[0])->toBe(402);

    $this->travel(1)->months();

    expect(app(CheckQuota::class)($license, 'orders.per_month'))->toBe(1);
});

it('limitsiz metrigi engellemez', function (): void {
    $tenant = makeTenant();
    $license = app(ActivateLicense::class)($tenant, makePlan(['products.max' => 10]));

    expect(app(CheckQuota::class)($license, 'webhooks.max', 999))->toBe(999);
});

it('yenileme donemi uzatir ve olayi kaydeder', function (): void {
    $tenant = makeTenant();
    $license = License::factory()->forTenant($tenant)->inGracePeriod()->create();
    $before = $license->ends_at;

    app(RenewLicense::class)($license->fresh());

    $license->refresh();

    expect($license->ends_at->greaterThan($before))->toBeTrue()
        ->and($license->isReadOnly())->toBeFalse()
        ->and(LicenseEvent::where('type', 'license_renewed')->count())->toBe(1);
});

it('askiya alma olayi kaydeder', function (): void {
    $tenant = makeTenant();
    $license = License::factory()->forTenant($tenant)->create();

    app(SuspendLicense::class)($license, 'odeme basarisiz');

    expect($license->refresh()->status)->toBe(LicenseStatus::Suspended)
        ->and($license->hasAccess())->toBeFalse()
        ->and(LicenseEvent::where('type', 'license_suspended')->value('payload'))
        ->toBe(['reason' => 'odeme basarisiz']);
});

it('7/3/1 gun kala uyarir ve grace bitince kapatir', function (): void {
    foreach ([7, 3, 1, 5] as $days) {
        License::factory()->forTenant(makeTenant())->create([
            'ends_at' => now()->addDays($days),
            'grace_until' => now()->addDays($days + 14),
        ]);
    }

    $done = License::factory()->forTenant(makeTenant())->expired()->create();

    $this->artisan('licenses:check-expiring')->assertSuccessful();

    expect(LicenseEvent::where('type', 'license_expiring')->count())->toBe(3)
        ->and($done->refresh()->status)->toBe(LicenseStatus::Expired)
        ->and(LicenseEvent::where('license_id', $done->getKey())->where('type', 'license_expired')->count())->toBe(1);
});
