<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Kota olcumu — §3.1. Kotalar Action seviyesinde bu sayaclardan okunur.
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $metric
 * @property string $period
 * @property int $value
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable(['tenant_id', 'metric', 'period', 'value'])]
class UsageCounter extends Model
{
    use CentralConnection;

    /**
     * Metrigin donem anahtari: `.per_month` ile bitenler aylik, digerleri kumulatif.
     */
    public static function periodFor(string $metric): string
    {
        return str_ends_with($metric, '.per_month') ? now()->format('Y-m') : 'total';
    }

    /**
     * Metrigin gecerli donemdeki degeri.
     */
    public static function valueFor(string $tenantId, string $metric): int
    {
        return (int) static::query()
            ->where('tenant_id', $tenantId)
            ->where('metric', $metric)
            ->where('period', static::periodFor($metric))
            ->value('value');
    }

    /**
     * Sayaci artirir (negatif deger azaltir) ve yeni degeri dondurur.
     */
    public static function record(string $tenantId, string $metric, int $by = 1): int
    {
        $period = static::periodFor($metric);

        // ponytail: insertOrIgnore + increment. Iki ekstra sorgu ama yaris
        // kosulunda dogru; tek sorguya inmek icin ham ON CONFLICT gerekirdi.
        static::query()->insertOrIgnore([
            'tenant_id' => $tenantId,
            'metric' => $metric,
            'period' => $period,
            'value' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        static::query()
            ->where('tenant_id', $tenantId)
            ->where('metric', $metric)
            ->where('period', $period)
            ->increment('value', $by, ['updated_at' => now()]);

        return static::valueFor($tenantId, $metric);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['value' => 'integer'];
    }
}
