<?php

declare(strict_types=1);

namespace App\Concerns;

/**
 * Arayuz metni enum'un kendi sorumlulugudur.
 *
 * Etiketler daha once her tuketicide bir `const STATUS_LABELS` dizisiydi;
 * OrderController ile ReportController'daki iki kopya birebir aynidir ve
 * DashboardController'daki kopya `ConnectionStatus::label()` ile coktan
 * ayristi ('Bagli' / 'Aktif'). Tek kaynak o yuzden burada.
 *
 * Kullanan enum `label()` yazar; secenek listesi ve ham deger cevirisi
 * buradan gelir.
 */
trait HasLabels
{
    /**
     * Filtre ve secim kutulari icin, enum sirasinda.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }

    /**
     * Veritabanindan ham string olarak gelen durum icin etiket. Taninmayan
     * deger oldugu gibi doner — sessizce bir duruma katlanmaz.
     */
    public static function labelFor(?string $value): string
    {
        return self::tryFrom((string) $value)?->label() ?? (string) $value;
    }
}
