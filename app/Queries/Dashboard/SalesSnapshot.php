<?php

declare(strict_types=1);

namespace App\Queries\Dashboard;

use App\Queries\OrderTotals;
use App\Support\AppTime;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Secili donemde gelen siparis adedi ve tutari.
 *
 * Gun siniri Europe/Istanbul'a gore alinir ve UTC'ye cevrilerek sorulur:
 * `placed_at` zaman dilimsiz saklaniyor, "bugun" ise operatorun gunu.
 */
final class SalesSnapshot
{
    /**
     * @return array{count: int, total: string, today: int}
     */
    public function get(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $row = DB::table('orders')
            ->where('placed_at', '>=', $from->utc())
            ->where('placed_at', '<', $to->addDay()->utc())
            ->selectRaw('count(*) as orders')
            ->selectRaw(OrderTotals::NET_SUM.' as total')
            ->first();

        return [
            'count' => (int) ($row->orders ?? 0),
            'total' => Money::format((float) ($row->total ?? 0.0)),
            'today' => DB::table('orders')
                ->where('placed_at', '>=', AppTime::now()->startOfDay()->utc())
                ->count(),
        ];
    }
}
