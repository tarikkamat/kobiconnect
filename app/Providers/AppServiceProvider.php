<?php

namespace App\Providers;

use App\Events\NotificationEventOccurred;
use App\Listeners\Notifications\SendEventNotification;
use App\Marketplaces\Support\MarketplaceManager;
use App\Models\ChannelListing;
use App\Models\InventoryItem;
use App\Models\Price;
use App\Models\User;
use App\Observers\ChannelListingObserver;
use App\Observers\InventoryItemObserver;
use App\Observers\PriceObserver;
use App\Support\TenantUserProvider;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Number;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Surucu cozumlemesi worker omru boyunca cache'lenir. Manager tenant
        // durumu TASIMAZ; tenant'a ozgu her sey cagri basina MappingContext ile
        // gecer (Octane guvenligi).
        $this->app->singleton(MarketplaceManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        // users tenant semasinda yasar; central istekte session'dan user
        // cozulmeye kalkilirsa "relation users does not exist" olur. Bkz.
        // App\Support\TenantUserProvider.
        Auth::provider('tenant-eloquent', fn ($app, array $config): TenantUserProvider => new TenantUserProvider($app['hash'], $config['model']));

        // MCP istemcisinin (Claude vb.) tarayicida gordugu onay ekrani. Paket
        // gorunumu vendor:publish --tag=mcp-views ile alindi.
        Passport::authorizationView(
            /** @param array<string, mixed> $parameters */
            fn (array $parameters): Response => response()->view('mcp.authorize', $parameters)
        );

        // ZORUNLU: Passport anahtarlari varsayilan olarak storage_path() ile
        // aranir, FilesystemTenancyBootstrapper ise o yolu tenant basina
        // soneklendirir (storage/tenant{id}/...). Sabitlemezsek her tenant
        // istegi "Invalid key supplied" ile 500 doner. Imza anahtari zaten
        // uygulamanin tamamina aittir, tenant'a degil.
        Passport::loadKeysFrom(base_path('storage'));

        // Horizon central domain'de yasar; orada tenant kullanicisi ve dolayisiyla
        // rol yoktur. Bu yuzden yetki rol degil operator listesiyle verilir.
        Gate::define('viewHorizon', fn (?User $user): bool => app()->isLocal()
            || in_array($user?->email, (array) config('horizon.operators', []), true));

        // Outbox tetikleyicileri. Modelde #[ObservedBy] yerine burada:
        // gozlemciler senkron motoruna aittir, katalog modeline degil.
        InventoryItem::observe(InventoryItemObserver::class);
        Price::observe(PriceObserver::class);
        ChannelListing::observe(ChannelListingObserver::class);

        Event::listen(NotificationEventOccurred::class, SendEventNotification::class);

        Date::use(CarbonImmutable::class);

        // Turkce urun: para ve sayi bicimlendirmesinin varsayilani. Sunucuda
        // bicimlendirip gonderiyoruz, iki yerde mantik tutmuyoruz.
        Number::useLocale('tr');
        Number::useCurrency('TRY');

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
