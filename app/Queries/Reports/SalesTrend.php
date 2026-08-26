<?php

declare(strict_types=1);

namespace App\Queries\Reports;

use App\Queries\OrderTotals;
use App\Support\AppTime;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Gunluk satis trendi. Satis olmayan gunler de dizide durur; grafik bosluk
 * yerine sifir gostermeli.
 */
final class SalesTrend extends ReportQuery
{
    /**
     * Gun siniri Europe/Istanbul'a gore alinir. `placed_at` naive timestamp
     * olarak UTC tutulur; ceviri yapilmadan `DATE()` almak aksam siparislerini
     * bir sonraki gune kaydiriyordu — dongunun bakti gun ile SQL'in urettigi
     * gun ayni olmak zorunda.
     */
    private const string LOCAL_DAY = "(orders.placed_at AT TIME ZONE 'UTC' AT TIME ZONE '".AppTime::ZONE."')::date";

    /**
     * @return list<array<string, mixed>>
     */
    public function get(): array
    {
        $perOrder = $this->orders()
            ->groupByRaw(self::LOCAL_DAY)
            ->selectRaw(self::LOCAL_DAY.' as day')
            ->selectRaw('COUNT(*) as order_count')
            ->selectRaw('('.OrderTotals::SHIPPING_COST_SUM.' + '.OrderTotals::PENALTY_SUM.') as shipping_and_penalty')
            ->get()
            ->keyBy(fn (object $row): string => mb_substr((string) $row->day, 0, 10));

        $perLine = $this->orderLines()
            ->groupByRaw(self::LOCAL_DAY)
            ->selectRaw(self::LOCAL_DAY.' as day')
            ->selectRaw(self::GROSS_SALES.' as gross_sales')
            ->selectRaw(self::COMMISSION_TOTAL.' as commission_total')
            ->get()
            ->keyBy(fn (object $row): string => mb_substr((string) $row->day, 0, 10));

        return array_map(function (CarbonImmutable $day) use ($perOrder, $perLine): array {
            $date = $day->toDateString();
            $orders = $perOrder->get($date);
            $lines = $perLine->get($date);

            $gross = (float) ($lines->gross_sales ?? 0.0);
            $commission = (float) ($lines->commission_total ?? 0.0);
            $shippingAndPenalty = (float) ($orders->shipping_and_penalty ?? 0.0);

            $deductions = $commission + $shippingAndPenalty;
            $net = max(0.0, $gross - $deductions);

            return [
                'date' => $date,
                'formattedDate' => $day->translatedFormat('d M'),
                'orderCount' => (int) ($orders->order_count ?? 0),
                'grossSales' => Money::format($gross),
                'rawGrossSales' => $gross,
                'commissionTotal' => Money::format($commission),
                'rawCommissionTotal' => $commission,
                'shippingAndPenalty' => Money::format($shippingAndPenalty),
                'rawShippingAndPenalty' => $shippingAndPenalty,
                'totalDeductions' => Money::format($deductions),
                'rawTotalDeductions' => $deductions,
                'netEarnings' => Money::format($net),
                'rawNetEarnings' => $net,
            ];
        }, $this->range->days());
    }
}
