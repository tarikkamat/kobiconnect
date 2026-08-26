<?php

declare(strict_types=1);

namespace App\Queries\Reports;

use App\Marketplaces\Data\Enums\CanonicalOrderStatus;
use App\Support\Money;
use stdClass;

/**
 * Kanonik statu dagilimi. Etiket enum'dan gelir; taninmayan bir statu ham
 * hâliyle gosterilir, bir varsayilana katlanmaz.
 */
final class StatusDistribution extends ReportQuery
{
    /**
     * @return list<array<string, mixed>>
     */
    public function get(): array
    {
        $rows = $this->orders()
            ->groupBy('orders.status')
            ->selectRaw('orders.status, COUNT(*) as count')
            ->orderByDesc('count')
            ->get();

        $total = (int) $rows->sum('count');

        return array_values($rows->map(static function (stdClass $row) use ($total): array {
            $share = $total > 0 ? ((int) $row->count / $total) * 100.0 : 0.0;

            return [
                'status' => (string) $row->status,
                'label' => CanonicalOrderStatus::labelFor((string) $row->status),
                'count' => (int) $row->count,
                'percentage' => $total > 0 ? Money::percent($share) : '0.0',
                'rawPercentage' => $share,
            ];
        })->all());
    }
}
