<?php

declare(strict_types=1);

namespace App\Support;

use App\Marketplaces\Contracts\MarketplaceDriver;
use App\Marketplaces\Support\Capability;
use App\Marketplaces\Support\Exceptions\MarketplaceException;
use App\Marketplaces\Support\MarketplaceManager;
use App\Models\License;
use Illuminate\Support\Arr;

/**
 * Uygulama magazasinin vitrini — `config/apps.php` ile surucu kaydini
 * birlestirir.
 *
 * Uc soruyu TEK yerde cevaplar:
 *   - Bu uygulama var mi?      -> katalogda tanimli mi
 *   - Kurulabilir mi?          -> surucusu kayitli mi (yoksa "Yakinda")
 *   - Musteri kurabilir mi?    -> lisansi izin veriyor mu (entitled)
 *
 * Bu sinif tekil (singleton) olarak baglanmaz: lisans okumasi istek basinadir
 * ve Octane surecinde tenant'lar arasi sizmamalidir.
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

    private ?License $license = null;

    private bool $licenseLoaded = false;

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
     * Musterinin lisansi bu uygulamaya izin veriyor mu?
     *
     * ponytail: bugun tek kaynak plan limitleridir. `channels.allowed` YOKSA
     * kisitlama da yok — License::limit() ile ayni konvansiyon ("anahtar yoksa
     * limitsiz"), boylece eski lisanslar kilitlenmez.
     *
     * App basina ucretlendirmeye gecildiginde degisecek TEK yer burasidir:
     * once tenant'in app aboneligine bakilir, yoksa plan listesine dusulur.
     */
    public function entitled(string $code): bool
    {
        $allowed = $this->license()?->limits->get('channels.allowed');

        return ! is_array($allowed) || in_array($code, $allowed, true);
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

        $available = $this->isInstallable($code);
        $entitled = $this->entitled($code);

        return [
            'code' => $code,
            'name' => (string) $definition['name'],
            'category' => (string) $definition['category'],
            'categoryLabel' => $this->categories()[$definition['category']] ?? (string) $definition['category'],
            'summary' => (string) $definition['summary'],
            // Marka varliklari public/apps altinda durur; isim = uygulama kodu.
            'logo' => "/apps/{$code}.svg",
            'capabilities' => $this->capabilities($code),
            'available' => $available,
            'entitled' => $entitled,
            'price' => $this->price($code),
            'fields' => $this->credentialFields($code),
        ];
    }

    /**
     * Fiyat sunucuda bicimlenir — FRONTEND-PLAN §7. `null` = plana dahil.
     *
     * @return array{monthly: string, yearly: string}|null
     */
    private function price(string $code): ?array
    {
        $price = Arr::get($this->definitions(), "{$code}.price");

        if (! is_array($price)) {
            return null;
        }

        return [
            'monthly' => $this->money($price['monthly'] ?? 0).' / ay',
            'yearly' => $this->money($price['yearly'] ?? 0).' / yıl',
        ];
    }

    private function money(mixed $amount): string
    {
        return '₺'.number_format((float) $amount, 2, ',', '.');
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

    private function license(): ?License
    {
        if ($this->licenseLoaded) {
            return $this->license;
        }

        $this->licenseLoaded = true;
        $tenant = tenant();

        return $this->license = $tenant === null
            ? null
            : License::query()->where('tenant_id', $tenant->getTenantKey())->first();
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
