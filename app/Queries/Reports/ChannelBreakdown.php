<?php

declare(strict_types=1);

namespace App\Queries\Reports;

use App\Queries\OrderTotals;
use App\Support\Money;
use stdClass;

/**
 * Kanal basina ciro, kesinti ve pay dagilimi.
 *
 * Siparis ve satir toplamlari iki ayri gruplu sorgudur ve oyle kalmalidir:
 * tek sorguda birlestirmek siparis basina jsonb toplamlarini satir sayisi
 * kadar cogaltir.
 */
final class ChannelBreakdown extends ReportQuery
{
    /**
     * @param  float  $totalGrossSales  pay yuzdesinin paydasi — SalesSummary'den
     * @return list<array<string, mixed>>
     */
    public function get(float $totalGrossSales): array
    {
        $perOrder = $this->orders()
            ->join('channel_connections', 'channel_connections.id', '=', 'orders.connection_id')
            ->groupBy(['orders.connection_id', 'channel_connections.name', 'channel_connections.marketplace'])
            ->selectRaw('orders.connection_id, channel_connections.name, channel_connections.marketplace')
            ->selectRaw('COUNT(*) as order_count')
            ->selectRaw(OrderTotals::SHIPPING_COST_SUM.' as shipping_total')
            ->selectRaw(OrderTotals::PENALTY_SUM.' as penalty_total')
            ->get();

        $perLine = $this->orderLines()
            ->groupBy('orders.connection_id')
            ->selectRaw('orders.connection_id')
            ->selectRaw(self::ITEM_COUNT.' as item_count')
            ->selectRaw(self::GROSS_SALES.' as gross_sales')
            ->selectRaw(self::COMMISSION_TOTAL.' as commission_total')
            ->get()
            ->keyBy('connection_id');

        $rows = $perOrder->map(function (stdClass $row) use ($perLine, $totalGrossSales): array {
            $lines = $perLine->get($row->connection_id);

            $gross = (float) ($lines->gross_sales ?? 0.0);
            $commission = (float) ($lines->commission_total ?? 0.0);
            $shipping = (float) $row->shipping_total;
            $penalty = (float) $row->penalty_total;

            $deductions = $commission + $shipping + $penalty;
            $net = max(0.0, $gross - $deductions);
            $share = $totalGrossSales > 0 ? ($gross / $totalGrossSales) * 100.0 : 0.0;

            return [
                'id' => (int) $row->connection_id,
                'name' => (string) $row->name,
                'marketplace' => (string) $row->marketplace,
                'orderCount' => (int) $row->order_count,
                'itemCount' => (int) ($lines->item_count ?? 0),
                'grossSales' => Money::format($gross),
                'rawGrossSales' => $gross,
                'commissionTotal' => Money::format($commission),
                'rawCommissionTotal' => $commission,
                'shippingTotal' => Money::format($shipping),
                'rawShippingTotal' => $shipping,
                'penaltyTotal' => Money::format($penalty),
                'rawPenaltyTotal' => $penalty,
                'totalDeductions' => Money::format($deductions),
                'rawTotalDeductions' => $deductions,
                'netEarnings' => Money::format($net),
                'rawNetEarnings' => $net,
                'avgCommissionRate' => Money::percent($gross > 0 ? ($commission / $gross) * 100.0 : 0.0),
                'sharePercentage' => Money::percent($share),
                'rawShare' => $share,
            ];
        })->all();

        usort($rows, static fn (array $a, array $b): int => $b['rawGrossSales'] <=> $a['rawGrossSales']);

        return $rows;
    }
}
