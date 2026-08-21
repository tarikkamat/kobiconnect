<?php

use App\Http\Middleware\EndTenancyAfterRequest;
use App\Http\Middleware\EnsureLicenseIsActive;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Listeners\ConfigureTenantHost;
use App\Models\SyncRun;
use App\Models\WebhookEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;
use Stancl\Tenancy\Middleware\ScopeSessions;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Uygulama TEK host'ta yasar (app.kobiconnect.com). Tenant path'ten
            // cozulur: /{tenant}/dashboard
            //
            // `InitializeTenancyByPath` tenant'in route'un ILK parametresi
            // olmasini sart kosar; prefix bunu garanti eder. PathTenantResolver
            // parametreyi cozdukten sonra forgetParameter() ile dusurur, boylece
            // controller imzalarina sizmaz.
            //
            // Tek host = tek session cookie. ScopeSessions bu yuzden ZORUNLU:
            // session'a `_tenant_id` yazar ve baska bir tenant'in path'ine
            // gecildiginde 403 verir.
            Route::prefix('{tenant}')
                ->middleware([
                    'web',
                    InitializeTenancyByPath::class,
                    ScopeSessions::class,
                    ConfigureTenantHost::class,
                ])
                ->group(base_path('routes/tenant.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Cloudflare + VDS: origin'e trafik yalnizca Cloudflare uzerinden
        // gelir. Bu olmadan $request->ip() Cloudflare'in IP'sini doner
        // (oran sinirlama ve giris throttle'i coker), isSecure() false kalir
        // ve url() http:// uretir.
        //
        // ⚠️ '*' guvenlidir ANCAK origin sunucusu yalnizca Cloudflare IP
        // araliklarina acik oldugunda. VDS guvenlik duvari 80/443'u herkese
        // acarsa X-Forwarded-For sahtelenebilir hale gelir.
        // LITERAL, bilerek: bu closure config servisi baglanmadan once calisir
        // (config() burada patlar) ve env() config cache'lendiginde null doner.
        // Ikisi de sessiz uretim hatasi uretir.
        //
        // '*' guvenlidir ANCAK origin sunucusu YALNIZCA Cloudflare IP
        // araliklarina acikken. VDS guvenlik duvarinda 80/443'u herkese acarsan
        // X-Forwarded-For sahtelenebilir hale gelir — Cloudflare kurulumlarinin
        // en yaygin acigi budur.
        //
        // Daraltmak gerekirse: AppServiceProvider::boot() icinde
        // TrustProxies::at(config('app.trusted_proxies')) — orada config hazir.
        $middleware->trustProxies(at: '*');

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Panel route gruplarina secici olarak uygulanir. Tenant grubunun
        // TAMAMINA eklenmez: login/logout/sifre ve faturalama disarida kalmali,
        // yoksa suresi dolmus musteri odeme yapmak icin giris bile yapamaz.
        $middleware->alias(['license' => EnsureLicenseIsActive::class]);

        // Octane/RoadRunner sizinti korumasi — BACKEND-PLAN.md §2.3.
        // stancl/tenancy istek sonunda tenancy()->end() cagirmaz.
        $middleware->append(EndTenancyAfterRequest::class);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Esikler tam tarih esledigi icin gunde bir kez yeterli; tekrar tetiklenmez.
        $schedule->command('licenses:check-expiring')->dailyAt('09:00');

        // Motorun kalp atisi. Tenant basina kadans LISANSTAN gelir
        // (licenses.limits['sync.interval_minutes']), zamanlamadan degil —
        // ucuz plan ikinci bir schedule satiri olmadan daha seyrek senkron olur.
        $schedule->command('sync:pull')->everyMinute()->withoutOverlapping();

        // Outbox'in altindaki emniyet agi: EnqueueOperation zaten drenaj
        // dispatch eder, bu yalnizca o yolun kaybettigi satirlari toplar.
        $schedule->command('sync:drain')->everyMinute()->withoutOverlapping();

        // Snapshot olmadan Horizon metrik ekrani bos kalir.
        $schedule->command('horizon:snapshot')->everyFiveMinutes();

        // webhook_events ve sync_runs MassPrunable; partitioning bilerek
        // ertelendi (BACKEND-PLAN.md §5.4).
        $schedule->command('model:prune', [
            '--model' => [SyncRun::class, WebhookEvent::class],
        ])->daily();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
