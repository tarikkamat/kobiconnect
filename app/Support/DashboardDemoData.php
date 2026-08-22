<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;

/**
 * Gösterge panelindeki GRAFIKLERIN ornek verisi — MVP sunumu icin.
 *
 * Uretimin tamami burada; `DashboardController` yalnizca cagirir. Gercek
 * sorgulara gecerken bu dosya silinir ve controller'daki alti `defer()` satiri
 * gercek metoda baglanir; frontend'e dokunulmaz cunku sozlesme (series/rows)
 * ayni kalir.
 *
 * Sayilar DETERMINISTIK: `crc32(seri + gun)` uzerinden turetilir. `rand`/`fake`
 * kullanilmaz — hem sunumda her yenilemede grafik ziplamasin, hem de Octane'de
 * global rastgelelik durumu istekler arasinda sizmasin.
 *
 * Butun grafikler TEK bir gunluk matristen beslenir: pasta grafigindeki kanal
 * payi, trend grafigindeki alanlarin toplamiyla birebir tutar.
 */
final class DashboardDemoData
{
    /** Baglantisi olmayan tenant'ta vitrin bos kalmasin. */
    private const array FALLBACK_MARKETPLACES = ['trendyol', 'hepsiburada'];

    /** Donemden bagimsiz grafiklerin (spark, senkron isi) sabit penceresi. */
    private const int DAYS = 90;

    private const string TIMEZONE = 'Europe/Istanbul';

    /** Kanal basina ortalama sepet (TRY); index buyudukce kucuk oyuncu. */
    private const array BASKETS = [420, 310, 260, 195, 168];

    /** @var list<array{key: string, label: string, logo: string}>|null */
    private ?array $series = null;

    /** @var list<array<string, mixed>>|null */
    private ?array $matrix = null;

    private ?CarbonImmutable $from = null;

    private ?CarbonImmutable $to = null;

    public function __construct(private readonly AppCatalog $catalog) {}

    /**
     * Panelin seçili dönemi. Satış grafiklerinin (trend, pay, hacim, KPI)
     * penceresi budur; verilmezse son 30 gün. Hedef ve senkron işi bilerek
     * dönemden bağımsızdır: biri takvim ayına, öteki son 12 haftaya bakar.
     */
    public function forRange(CarbonImmutable $from, CarbonImmutable $to): void
    {
        $this->from = $from->setTimezone(self::TIMEZONE)->startOfDay();
        $this->to = $to->setTimezone(self::TIMEZONE)->startOfDay();
        $this->matrix = null;
    }

    private function from(): CarbonImmutable
    {
        return $this->from ??= $this->today()->subDays(29);
    }

    private function to(): CarbonImmutable
    {
        return $this->to ??= $this->today();
    }

    /** Dönemin gün sayısı — KPI karşılaştırma penceresi de bu uzunluktadır. */
    private function rangeLength(): int
    {
        return (int) $this->from()->diffInDays($this->to()) + 1;
    }

    /**
     * Matrisin seçili döneme düşen günleri.
     *
     * @return list<array{date: string, channels: array<string, array{orders: int, revenue: float, returns: int}>}>
     */
    private function rangeDays(): array
    {
        return $this->daysBetween($this->from(), $this->to());
    }

    /**
     * @return list<array{date: string, channels: array<string, array{orders: int, revenue: float, returns: int}>}>
     */
    private function daysBetween(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return array_values(array_filter(
            $this->matrix(),
            fn (array $day): bool => $day['date'] >= $from->toDateString() && $day['date'] <= $to->toDateString(),
        ));
    }

    /**
     * Ust seritteki dort ozet. Delta = secili donem / onceki esit uzunlukta donem.
     *
     * @return array{demo: bool, items: list<array<string, mixed>>}
     */
    public function kpis(): array
    {
        $current = $this->totals($this->rangeDays());
        $previous = $this->totals($this->daysBetween(
            $this->from()->subDays($this->rangeLength()),
            $this->from()->subDay(),
        ));

        $basket = $current['orders'] > 0 ? $current['revenue'] / $current['orders'] : 0.0;
        $previousBasket = $previous['orders'] > 0 ? $previous['revenue'] / $previous['orders'] : 0.0;

        $returnRate = $current['orders'] > 0 ? $current['returns'] / $current['orders'] * 100 : 0.0;
        $previousReturnRate = $previous['orders'] > 0 ? $previous['returns'] / $previous['orders'] * 100 : 0.0;

        return [
            'demo' => true,
            'items' => [
                $this->kpi('revenue', 'Ciro', $this->money($current['revenue']), $current['revenue'], $previous['revenue'], 'revenue'),
                $this->kpi('orders', 'Sipariş', Number::format($current['orders'], locale: 'tr'), (float) $current['orders'], (float) $previous['orders'], 'orders'),
                $this->kpi('basket', 'Ortalama sepet', $this->money($basket), $basket, $previousBasket, 'revenue'),
                // Iadede ARTIS kotudur; yon bilerek ters cevrilir.
                $this->kpi('returns', 'İade oranı', $this->percent($returnRate), $returnRate, $previousReturnRate, 'returns', inverted: true),
            ],
        ];
    }

    /**
     * Secili donemin gunluk cirosu, pazaryeri basina. Donem sunucuda kesilir;
     * secici degisince Inertia zaten yeni istek atiyor.
     *
     * @return array{demo: bool, series: list<array<string, string>>, rows: list<array<string, mixed>>}
     */
    public function salesTrend(): array
    {
        return [
            'demo' => true,
            'series' => $this->series(),
            'rows' => array_map(
                fn (array $day): array => ['date' => $day['date']] + array_map(
                    fn (array $channel): float => round($channel['revenue'], 2),
                    $day['channels'],
                ),
                $this->rangeDays(),
            ),
        ];
    }

    /**
     * Secili donemin ciro payi — pasta.
     *
     * @return array{demo: bool, total: string, items: list<array<string, mixed>>}
     */
    public function channelShare(): array
    {
        $days = $this->rangeDays();
        $totals = [];

        foreach ($days as $day) {
            foreach ($day['channels'] as $key => $channel) {
                $totals[$key] = ($totals[$key] ?? 0.0) + $channel['revenue'];
            }
        }

        $grand = array_sum($totals);

        return [
            'demo' => true,
            'total' => $this->money($grand),
            'items' => array_map(fn (array $serie): array => [
                ...$serie,
                'value' => round($totals[$serie['key']] ?? 0.0, 2),
                'formatted' => $this->money($totals[$serie['key']] ?? 0.0),
                'share' => $grand > 0.0 ? $this->percent(($totals[$serie['key']] ?? 0.0) / $grand * 100) : $this->percent(0),
            ], $this->series()),
        ];
    }

    /**
     * Gunluk hacim: adet / ciro / iade. Ustteki uc butonla degisen tek bar.
     *
     * @return array{demo: bool, totals: array<string, string>, rows: list<array<string, mixed>>}
     */
    public function orderVolume(): array
    {
        $rows = array_map(fn (array $day): array => [
            'date' => $day['date'],
            'orders' => array_sum(array_column($day['channels'], 'orders')),
            'revenue' => round(array_sum(array_column($day['channels'], 'revenue')), 2),
            'returns' => array_sum(array_column($day['channels'], 'returns')),
        ], $this->rangeDays());

        return [
            'demo' => true,
            'totals' => [
                'orders' => Number::format((int) array_sum(array_column($rows, 'orders')), locale: 'tr'),
                'revenue' => $this->money((float) array_sum(array_column($rows, 'revenue'))),
                'returns' => Number::format((int) array_sum(array_column($rows, 'returns')), locale: 'tr'),
            ],
            'rows' => $rows,
        ];
    }

    /**
     * Bu ayin hedef gerceklesmesi, pazaryeri basina. Hedef, gecen ayin
     * cirosunun %15 uzerine "yuvarlak" bir sayi olarak kurulur.
     *
     * @return array{demo: bool, month: string, overall: array<string, mixed>, items: list<array<string, mixed>>}
     */
    public function salesTarget(): array
    {
        $month = $this->today()->startOfMonth()->toDateString();
        $achieved = [];
        $reference = [];

        foreach ($this->matrix() as $day) {
            $inMonth = $day['date'] >= $month;

            foreach ($day['channels'] as $key => $channel) {
                $achieved[$key] = ($achieved[$key] ?? 0.0) + ($inMonth ? $channel['revenue'] : 0.0);
                $reference[$key] = ($reference[$key] ?? 0.0) + (! $inMonth ? $channel['revenue'] : 0.0);
            }
        }

        $items = array_map(function (array $serie) use ($achieved, $reference): array {
            $target = $this->target($reference[$serie['key']] ?? 0.0);
            $done = $achieved[$serie['key']] ?? 0.0;

            return [
                ...$serie,
                'percent' => $target > 0.0 ? (int) round(min(100, $done / $target * 100)) : 0,
                'achieved' => $this->money($done),
                'target' => $this->money($target),
            ];
        }, $this->series());

        $targetSum = $this->target(array_sum($reference));
        $achievedSum = array_sum($achieved);

        return [
            'demo' => true,
            'month' => $this->today()->translatedFormat('F Y'),
            'overall' => [
                'percent' => $targetSum > 0.0 ? (int) round(min(100, $achievedSum / $targetSum * 100)) : 0,
                'achieved' => $this->money($achievedSum),
                'target' => $this->money($targetSum),
            ],
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function kpi(string $key, string $label, string $value, float $current, float $previous, string $tone, bool $inverted = false): array
    {
        $change = $previous > 0.0 ? ($current - $previous) / $previous * 100 : 0.0;
        $rising = $change >= 0.0;

        return [
            'key' => $key,
            'label' => $label,
            'value' => $value,
            'delta' => $this->percent(abs($change)),
            'rising' => $rising,
            // Yon iyi mi kotu mu: iade artisinda ok yukari ama renk kirmizi.
            'positive' => $inverted ? ! $rising : $rising,
            'spark' => $this->spark($tone),
        ];
    }

    /**
     * Kart icindeki mini cizgi — son 12 haftanin haftalik toplami.
     *
     * @return list<array{index: int, value: float}>
     */
    private function spark(string $metric): array
    {
        $spark = [];

        foreach (array_chunk($this->lastTwelveWeeks(), 7) as $index => $week) {
            $value = 0.0;

            foreach ($week as $day) {
                $value += match ($metric) {
                    'orders' => array_sum(array_column($day['channels'], 'orders')),
                    'returns' => array_sum(array_column($day['channels'], 'returns')),
                    default => array_sum(array_column($day['channels'], 'revenue')),
                };
            }

            $spark[] = ['index' => $index, 'value' => round($value, 2)];
        }

        return $spark;
    }

    /**
     * Bugunle biten sabit 84 gunluk pencere — spark ve senkron isi donem
     * seciminden etkilenmez.
     *
     * @return list<array{date: string, channels: array<string, array{orders: int, revenue: float, returns: int}>}>
     */
    private function lastTwelveWeeks(): array
    {
        return $this->daysBetween($this->today()->subDays(83), $this->today());
    }

    /**
     * @param  list<array{date: string, channels: array<string, array{orders: int, revenue: float, returns: int}>}>  $days
     * @return array{orders: int, revenue: float, returns: int}
     */
    private function totals(array $days): array
    {
        $totals = ['orders' => 0, 'revenue' => 0.0, 'returns' => 0];

        foreach ($days as $day) {
            foreach ($day['channels'] as $channel) {
                $totals['orders'] += $channel['orders'];
                $totals['revenue'] += $channel['revenue'];
                $totals['returns'] += $channel['returns'];
            }
        }

        return $totals;
    }

    /**
     * Gunluk matris — panelin TEK veri kaynagi.
     *
     * @return list<array{date: string, channels: array<string, array{orders: int, revenue: float, returns: int}>}>
     */
    private function matrix(): array
    {
        if ($this->matrix !== null) {
            return $this->matrix;
        }

        // Secili donem + KPI karsilastirmasi icin bir onceki esit pencere +
        // sabit pencereli grafiklerin 90 gunu; hangisi genisse o.
        $start = $this->from()->subDays($this->rangeLength())->min($this->today()->subDays(self::DAYS - 1));
        $end = $this->to()->max($this->today());
        $rows = [];

        for ($day = $start; $day->lessThanOrEqualTo($end); $day = $day->addDay()) {
            $age = (int) $day->diffInDays($end);
            $channels = [];

            foreach ($this->series() as $index => $serie) {
                $orders = $this->dailyOrders($serie['key'], $day, $index, $age);
                $basket = self::BASKETS[$index % count(self::BASKETS)];

                $channels[$serie['key']] = [
                    'orders' => $orders,
                    'revenue' => round($orders * $basket * (1 + (crc32('b'.$serie['key'].$day->toDateString()) % 21 - 10) / 100), 2),
                    'returns' => (int) round($orders * (4 + crc32('r'.$serie['key'].$day->toDateString()) % 5) / 100),
                ];
            }

            $rows[] = ['date' => $day->toDateString(), 'channels' => $channels];
        }

        return $this->matrix = $rows;
    }

    /**
     * Bir gunun siparis adedi: taban + belirlenimci gurultu + hafta sonu tepesi
     * + hafif buyume egilimi (en eski gun ~%20 daha dusuk).
     */
    private function dailyOrders(string $key, CarbonImmutable $day, int $index, int $age): int
    {
        $base = max(5, (int) round(48 * 0.62 ** $index));
        $spread = max(2, (int) round($base * 0.45));
        $noise = (int) (crc32($key.$day->toDateString()) % (2 * $spread + 1)) - $spread;
        $weekend = $day->isWeekend() ? (int) round($base * 0.28) : 0;
        $growth = (int) round($base * 0.2 * $age / self::DAYS);

        return max(1, $base + $noise + $weekend - $growth);
    }

    /**
     * Grafiklerdeki pazaryerleri: tenant'in gercek baglantilari, yoksa vitrin
     * varsayilani. Ad ve logo `AppCatalog`'dan gelir — TEK kaynak.
     *
     * @return list<array{key: string, label: string, logo: string}>
     */
    private function series(): array
    {
        if ($this->series !== null) {
            return $this->series;
        }

        $codes = DB::table('channel_connections')
            ->distinct()
            ->orderBy('marketplace')
            ->pluck('marketplace')
            ->map(strval(...))
            ->all();

        if ($codes === []) {
            $codes = self::FALLBACK_MARKETPLACES;
        }

        return $this->series = array_values(array_map(function (string $code): array {
            $app = $this->catalog->find($code);

            return [
                'key' => $code,
                'label' => is_array($app) ? (string) $app['name'] : $code,
                'logo' => is_array($app) ? (string) $app['logo'] : "/apps/{$code}.svg",
            ];
        }, array_slice($codes, 0, 5)));
    }

    /** Hedef: referans cironun %15 uzeri, on binlige yuvarli. */
    private function target(float $reference): float
    {
        // ponytail: iki aylik referansin yarisi = aylik taban.
        return max(10000.0, ceil($reference / 2 * 1.15 / 10000) * 10000);
    }

    /** Para ve yuzde SUNUCUDA bicimlenir — FRONTEND-PLAN §7. */
    private function money(float $amount): string
    {
        return (string) Number::currency($amount, 'TRY', 'tr');
    }

    private function percent(float $value): string
    {
        return (string) Number::percentage($value, 1, locale: 'tr');
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now(self::TIMEZONE)->startOfDay();
    }
}
