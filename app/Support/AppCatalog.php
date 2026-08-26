<?php

declare(strict_types=1);

namespace App\Support;

use App\Marketplaces\Support\Capability;
use App\Marketplaces\Support\MarketplaceManager;

/**
 * Uygulama magazasinin vitrini — `config/apps.php` ile surucu kaydini
 * birlestirir.
 *
 * Iki soruyu TEK yerde cevaplar:
 *   - Bu uygulama var mi?  -> katalogda tanimli mi
 *   - Kurulabilir mi?      -> surucusu kayitli mi (yoksa "Yakinda")
 *
 * @phpstan-type CredentialField array{
 *     name: string,
 *     label: string,
 *     type: 'text'|'secret'|'select'|'checkbox',
 *     help?: string,
 *     options?: list<string>,
 *     default?: string,
 *     identity?: bool,
 * }
 * @phpstan-type AppCard array{
 *     code: string,
 *     name: string,
 *     category: string,
 *     categoryLabel: string,
 *     logo: string,
 *     logoScale: float,
 *     logoDarkInvert: bool,
 *     capabilities: list<array{value: string, label: string}>,
 *     available: bool,
 *     fields: list<CredentialField>,
 * }
 */
final class AppCatalog
{
    public function __construct(private readonly MarketplaceManager $marketplaces) {}

    /**
     * Vitrindeki tum uygulamalar. Sira: once kurulabilenler, sonra alfabetik —
     * "Yakinda" kartlari listenin basini isgal etmez.
     *
     * @return list<AppCard>
     */
    public function all(): array
    {
        $apps = array_map($this->present(...), array_keys($this->definitions()));

        usort($apps, static fn (array $a, array $b): int => [! $a['available'], $a['name']] <=> [! $b['available'], $b['name']]);

        return $apps;
    }

    /**
     * @return AppCard|null
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
        return $this->marketplaces->tryDriver($code) !== null;
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
     * @return AppCard
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
        $driver = $this->marketplaces->tryDriver($code);

        if ($driver === null) {
            return [];
        }

        return array_map(
            static fn (Capability $capability): array => [
                'value' => $capability->value,
                'label' => $capability->label(),
            ],
            $driver->capabilities(),
        );
    }

    /**
     * Kimlik formu surucunun bildirimidir; `rules` sunucuda kalir.
     *
     * @return list<CredentialField>
     */
    private function credentialFields(string $code): array
    {
        $driver = $this->marketplaces->tryDriver($code);

        if ($driver === null) {
            return [];
        }

        $fields = [];

        foreach ($driver->credentialFields() as $field) {
            unset($field['rules']);
            $fields[] = $field;
        }

        return $fields;
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
