<?php

declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\Actions\Licensing\ActivateLicense;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\UsageCounter;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Central kayit akisinin tamami: tenant, lisans ve sahip kullanici. Bunlarin
 * hepsi ya olur ya da hicbiri kalmaz.
 *
 * "Tek transaction" burada gercek bir DB transaction'i OLAMAZ: sema yaratma ve
 * tenant migration'lari ayri bir PDO uzerinde kosar (bkz. .ai/rules/tests.md),
 * dolayisiyla central transaction'i onlari kapsamaz. Atomiklik telafi ile
 * saglanir — herhangi bir adim patlarsa tenant silinir, bu da TenantDeleted ->
 * DROP SCHEMA CASCADE zincirini calistirir ve licenses satirlarini FK cascade
 * goturur.
 */
final class RegisterTenant
{
    public function __construct(private readonly ActivateLicense $activateLicense) {}

    /**
     * @param  array{company: string, name: string, email: string, password: string, plan: string}  $input
     * @return array{User, Tenant} Sahip kullanici ve tenant; cagiran taraf
     *                             yonlendirme icin tenant id'sine ihtiyac duyar.
     */
    public function __invoke(array $input): array
    {
        $plan = Plan::query()
            ->where('code', $input['plan'])
            ->where('is_public', true)
            ->firstOrFail();

        // Id bilerek verilmiyor: SequentialTenantIdGenerator sirayi central
        // `tenant_ids` sequence'inden alir (1001, 1002, ...) ve bu sayi dogrudan
        // URL segmenti olur.
        $tenant = new Tenant(['name' => $input['company']]);

        try {
            // TenantCreated pipeline'i `save()` sirasinda calisir: CREATE SCHEMA
            // + tenants:migrate + tenants:seed (roller). Bugun senkron
            // (TenancyServiceProvider: shouldBeQueued(false)), bu yuzden
            // donusten sonra sema kullanilabilir durumdadir. Insert `try`
            // icinde: pipeline patlarsa tenant satiri zaten yazilmis olur ve
            // temizlenmesi gerekir.
            $tenant->save();

            ($this->activateLicense)($tenant, $plan);

            $owner = $tenant->run(fn (): User => $this->createOwner($input));

            // Sahip de bir koltuktur. Sayilmazsa `seats.max` kotasi ilk davette
            // bir eksik hesaplanir (lisans ekrani `usage_counters`'tan okur).
            UsageCounter::record($tenant->getTenantKey(), 'seats.max', 1);

            return [$owner, $tenant];
        } catch (Throwable $exception) {
            // Hata `run()` icinde olduysa tenancy hala yeni tenant'ta; silme
            // islemi central baglanti uzerinden yapilmali. Telafi silme asil
            // hatayi maskelememeli — sema hic yaratilamamissa DROP SCHEMA de
            // patlar, o yuzden rescue.
            tenancy()->end();
            rescue(fn (): ?bool => $tenant->delete());

            throw $exception;
        }
    }

    /**
     * @param  array{company: string, name: string, email: string, password: string, plan: string}  $input
     */
    private function createOwner(array $input): User
    {
        if (! Schema::connection('tenant')->hasTable('users')) {
            throw new RuntimeException(
                'Tenant semasi hazir degil. TenantCreated pipeline\'i kuyruga alinmissa '
                .'(shouldBeQueued(true)) sahip kullanici pipeline icinde, migration '
                .'ve seed adimlarindan SONRA yaratilmalidir.'
            );
        }

        $owner = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);

        // ponytail: sahip kullanici dogrulanmis sayilir — kaydi yapan kisi
        // tenant'in sahibidir ve dogrudan panele girer, yoksa ilk deneyim
        // "e-postani dogrula" duvarina toslar. E-posta sahipliginin kayit
        // aninda kanitlanmasi gerekirse bu satiri kaldirmak yeterli; Fortify'in
        // dogrulama akisi zaten devrede.
        $owner->forceFill(['email_verified_at' => now()])->save();

        $owner->assignRole('Sahip');

        return $owner;
    }
}
