<?php

declare(strict_types=1);

namespace App\Http\Controllers\Channels;

use App\Http\Controllers\Controller;
use App\Marketplaces\Support\Exceptions\MarketplaceException;
use App\Marketplaces\Support\MarketplaceManager;
use App\Models\ChannelConnection;
use App\Support\AppCatalog;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Uygulama magazasi — tek ekran: vitrin + kurulu baglantilar.
 *
 * Vitrin `config/apps.php` + surucu kaydindan gelir (App\Support\AppCatalog).
 * Detay sayfasi BILEREK yok: karta tiklamak dogrudan kurulum cekmecesini acar.
 * Kimlik bilgileri BURADAN HIC CIKMAZ: arayuze yalnizca gizli olmayan alanlar
 * ve "kayitli" isareti doner.
 */
class AppStoreController extends Controller
{
    /**
     * Arayuz metinleri Turkce, kanonik enum degerleri degil — FRONTEND-PLAN §7.
     *
     * @var array<string, string>
     */
    private const array STATUS_LABELS = [
        'active' => 'Bağlı',
        'paused' => 'Duraklatıldı',
        'error' => 'Hatalı',
    ];

    public function __construct(
        private readonly AppCatalog $catalog,
        private readonly MarketplaceManager $marketplaces,
    ) {}

    public function index(): Response
    {
        Gate::authorize('viewAny', ChannelConnection::class);

        $installed = ChannelConnection::query()
            ->selectRaw('marketplace, count(*) as total')
            ->groupBy('marketplace')
            ->pluck('total', 'marketplace');

        return Inertia::render('channels/apps/index', [
            'apps' => array_map(
                static fn (array $app): array => [
                    ...$app,
                    'installed' => (int) ($installed[$app['code']] ?? 0),
                ],
                $this->catalog->all(),
            ),
            'connections' => ChannelConnection::query()
                ->orderBy('name')
                ->get()
                ->map($this->row(...))
                ->all(),
            'categories' => array_map(
                static fn (string $value, string $label): array => ['value' => $value, 'label' => $label],
                array_keys($this->catalog->categories()),
                array_values($this->catalog->categories()),
            ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(ChannelConnection $connection): array
    {
        return [
            'id' => $connection->getKey(),
            'name' => $connection->name,
            'marketplace' => $connection->marketplace,
            'marketplaceLabel' => $this->marketplaceLabel($connection->marketplace),
            'status' => $connection->status->value,
            'statusLabel' => self::STATUS_LABELS[$connection->status->value],
            'capabilities' => $this->capabilityBadges($connection),
            // Tarih sunucuda bicimlenir, Europe/Istanbul — FRONTEND-PLAN §7.
            'lastHealthCheckAt' => $connection->last_health_check_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i'),
            'lastHealthError' => $this->lastHealthError($connection),
            'webhookUrl' => $this->webhookUrl($connection),
            'sellerId' => (string) $connection->external_seller_id,
            'credentials' => $this->nonSecretCredentials($connection),
        ];
    }

    /**
     * Kimlik bilgilerinin GIZLI OLMAYAN kismi. `secret` tipindeki hicbir alan
     * prop'a girmez; formda bos birakilirsa kayitli degerleri korunur.
     * `secretsStored` yalnizca "kayitli bir sir var" isaretidir.
     *
     * @return array{values: array<string, bool|string>, secretsStored: bool}
     */
    private function nonSecretCredentials(ChannelConnection $connection): array
    {
        $stored = $connection->credentials->toArray();
        $values = [];
        $secretsStored = false;

        foreach ($this->credentialFields($connection->marketplace) as $field) {
            $value = $stored[$field['name']] ?? null;

            if ($field['type'] === 'secret') {
                $secretsStored = $secretsStored || (string) $value !== '';

                continue;
            }

            $values[$field['name']] = $field['type'] === 'checkbox'
                ? (bool) $value
                : (string) ($value ?? $field['default'] ?? '');
        }

        return ['values' => $values, 'secretsStored' => $secretsStored];
    }

    /**
     * @return list<array{name: string, label: string, type: string, rules: list<string>, help?: string, options?: list<string>, default?: string, identity?: bool}>
     */
    private function credentialFields(string $marketplace): array
    {
        try {
            return $this->marketplaces->driver($marketplace)->credentialFields();
        } catch (MarketplaceException) {
            return [];
        }
    }

    /**
     * Yetenekler `capabilities` anlik goruntusunden okunur; sutun bos ise
     * (kayit hic saglik kontrolunden gecmemisse) katalogtan turetilir.
     *
     * @return list<array{value: string, label: string}>
     */
    private function capabilityBadges(ChannelConnection $connection): array
    {
        $catalog = $this->catalog->find($connection->marketplace);
        $catalog = is_array($catalog) ? $catalog['capabilities'] : [];
        $snapshot = array_values(array_filter($connection->capabilities, is_string(...)));

        if ($snapshot === []) {
            return $catalog;
        }

        $labels = array_column($catalog, 'label', 'value');

        return array_map(
            static fn (string $value): array => [
                'value' => $value,
                'label' => $labels[$value] ?? $value,
            ],
            $snapshot,
        );
    }

    private function lastHealthError(ChannelConnection $connection): ?string
    {
        $error = $connection->settings['last_health_error'] ?? null;

        return is_string($error) && $error !== '' ? $error : null;
    }

    /**
     * ponytail: webhook ucu bu fazda YOK (BACKEND-PLAN.md §8.1 — Trendyol'a
     * kayit sonraki faz), URL yalnizca gosterilir ve kopyalanir. Uc eklendiginde
     * burasi `route()` olur; host `app.` yerine `hooks.` alt alan adidir.
     */
    private function webhookUrl(ChannelConnection $connection): string
    {
        // Taban config'ten gelir; host uzerinde string oynamasi yapilmaz
        // (APP_URL farkli bir host olursa o yaklasim saçmalar).
        return rtrim((string) config('marketplaces.webhook_base_url'), '/')
            ."/{$connection->marketplace}/{$connection->webhook_token}";
    }

    private function marketplaceLabel(string $marketplace): string
    {
        $app = $this->catalog->find($marketplace);

        return is_array($app) ? (string) $app['name'] : $marketplace;
    }
}
