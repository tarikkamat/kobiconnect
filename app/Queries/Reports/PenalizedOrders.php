<?php

declare(strict_types=1);

namespace App\Queries\Reports;

use App\Queries\OrderTotals;
use App\Support\AppTime;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Ceza kesilmis siparisler.
 *
 * Iki sey degisti:
 *
 *  1. Elemeyi artik SQL yapiyor. Once araliktaki BUTUN siparisler cekilip
 *     PHP'de `totals` acilarak eleniyordu; cezalı siparis oran olarak azdir,
 *     gerisini tasimanin anlami yok.
 *  2. Kargo bilgisi `DISTINCT ON` ile siparis basina TEK pakete indirildi.
 *     Duz `leftJoin` iki paketli bir siparisi listede iki kez gosteriyor ve
 *     cezasini iki kez sayiyordu.
 */
final class PenalizedOrders extends ReportQuery
{
    /**
     * @return list<array<string, mixed>>
     */
    public function get(): array
    {
        $rows = $this->orders()
            ->join('channel_connections', 'channel_connections.id', '=', 'orders.connection_id')
            ->leftJoinSub(
                DB::table('shipment_packages')
                    ->selectRaw('DISTINCT ON (order_id) order_id, cargo_provider, deci')
                    ->orderByRaw('order_id, id'),
                'package',
                'package.order_id',
                '=',
                'orders.id',
            )
            ->whereRaw('('.OrderTotals::CARGO_PENALTY.' + '.OrderTotals::LATE_PENALTY.') > 0')
            ->orderByDesc('orders.placed_at')
            ->selectRaw('orders.id, orders.remote_order_number, orders.placed_at')
            ->selectRaw('channel_connections.name as connection_name, channel_connections.marketplace')
            ->selectRaw('package.cargo_provider, package.deci')
            ->selectRaw(OrderTotals::CARGO_PENALTY.' as cargo_penalty, '.OrderTotals::LATE_PENALTY.' as late_penalty')
            ->get();

        return array_values($rows->map(static function (stdClass $row): array {
            $cargoPenalty = (float) $row->cargo_penalty;
            $latePenalty = (float) $row->late_penalty;

            $reasons = [];

            if ($cargoPenalty > 0.0) {
                $reasons[] = 'Desi/Baremi Aşımı ('.Money::format($cargoPenalty).')';
            }

            if ($latePenalty > 0.0) {
                $reasons[] = 'Gecikme/İptal Bedeli ('.Money::format($latePenalty).')';
            }

            return [
                'id' => (int) $row->id,
                'orderNumber' => (string) $row->remote_order_number,
                'connectionName' => (string) $row->connection_name,
                'marketplace' => (string) $row->marketplace,
                'cargoProvider' => (string) ($row->cargo_provider ?? 'Bilinmiyor'),
                'deci' => $row->deci !== null ? (float) $row->deci : null,
                'cargoPenalty' => Money::format($cargoPenalty),
                'latePenalty' => Money::format($latePenalty),
                'totalPenalty' => Money::format($cargoPenalty + $latePenalty),
                'rawTotalPenalty' => $cargoPenalty + $latePenalty,
                'reasons' => implode(', ', $reasons),
                'placedAt' => AppTime::parse((string) $row->placed_at)->translatedFormat('d M Y H:i'),
            ];
        })->all());
    }
}
