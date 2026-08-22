<?php

declare(strict_types=1);

use App\Actions\Onboarding\RegisterTenant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Exceptions\TenantDatabaseAlreadyExistsException;
use Tests\TestCase;

const ONBOARDING_COMPANY = 'Onboarding Acme';

/**
 * Central baglanti RefreshDatabase'in transaction'i DISINDADIR (transaction
 * varsayilan `pgsql` baglantisindadir), bu yuzden burada yaratilan tenant'lar
 * testler arasinda yasar. Elle temizliyoruz.
 *
 * @param  list<string>  $ids
 */
function forgetOnboardingTenants(array $ids): void
{
    foreach ($ids as $id) {
        // Tenant silmek TenantDeleted -> DROP SCHEMA CASCADE zincirini
        // calistirir; satir yoksa da sema kalmis olabilir, ikisini de sil.
        rescue(fn (): ?bool => Tenant::find($id)?->delete(), report: false);

        DB::connection('central')->statement('DROP SCHEMA IF EXISTS "tenant'.$id.'" CASCADE');
        DB::connection('central')->table('tenants')->where('id', $id)->delete();
    }
}

/**
 * Kimlikler artik sequence'ten geliyor; hangi numaranin dusecegini test
 * onceden bilemez. Sabit test tenant'i disindaki her seyi topluyoruz.
 */
function forgetGeneratedTenants(): void
{
    forgetOnboardingTenants(
        DB::connection('central')->table('tenants')
            ->where('id', '!=', TestCase::TENANT_ID)
            ->pluck('id')
            ->map(fn ($id): string => (string) $id)
            ->all()
    );
}

beforeEach(function (): void {
    forgetGeneratedTenants();

});

afterEach(fn () => forgetGeneratedTenants());

/**
 * @return array<string, string>
 */
function onboardingPayload(array $overrides = []): array
{
    // Workspace adresi formda YOK: sunucuda belirlenir.
    return [
        'company' => ONBOARDING_COMPANY,
        'name' => 'Ayşe Yılmaz',
        'email' => 'ayse@acme.test',
        'password' => 'password',
        'password_confirmation' => 'password',
        ...$overrides,
    ];
}

it('kayit ekrani yalnizca hesap bilgisi ister', function (): void {
    $this->get(route('onboarding.register'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('onboarding/register')
            ->missing('plans'));
});

it('panelde oturumu olan kullaniciya kayit ekranini misafir olarak acar', function (): void {
    // Tek host = tek session cookie: paneldeki oturumun anahtari central
    // istekle de gelir. Central baglantida users tablosu yoktur; session'daki
    // id cozulmeye kalkilsa "relation users does not exist" olurdu.
    // TenantUserProvider central'da user yuklemeyi hic denemez.
    $user = User::factory()->create();

    tenancy()->end();

    $this->withSession([auth()->guard('web')->getName() => $user->getAuthIdentifier()])
        ->get(route('onboarding.register'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('onboarding/register')
            ->where('auth.user', null));
});

it('workspace adresini 1001 ve sonrasindan sirayla atar', function (): void {
    $this->post(route('onboarding.register.store'), onboardingPayload([
        'company' => 'Örnek Ticaret A.Ş.',
        'email' => 'sahip@ornek.test',
    ]))->assertRedirect();

    $first = Tenant::query()->where('data->name', 'Örnek Ticaret A.Ş.')->sole();

    // Firma adi kimlige HIC karismaz: numara sequence'ten gelir, 1001'den
    // baslar ve bir sonraki kayit bir fazlasini alir.
    expect((int) $first->getTenantKey())->toBeGreaterThanOrEqual(1001);

    $this->post(route('onboarding.register.store'), onboardingPayload([
        'company' => 'Örnek Ticaret A.Ş.',
        'email' => 'ikinci@ornek.test',
    ]))->assertRedirect();

    $second = Tenant::query()->where('data->name', 'Örnek Ticaret A.Ş.')
        ->orderByDesc('id')->first();

    expect((int) $second->getTenantKey())->toBe((int) $first->getTenantKey() + 1);
});

it('tenant ve Sahip rollu kullanici yaratip panele yonlendirir', function (): void {
    $response = $this->post(route('onboarding.register.store'), onboardingPayload());

    $tenant = Tenant::query()->where('data->name', ONBOARDING_COMPANY)->sole();

    $response->assertRedirect(route('dashboard', ['tenant' => $tenant->getTenantKey()]));
    $this->assertAuthenticated();

    $tenant->run(function (): void {
        $owner = User::query()->where('email', 'ayse@acme.test')->first();

        expect($owner)->not->toBeNull()
            ->and($owner->hasRole('Sahip'))->toBeTrue()
            ->and($owner->hasVerifiedEmail())->toBeTrue();
    });
});

it('kurulum yarida kalirsa tenant kaydini geride birakmaz', function (): void {
    // Sema onceden varsa CreateDatabase patlar — pipeline'in gercekci
    // basarisizligi. Tenant satiri o an zaten yazilmistir. Sequence tek
    // kullanicili oldugu icin bir sonraki numara tahmin edilebilir.
    $nextId = (int) DB::connection('central')->scalar("SELECT nextval('tenant_ids')") + 1;

    DB::connection('central')->statement('CREATE SCHEMA "tenant'.$nextId.'"');

    $register = app(RegisterTenant::class);

    expect(fn () => $register([
        'company' => 'Yarim Kalan',
        'name' => 'Ali Veli',
        'email' => 'ali@yarim.test',
        'password' => 'password',
    ]))->toThrow(TenantDatabaseAlreadyExistsException::class);

    expect(Tenant::find((string) $nextId))->toBeNull();

    DB::connection('central')->statement('DROP SCHEMA IF EXISTS "tenant'.$nextId.'" CASCADE');
});
