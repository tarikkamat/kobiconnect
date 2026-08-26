<?php

declare(strict_types=1);

namespace App\Queries\Reports;

use App\Queries\OrderTotals;
use App\Support\Money;

/**
 * Dort rapor ekraninin ustunde duran KPI seridi: ciro, kesintiler, net kazanc.
 */
final class SalesSummary extends ReportQuery
{
    /**
     * @return array<string, mixed>
     */
    public function get(): array
    {
        $orders = $this->orders()
            ->selectRaw('COUNT(*) as order_count')
            ->selectRaw(OrderTotals::SHIPPING_COST_SUM.' as shipping_total')
            ->selectRaw(OrderTotals::CARGO_PENALTY_SUM.' as cargo_penalty_total')
            ->selectRaw(OrderTotals::LATE_PENALTY_SUM.' as late_penalty_total')
            ->first();

        $lines = $this->orderLines()
            ->selectRaw(self::ITEM_COUNT.' as item_count')
            ->selectRaw(self::GROSS_SALES.' as gross_sales')
            ->selectRaw(self::COMMISSION_TOTAL.' as commission_total')
            ->first();

        $orderCount = (int) ($orders->order_count ?? 0);
        $shippingTotal = (float) ($orders->shipping_total ?? 0.0);
        $cargoPenaltyTotal = (float) ($orders->cargo_penalty_total ?? 0.0);
        $latePenaltyTotal = (float) ($orders->late_penalty_total ?? 0.0);

        $itemCount = (int) ($lines->item_count ?? 0);
        $grossSales = (float) ($lines->gross_sales ?? 0.0);
        $commissionTotal = (float) ($lines->commission_total ?? 0.0);

        $totalPenalties = $cargoPenaltyTotal + $latePenaltyTotal;
        $totalDeductions = $commissionTotal + $shippingTotal + $totalPenalties;
        $netEarnings = max(0.0, $grossSales - $totalDeductions);

        return [
            'rawGrossSales' => $grossSales,
            'grossSales' => Money::format($grossSales),
            'commissionTotal' => Money::format($commissionTotal),
            'rawCommissionTotal' => $commissionTotal,
            'shippingTotal' => Money::format($shippingTotal),
            'rawShippingTotal' => $shippingTotal,
            'cargoPenaltyTotal' => Money::format($cargoPenaltyTotal),
            'rawCargoPenaltyTotal' => $cargoPenaltyTotal,
            'latePenaltyTotal' => Money::format($latePenaltyTotal),
            'rawLatePenaltyTotal' => $latePenaltyTotal,
            'totalPenalties' => Money::format($totalPenalties),
            'rawTotalPenalties' => $totalPenalties,
            'totalDeductions' => Money::format($totalDeductions),
            'rawTotalDeductions' => $totalDeductions,
            'netEarnings' => Money::format($netEarnings),
            'rawNetEarnings' => $netEarnings,
            'orderCount' => $orderCount,
            'itemCount' => $itemCount,
            'avgOrderValue' => Money::format($orderCount > 0 ? $grossSales / $orderCount : 0.0),
            'avgCommissionRate' => Money::percent($grossSales > 0 ? ($commissionTotal / $grossSales) * 100.0 : 0.0),
        ];
    }
}
