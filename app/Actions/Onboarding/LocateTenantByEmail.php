<?php

declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\Models\Tenant;
use App\Models\User;
use Closure;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;

/**
 * Central ekranlarin (giris, parola sifirlama) ortak sorusu: bu e-posta hangi
 * tenant'ta? Kullanicilar tenant semasinda yasar (BACKEND-PLAN.md §4.1), bu
 * yuzden central tarafta e-postadan tenant'a giden tek yol semalari gezmektir.
 */
final class LocateTenantByEmail
{
    /**
     * @param  Closure(User): bool|null  $accept  Ek kosul (or. parola dogrulama);
     *                                            null ise ilk eslesen tenant doner.
     * @return array{0: Tenant, 1: User}|null
     */
    public function __invoke(string $email, ?Closure $accept = null): ?array
    {
        // ponytail: her tenant semasinda tek sorgu — O(tenant sayisi). Tenant
        // sayisi buyudugunde central'a bir `email -> tenant` indeks tablosu
        // ekleyip burayi tek sorguya indir.
        $match = null;

        // `null` = tum tenant'lar; runForMultiple cursor ile gezer ve sonunda
        // onceki baglami geri yukler.
        tenancy()->runForMultiple(null, function (TenantContract $tenant) use ($email, $accept, &$match): void {
            if ($match !== null) {
                return;
            }

            // Semasi bozuk/yarim kalmis tek bir tenant butun aramayi
            // dusurmemeli; rescue hatayi raporlar ve donguyu surdurur.
            $user = rescue(fn (): ?User => User::query()->where('email', $email)->first());

            if ($user instanceof User && ($accept === null || $accept($user))) {
                /** @var Tenant $tenant */
                $match = [$tenant, $user];
            }
        });

        return $match;
    }
}
