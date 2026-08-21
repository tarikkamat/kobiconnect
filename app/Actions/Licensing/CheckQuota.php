<?php

declare(strict_types=1);

namespace App\Actions\Licensing;

use App\Events\QuotaExceeded;
use App\Events\QuotaWarning;
use App\Models\License;
use App\Models\UsageCounter;

/**
 * Kota kapisi — §3.2. Middleware "lisans aktif mi" der; "bir urun daha
 * ekleyemezsin" karari burada verilir, cunku anlamli hata mesajini ancak
 * cagiran Action uretebilir.
 *
 * Kontrol eder *ve* basarili ise sayaci artirir; boylece cagiran tarafin
 * sayac guncellemeyi unutma ihtimali kalmaz. Islem sonradan geri alinirsa
 * `UsageCounter::record($tenantId, $metric, -1)` ile dusurulur.
 */
final class CheckQuota
{
    /**
     * QuotaWarning esigi (§3.3 — %80).
     */
    private const WARNING_RATIO = 0.8;

    /**
     * Hata mesajinda kullanilan metrik adlari.
     *
     * @var array<string, string>
     */
    private const LABELS = [
        'products.max' => 'ürün',
        'orders.per_month' => 'aylık sipariş',
        'channels.max' => 'pazaryeri kanalı',
        'seats.max' => 'kullanıcı',
    ];

    /**
     * @return int Metrigin yeni degeri.
     */
    public function __invoke(License $license, string $metric, int $amount = 1): int
    {
        $limit = $license->limit($metric);
        $current = UsageCounter::valueFor($license->tenant_id, $metric);

        if ($limit !== null && $current + $amount > $limit) {
            QuotaExceeded::dispatch($license, [
                'metric' => $metric,
                'limit' => $limit,
                'usage' => $current,
                'requested' => $amount,
            ]);

            abort(402, $this->message($license, $metric, $current, $limit));
        }

        $value = UsageCounter::record($license->tenant_id, $metric, $amount);

        if ($limit !== null && $this->crossedWarningThreshold($current, $value, $limit)) {
            QuotaWarning::dispatch($license, [
                'metric' => $metric,
                'limit' => $limit,
                'usage' => $value,
            ]);
        }

        return $value;
    }

    private function crossedWarningThreshold(int $before, int $after, int $limit): bool
    {
        $threshold = $limit * self::WARNING_RATIO;

        return $before < $threshold && $after >= $threshold;
    }

    private function message(License $license, string $metric, int $usage, int $limit): string
    {
        return sprintf(
            '%s planınızın %s kotası doldu (%d/%d). Devam etmek için planınızı yükseltin.',
            $license->plan->name,
            self::LABELS[$metric] ?? $metric,
            $usage,
            $limit,
        );
    }
}
