<?php

declare(strict_types=1);

namespace App\Queries\Reports;

use App\Support\Money;
use Illuminate\Database\Query\Builder;
use stdClass;

/**
 * SKU bazinda satis performansi. Katalog eslesmesi aranmaz: eslesmemis bir
 * satir da satistir ve raporda gorunmelidir.
 */
final class TopProducts extends ReportQuery
{
    private const int LIMIT = 100;

    /**
     * @return list<array<string, mixed>>
     */
    public function get(?string $search = null): array
    {
        $term = $search === null ? '' : mb_strtolower(trim($search));

        $rows = $this->orderLines()
            ->when($term !== '', fn (Builder $query): Builder => $query->where(
                fn (Builder $inner) => $inner
                    ->whereRaw('LOWER(order_lines.sku) LIKE ?', ["%{$term}%"])
                    ->orWhereRaw('LOWER(order_lines.barcode) LIKE ?', ["%{$term}%"]),
            ))
            ->groupBy(['order_lines.sku', 'order_lines.barcode'])
            ->selectRaw('order_lines.sku, order_lines.barcode')
            ->selectRaw(self::ITEM_COUNT.' as quantity_sold')
            ->selectRaw(self::GROSS_SALES.' as gross_sales')
            ->selectRaw(self::COMMISSION_TOTAL.' as commission_total')
            ->orderByDesc('gross_sales')
            ->limit(self::LIMIT)
            ->get();

        return array_values($rows->map(static function (stdClass $row): array {
            $gross = (float) $row->gross_sales;
            $commission = (float) $row->commission_total;

            return [
                'sku' => (string) ($row->sku ?: 'Bilinmiyor'),
                'barcode' => $row->barcode ? (string) $row->barcode : null,
                'quantitySold' => (int) $row->quantity_sold,
                'grossSales' => Money::format($gross),
                'rawGrossSales' => $gross,
                'commissionTotal' => Money::format($commission),
                'rawCommissionTotal' => $commission,
                'netEarnings' => Money::format($gross - $commission),
                'rawNetEarnings' => $gross - $commission,
            ];
        })->all());
    }
}
