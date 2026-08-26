<?php

use App\Http\Middleware\EndTenancyAfterRequest;
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
use Inertia\Inertia;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;
use Stancl\Tenancy\Middleware\ScopeSessions;
use Symfony\Component\HttpFoundation\Response;

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

        // Token ucuna istemci SUNUCUSU gelir; CSRF token'i uretecek bir sayfa
        // yoktur ve kimlik zaten PKCE dogrulayicisindan gelir. Passport'un
        // route grubu `web` tasidigi icin (config/passport.php) muafiyet
        // olmadan her token takasi 419 donerdi.
        //
        // `POST {tenant}/oauth/authorize` (onay formu) BILEREK disarida: o
        // gercek bir tarayici formudur ve CSRF korumasi altinda kalmali.
        // {tenant}/mcp ve {tenant}/oauth/register `web` grubunda degil, o
        // yuzden listede yoklar.
        $middleware->validateCsrfTokens(except: ['*/oauth/token']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Octane/RoadRunner sizinti korumasi — BACKEND-PLAN.md §2.3.
        // stancl/tenancy istek sonunda tenancy()->end() cagirmaz.
        $middleware->append(EndTenancyAfterRequest::class);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Motorun kalp atisi. Kadans komutun kendi araligindan gelir
        // (SyncCommand::DEFAULT_INTERVAL_MINUTES), zamanlamadan degil.
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

        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return $response;
            }

            $statusCode = $response->getStatusCode();

            if (! in_array($statusCode, [500, 503, 404, 403, 401, 419, 429], true)) {
                return $response;
            }

            if (app()->hasDebugModeEnabled() && $statusCode >= 500) {
                return $response;
            }

            return Inertia::render('error', [
                'status' => $statusCode,
            ])
                ->toResponse($request)
                ->setStatusCode($statusCode);
        });
    })->create();
