<?php

declare(strict_types=1);

namespace App\Support;

use App\Marketplaces\Contracts\MarketplaceDriver;
use App\Marketplaces\Support\Capability;
use App\Marketplaces\Support\Exceptions\MarketplaceException;
use App\Marketplaces\Support\MarketplaceManager;
use Illuminate\Support\Arr;

/**
 * Uygulama magazasinin vitrini — `config/apps.php` ile surucu kaydini
 * birlestirir.
 *
 * Iki soruyu TEK yerde cevaplar:
 *   - Bu uygulama var mi?  -> katalogda tanimli mi
 *   - Kurulabilir mi?      -> surucusu kayitli mi (yoksa "Yakinda")
 */
final class AppCatalog
{
    /**
     * Arayuz metinleri Turkce, kanonik enum degerleri degil — FRONTEND-PLAN §7.
     *
     * @var array<string, string>
     */
    private const array CAPABILITY_LABELS = [
        'product_sync' => 'Ürün',
        'inventory_sync' => 'Stok',
        'price_sync' => 'Fiyat',
        'order_sync' => 'Sipariş',
        'shipment_updates' => 'Kargo',
        'claims' => 'İade',
        'questions' => 'Soru-Cevap',
        'catalog_matching' => 'Ürün eşleştirme',
        'category_catalog' => 'Kategori kataloğu',
        'brand_catalog' => 'Marka kataloğu',
        'webhooks' => 'Webhook',
    ];

    public function __construct(private readonly MarketplaceManager $marketplaces) {}

    /**
     * Vitrindeki tum uygulamalar. Sira: once kurulabilenler, sonra alfabetik —
     * "Yakinda" kartlari listenin basini isgal etmez.
     *
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $apps = array_map($this->present(...), array_keys($this->definitions()));

        usort($apps, static fn (array $a, array $b): int => [! $a['available'], $a['name']] <=> [! $b['available'], $b['name']]);

        return $apps;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $code): ?array
    {
        return isset($this->definitions()[$code]) ? $this->present($code) : null;
    }

    /**
     * Kurulabilir mi — surucusu kayitli mi. Katalogda olup surucusu olmayan
     * uygulama vitrinde durur ama kurulamaz.
     */
    public function isInstallable(string $code): bool
    {
        return $this->driver($code) !== null;
    }

    /**
     * @return array<string, string>
     */
    public function categories(): array
    {
        $categories = config('apps.categories');

        return is_array($categories) ? array_map(strval(...), $categories) : [];
    }

    /**
     * Vitrin karti. Kurulu baglanti sayisi BURADA YOK: o istek basina degisen
     * veridir ve cagiran tarafta birlestirilir.
     *
     * @return array<string, mixed>
     */
    private function present(string $code): array
    {
        /** @var array<string, mixed> $definition */
        $definition = $this->definitions()[$code];

        return [
            'code' => $code,
            'name' => (string) $definition['name'],
            'category' => (string) $definition['category'],
            'categoryLabel' => $this->categories()[$definition['category']] ?? (string) $definition['category'],
            // Marka varliklari public/apps altinda durur; isim = uygulama kodu.
            'logo' => "/apps/{$code}.svg",
            // Bazi markalar kendi tuvalinde kucuk cizilmis — bkz. config/apps.php.
            'logoScale' => (float) ($definition['logo_scale'] ?? 1),
            // Koyu wordmark'lar karanlik temada beyaza boyanir — bkz. config/apps.php.
            'logoDarkInvert' => (bool) ($definition['logo_dark_invert'] ?? false),
            'capabilities' => $this->capabilities($code),
            'available' => $this->isInstallable($code),
            'fields' => $this->credentialFields($code),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function capabilities(string $code): array
    {
        $driver = $this->driver($code);

        if ($driver === null) {
            return [];
        }

        return array_map(
            static fn (Capability $capability): array => [
                'value' => $capability->value,
                'label' => self::CAPABILITY_LABELS[$capability->value] ?? $capability->value,
            ],
            $driver->capabilities(),
        );
    }

    /**
     * Kimlik formu surucunun bildirimidir; `rules` sunucuda kalir.
     *
     * @return list<array<string, mixed>>
     */
    private function credentialFields(string $code): array
    {
        $driver = $this->driver($code);

        if ($driver === null) {
            return [];
        }

        return array_map(
            static fn (array $field): array => Arr::except($field, ['rules']),
            $driver->credentialFields(),
        );
    }

    private function driver(string $code): ?MarketplaceDriver
    {
        try {
            return $this->marketplaces->driver($code);
        } catch (MarketplaceException) {
            return null;
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function definitions(): array
    {
        $apps = config('apps.apps');

        return is_array($apps) ? $apps : [];
    }
}
