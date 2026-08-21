<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Stancl\Tenancy\Events\TenancyInitialized;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tenant, path'in ilk segmentidir (/{tenant}/...). Bu sinif o segmenti URL
 * varsayilani yapar, boylece `route('dashboard')` gibi cagrilar parametreyi
 * elle almak zorunda kalmaz.
 *
 * Iki girisi var, ikisi de gerekli:
 *
 * - `configure()` — TenancyInitialized dinleyicisi. HTTP disinda da (kuyruk,
 *   `tenants:run`, testler) varsayilani kurar.
 * - `handle()` — tenant route'larina takilan middleware. Runtime'da isi
 *   `configure()` ile ayni, ama Wayfinder middleware'lerin `handle()` govdesini
 *   METIN olarak tarar ve orada gordugu `URL::defaults` anahtarlarini
 *   TypeScript tarafinda opsiyonel parametreye cevirir. Bu satir olmadan her
 *   tenant route'u frontend'de zorunlu bir `tenant` argumani ister.
 *
 * Not: passkey Relying Party ID artik burada ayarlanmaz. Uygulama tek host'ta
 * yasadigi icin RP ID tum tenant'lar icin sabittir ve `config/fortify.php`'de
 * statik durur. Tenant ayrimi `User::getPasskeyUserHandle()` icinde yapilir —
 * bkz. BACKEND-PLAN.md §4.2.
 */
final class ConfigureTenantHost
{
    public function handle(Request $request, Closure $next): Response
    {
        // InitializeTenancyByPath bu middleware'den once calisir (tenancy
        // middleware'leri en yuksek onceliktedir) ve route parametresini
        // cozup dusurur; degeri tenant'in kendisinden okuyoruz.
        if (tenancy()->initialized) {
            URL::defaults(['tenant' => tenant()->getTenantKey()]);
        }

        return $next($request);
    }

    public function configure(TenancyInitialized $event): void
    {
        $tenant = $event->tenancy->tenant;

        if (! $tenant instanceof Tenant) {
            return;
        }

        URL::defaults(['tenant' => $tenant->getTenantKey()]);

        // Fortify giris/dogrulama sonrasi `fortify.home`'a yonlendirir ve o
        // deger sabit bir string'dir — tenant prefix'ini bilmez. URL
        // varsayilani yukarida kuruldugu icin `route()` dogru path'i uretir.
        if (Route::has('dashboard')) {
            config(['fortify.home' => route('dashboard', absolute: false)]);
        }
    }
}
