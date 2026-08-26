<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Number;
use Inertia\Inertia;
use Inertia\Response;
use stdClass;

class ReportController extends Controller
{
    private const array STATUS_LABELS = [
        'pending_payment' => 'Ödeme bekleniyor',
        'created' => 'Gönderime hazır',
        'picking' => 'Hazırlanıyor',
        'invoiced' => 'Faturalandı',
        'shipped' => 'Kargoda',
        'at_collection_point' => 'Teslimat noktasında',
        'delivered' => 'Teslim edildi',
        'undelivered' => 'Teslim edilemedi',
        'unpacked' => 'Paket bölündü',
        'unsupplied' => 'Tedarik edilemedi',
        'cancelled' => 'İptal edildi',
        'returned' => 'İade edildi',
        'unknown' => 'Bilinmeyen durum',
    ];

    /**
     * Finans ve Satış Raporu (Ciro, Kesintiler, Net Kazanç ve Günlük Satış Trendi).
     */
    public function index(Request $request): Response
    {
        $this->authorizeView();

        [$from, $to] = $this->range($request);
        $connectionId = $request->filled('connection') ? (int) $request->input('connection') : null;

        $connections = $this->connections();
        $kpis = $this->summaryKpis($from, $to, $connectionId);
        $salesTrend = $this->salesTrend($from, $to, $connectionId);

        return Inertia::render('reports/index', [
            'range' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'filters' => [
                'connection' => $connectionId,
            ],
            'connections' => $connections,
            'kpis' => $kpis,
            'salesTrend' => $salesTrend,
        ]);
    }

    /**
     * Pazaryeri ve Satış Kanalı Dağılım Raporu.
     */
    public function channels(Request $request): Response
    {
        $this->authorizeView();

        [$from, $to] = $this->range($request);
        $connectionId = $request->filled('connection') ? (int) $request->input('connection') : null;

        $connections = $this->connections();
        $kpis = $this->summaryKpis($from, $to, $connectionId);
        $channelBreakdown = $this->channelBreakdown($from, $to, $connectionId, $kpis['rawGrossSales']);

        return Inertia::render('reports/channels', [
            'range' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'filters' => [
                'connection' => $connectionId,
            ],
            'connections' => $connections,
            'kpis' => $kpis,
            'channelBreakdown' => $channelBreakdown,
        ]);
    }

    /**
     * Ürün Satış ve Gelir Performansı Raporu.
     */
    public function products(Request $request): Response
    {
        $this->authorizeView();

        [$from, $to] = $this->range($request);
        $connectionId = $request->filled('connection') ? (int) $request->input('connection') : null;
        $search = $request->filled('search') ? (string) $request->input('search') : null;

        $connections = $this->connections();
        $products = $this->topProducts($from, $to, $connectionId, $search);

        return Inertia::render('reports/products', [
            'range' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'filters' => [
                'connection' => $connectionId,
                'search' => $search,
            ],
            'connections' => $connections,
            'products' => $products,
        ]);
    }

    /**
     * Kargo Kesintileri, Desi Aşımı ve Cezalar Raporu.
     */
    public function penalties(Request $request): Response
    {
        $this->authorizeView();

        [$from, $to] = $this->range($request);
        $connectionId = $request->filled('connection') ? (int) $request->input('connection') : null;

        $connections = $this->connections();
        $kpis = $this->summaryKpis($from, $to, $connectionId);
        $penalizedOrders = $this->penalizedOrders($from, $to, $connectionId);

        return Inertia::render('reports/penalties', [
            'range' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'filters' => [
                'connection' => $connectionId,
            ],
            'connections' => $connections,
            'kpis' => $kpis,
            'penalizedOrders' => $penalizedOrders,
        ]);
    }

    /**
     * Sipariş Hacmi ve Operasyonel Statü Dağılımı Raporu.
     */
    public function orders(Request $request): Response
    {
        $this->authorizeView();

        [$from, $to] = $this->range($request);
        $connectionId = $request->filled('connection') ? (int) $request->input('connection') : null;

        $connections = $this->connections();
        $orderCount = DB::table('orders')
            ->whereBetween('orders.placed_at', [$from, $to])
            ->when($connectionId !== null, fn (Builder $q) => $q->where('orders.connection_id', $connectionId))
            ->count();

        $statusDistribution = $this->statusDistribution($from, $to, $connectionId);

        return Inertia::render('reports/orders', [
            'range' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'filters' => [
                'connection' => $connectionId,
            ],
            'connections' => $connections,
            'totalOrders' => $orderCount,
            'statusDistribution' => $statusDistribution,
        ]);
    }

    private function authorizeView(): void
    {
        if (Gate::has('reports.view')) {
            Gate::authorize('reports.view');
        } else {
            Gate::authorize('orders.view');
        }
    }

    /**
     * @return list<array{id: int, name: string, marketplace: string}>
     */
    private function connections(): array
    {
        return array_values(DB::table('channel_connections')
            ->select(['id', 'name', 'marketplace'])
            ->orderBy('name')
            ->get()
            ->map(fn (stdClass $c): array => [
                'id' => (int) $c->id,
                'name' => (string) $c->name,
                'marketplace' => (string) $c->marketplace,
            ])
            ->all());
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function range(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $today = CarbonImmutable::now('Europe/Istanbul')->endOfDay();

        $to = isset($validated['to'])
            ? CarbonImmutable::parse($validated['to'], 'Europe/Istanbul')->endOfDay()
            : $today;

        $from = isset($validated['from'])
            ? CarbonImmutable::parse($validated['from'], 'Europe/Istanbul')->startOfDay()
            : $to->subDays(30)->startOfDay();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        }

        return [$from, $to];
    }

    /**
     * @return array<string, mixed>
     */
    private function summaryKpis(CarbonImmutable $from, CarbonImmutable $to, ?int $connectionId): array
    {
        $ordersQuery = DB::table('orders')
            ->whereBetween('orders.placed_at', [$from, $to])
            ->when($connectionId !== null, fn (Builder $q) => $q->where('orders.connection_id', $connectionId));

        $orderRows = $ordersQuery->select(['id', 'totals'])->get();
        $orderCount = $orderRows->count();

        $shippingTotal = 0.0;
        $cargoPenaltyTotal = 0.0;
        $latePenaltyTotal = 0.0;

        foreach ($orderRows as $o) {
            $t = is_string($o->totals) ? json_decode($o->totals, true) : (array) $o->totals;
            $shippingTotal += (float) ($t['shipping_cost'] ?? 0.0);
            $cargoPenaltyTotal += (float) ($t['cargo_penalty'] ?? 0.0);
            $latePenaltyTotal += (float) ($t['late_penalty'] ?? 0.0);
        }

        $linesQuery = DB::table('orders')
            ->join('order_lines', 'order_lines.order_id', '=', 'orders.id')
            ->whereBetween('orders.placed_at', [$from, $to])
            ->when($connectionId !== null, fn (Builder $q) => $q->where('orders.connection_id', $connectionId));

        $lineStats = $linesQuery
            ->selectRaw('
                COALESCE(SUM(order_lines.quantity), 0) as item_count,
                COALESCE(SUM(order_lines.unit_price * order_lines.quantity), 0) as gross_sales,
                COALESCE(SUM((order_lines.unit_price * order_lines.quantity) * (COALESCE(order_lines.commission, 0) / 100.0)), 0) as commission_total
            ')
            ->first();

        $itemCount = (int) ($lineStats->item_count ?? 0);
        $grossSales = (float) ($lineStats->gross_sales ?? 0.0);
        $commissionTotal = (float) ($lineStats->commission_total ?? 0.0);

        $totalPenalties = $cargoPenaltyTotal + $latePenaltyTotal;
        $totalDeductions = $commissionTotal + $shippingTotal + $totalPenalties;
        $netEarnings = max(0.0, $grossSales - $totalDeductions);

        $avgOrderValue = $orderCount > 0 ? $grossSales / $orderCount : 0.0;
        $avgCommissionRate = $grossSales > 0 ? ($commissionTotal / $grossSales) * 100.0 : 0.0;

        return [
            'rawGrossSales' => $grossSales,
            'grossSales' => Number::currency($grossSales, 'TRY', 'tr'),
            'commissionTotal' => Number::currency($commissionTotal, 'TRY', 'tr'),
            'rawCommissionTotal' => $commissionTotal,
            'shippingTotal' => Number::currency($shippingTotal, 'TRY', 'tr'),
            'rawShippingTotal' => $shippingTotal,
            'cargoPenaltyTotal' => Number::currency($cargoPenaltyTotal, 'TRY', 'tr'),
            'rawCargoPenaltyTotal' => $cargoPenaltyTotal,
            'latePenaltyTotal' => Number::currency($latePenaltyTotal, 'TRY', 'tr'),
            'rawLatePenaltyTotal' => $latePenaltyTotal,
            'totalPenalties' => Number::currency($totalPenalties, 'TRY', 'tr'),
            'rawTotalPenalties' => $totalPenalties,
            'totalDeductions' => Number::currency($totalDeductions, 'TRY', 'tr'),
            'rawTotalDeductions' => $totalDeductions,
            'netEarnings' => Number::currency($netEarnings, 'TRY', 'tr'),
            'rawNetEarnings' => $netEarnings,
            'orderCount' => $orderCount,
            'itemCount' => $itemCount,
            'avgOrderValue' => Number::currency($avgOrderValue, 'TRY', 'tr'),
            'avgCommissionRate' => Number::percentage($avgCommissionRate, precision: 1, locale: 'tr'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function channelBreakdown(CarbonImmutable $from, CarbonImmutable $to, ?int $connectionId, float $totalGrossSales): array
    {
        $orders = DB::table('orders')
            ->join('channel_connections', 'channel_connections.id', '=', 'orders.connection_id')
            ->whereBetween('orders.placed_at', [$from, $to])
            ->when($connectionId !== null, fn (Builder $q) => $q->where('orders.connection_id', $connectionId))
            ->select(['orders.id', 'orders.connection_id', 'orders.totals', 'channel_connections.name', 'channel_connections.marketplace'])
            ->get();

        $lines = DB::table('orders')
            ->join('order_lines', 'order_lines.order_id', '=', 'orders.id')
            ->whereBetween('orders.placed_at', [$from, $to])
            ->when($connectionId !== null, fn (Builder $q) => $q->where('orders.connection_id', $connectionId))
            ->groupBy('orders.connection_id')
            ->selectRaw('
                orders.connection_id,
                COALESCE(SUM(order_lines.quantity), 0) as item_count,
                COALESCE(SUM(order_lines.unit_price * order_lines.quantity), 0) as gross_sales,
                COALESCE(SUM((order_lines.unit_price * order_lines.quantity) * (COALESCE(order_lines.commission, 0) / 100.0)), 0) as commission_total
            ')
            ->get()
            ->keyBy('connection_id');

        $grouped = $orders->groupBy('connection_id');

        $result = [];
        foreach ($grouped as $connId => $connOrders) {
            $first = $connOrders->first();
            $lineStat = $lines->get($connId);

            $gross = (float) ($lineStat->gross_sales ?? 0.0);
            $commission = (float) ($lineStat->commission_total ?? 0.0);
            $items = (int) ($lineStat->item_count ?? 0);

            $shipping = 0.0;
            $penalty = 0.0;

            foreach ($connOrders as $o) {
                $t = is_string($o->totals) ? json_decode($o->totals, true) : (array) $o->totals;
                $shipping += (float) ($t['shipping_cost'] ?? 0.0);
                $penalty += (float) ($t['cargo_penalty'] ?? 0.0) + (float) ($t['late_penalty'] ?? 0.0);
            }

            $deductions = $commission + $shipping + $penalty;
            $net = max(0.0, $gross - $deductions);
            $avgRate = $gross > 0 ? ($commission / $gross) * 100.0 : 0.0;
            $share = $totalGrossSales > 0 ? ($gross / $totalGrossSales) * 100.0 : 0.0;

            $result[] = [
                'id' => (int) $connId,
                'name' => (string) $first->name,
                'marketplace' => (string) $first->marketplace,
                'orderCount' => $connOrders->count(),
                'itemCount' => $items,
                'grossSales' => Number::currency($gross, 'TRY', 'tr'),
                'rawGrossSales' => $gross,
                'commissionTotal' => Number::currency($commission, 'TRY', 'tr'),
                'rawCommissionTotal' => $commission,
                'shippingTotal' => Number::currency($shipping, 'TRY', 'tr'),
                'rawShippingTotal' => $shipping,
                'penaltyTotal' => Number::currency($penalty, 'TRY', 'tr'),
                'rawPenaltyTotal' => $penalty,
                'totalDeductions' => Number::currency($deductions, 'TRY', 'tr'),
                'rawTotalDeductions' => $deductions,
                'netEarnings' => Number::currency($net, 'TRY', 'tr'),
                'rawNetEarnings' => $net,
                'avgCommissionRate' => Number::percentage($avgRate, precision: 1, locale: 'tr'),
                'sharePercentage' => Number::percentage($share, precision: 1, locale: 'tr'),
                'rawShare' => $share,
            ];
        }

        usort($result, fn ($a, $b) => $b['rawGrossSales'] <=> $a['rawGrossSales']);

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function salesTrend(CarbonImmutable $from, CarbonImmutable $to, ?int $connectionId): array
    {
        $orders = DB::table('orders')
            ->whereBetween('orders.placed_at', [$from, $to])
            ->when($connectionId !== null, fn (Builder $q) => $q->where('orders.connection_id', $connectionId))
            ->select(['id', 'placed_at', 'totals'])
            ->get();

        $lines = DB::table('orders')
            ->join('order_lines', 'order_lines.order_id', '=', 'orders.id')
            ->whereBetween('orders.placed_at', [$from, $to])
            ->when($connectionId !== null, fn (Builder $q) => $q->where('orders.connection_id', $connectionId))
            ->groupBy(DB::raw('DATE(orders.placed_at)'))
            ->selectRaw('
                DATE(orders.placed_at) as date,
                COALESCE(SUM(order_lines.unit_price * order_lines.quantity), 0) as gross_sales,
                COALESCE(SUM((order_lines.unit_price * order_lines.quantity) * (COALESCE(order_lines.commission, 0) / 100.0)), 0) as commission_total
            ')
            ->get()
            ->keyBy(fn ($r) => substr((string) $r->date, 0, 10));

        $ordersByDate = $orders->groupBy(fn ($o) => substr((string) $o->placed_at, 0, 10));

        $dates = [];
        $current = $from->startOfDay();
        while ($current->lessThanOrEqualTo($to)) {
            $dateStr = $current->toDateString();
            $dateOrders = $ordersByDate->get($dateStr, collect());
            $lineStat = $lines->get($dateStr);

            $gross = (float) ($lineStat->gross_sales ?? 0.0);
            $commission = (float) ($lineStat->commission_total ?? 0.0);
            $shippingAndPenalty = 0.0;

            foreach ($dateOrders as $o) {
                $t = is_string($o->totals) ? json_decode($o->totals, true) : (array) $o->totals;
                $shippingAndPenalty += (float) ($t['shipping_cost'] ?? 0.0) + (float) ($t['cargo_penalty'] ?? 0.0) + (float) ($t['late_penalty'] ?? 0.0);
            }

            $deductions = $commission + $shippingAndPenalty;
            $net = max(0.0, $gross - $deductions);

            $dates[] = [
                'date' => $dateStr,
                'formattedDate' => $current->translatedFormat('d M'),
                'orderCount' => $dateOrders->count(),
                'grossSales' => Number::currency($gross, 'TRY', 'tr'),
                'rawGrossSales' => $gross,
                'commissionTotal' => Number::currency($commission, 'TRY', 'tr'),
                'rawCommissionTotal' => $commission,
                'shippingAndPenalty' => Number::currency($shippingAndPenalty, 'TRY', 'tr'),
                'rawShippingAndPenalty' => $shippingAndPenalty,
                'totalDeductions' => Number::currency($deductions, 'TRY', 'tr'),
                'rawTotalDeductions' => $deductions,
                'netEarnings' => Number::currency($net, 'TRY', 'tr'),
                'rawNetEarnings' => $net,
            ];

            $current = $current->addDay();
        }

        return $dates;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function topProducts(CarbonImmutable $from, CarbonImmutable $to, ?int $connectionId, ?string $search = null): array
    {
        $rows = DB::table('order_lines')
            ->join('orders', 'orders.id', '=', 'order_lines.order_id')
            ->whereBetween('orders.placed_at', [$from, $to])
            ->when($connectionId !== null, fn (Builder $q) => $q->where('orders.connection_id', $connectionId))
            ->when($search !== null && $search !== '', function (Builder $q) use ($search): void {
                $term = '%'.mb_strtolower(trim($search)).'%';
                $q->where(function (Builder $sub) use ($term): void {
                    $sub->whereRaw('LOWER(order_lines.sku) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(order_lines.barcode) LIKE ?', [$term]);
                });
            })
            ->groupBy(['order_lines.sku', 'order_lines.barcode'])
            ->selectRaw('
                order_lines.sku,
                order_lines.barcode,
                COALESCE(SUM(order_lines.quantity), 0) as quantity_sold,
                COALESCE(SUM(order_lines.unit_price * order_lines.quantity), 0) as gross_sales,
                COALESCE(SUM((order_lines.unit_price * order_lines.quantity) * (COALESCE(order_lines.commission, 0) / 100.0)), 0) as commission_total
            ')
            ->orderByDesc('gross_sales')
            ->limit(100)
            ->get();

        return array_values($rows->map(function (stdClass $row): array {
            $gross = (float) $row->gross_sales;
            $commission = (float) $row->commission_total;
            $net = $gross - $commission;

            return [
                'sku' => (string) ($row->sku ?: 'Bilinmiyor'),
                'barcode' => $row->barcode ? (string) $row->barcode : null,
                'quantitySold' => (int) $row->quantity_sold,
                'grossSales' => Number::currency($gross, 'TRY', 'tr'),
                'rawGrossSales' => $gross,
                'commissionTotal' => Number::currency($commission, 'TRY', 'tr'),
                'rawCommissionTotal' => $commission,
                'netEarnings' => Number::currency($net, 'TRY', 'tr'),
                'rawNetEarnings' => $net,
            ];
        })->all());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function statusDistribution(CarbonImmutable $from, CarbonImmutable $to, ?int $connectionId): array
    {
        $rows = DB::table('orders')
            ->whereBetween('orders.placed_at', [$from, $to])
            ->when($connectionId !== null, fn (Builder $q) => $q->where('orders.connection_id', $connectionId))
            ->groupBy('status')
            ->selectRaw('status, COUNT(*) as count')
            ->orderByDesc('count')
            ->get();

        $totalCount = $rows->sum('count');

        return array_values($rows->map(fn (stdClass $row): array => [
            'status' => (string) $row->status,
            'label' => self::STATUS_LABELS[$row->status] ?? ucfirst((string) $row->status),
            'count' => (int) $row->count,
            'percentage' => $totalCount > 0 ? Number::percentage(((int) $row->count / $totalCount) * 100.0, precision: 1, locale: 'tr') : '0.0',
            'rawPercentage' => $totalCount > 0 ? ((int) $row->count / $totalCount) * 100.0 : 0.0,
        ])->all());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function penalizedOrders(CarbonImmutable $from, CarbonImmutable $to, ?int $connectionId): array
    {
        $orders = DB::table('orders')
            ->join('channel_connections', 'channel_connections.id', '=', 'orders.connection_id')
            ->leftJoin('shipment_packages', 'shipment_packages.order_id', '=', 'orders.id')
            ->whereBetween('orders.placed_at', [$from, $to])
            ->when($connectionId !== null, fn (Builder $q) => $q->where('orders.connection_id', $connectionId))
            ->select([
                'orders.id',
                'orders.remote_order_number',
                'orders.placed_at',
                'orders.status',
                'orders.totals',
                'channel_connections.name as connection_name',
                'channel_connections.marketplace',
                'shipment_packages.cargo_provider',
                'shipment_packages.deci',
            ])
            ->orderByDesc('orders.placed_at')
            ->get();

        $penalized = [];
        foreach ($orders as $o) {
            $t = is_string($o->totals) ? json_decode($o->totals, true) : (array) $o->totals;
            $cargoPenalty = (float) ($t['cargo_penalty'] ?? 0.0);
            $latePenalty = (float) ($t['late_penalty'] ?? 0.0);
            $totalPenalty = $cargoPenalty + $latePenalty;

            if ($totalPenalty > 0.0) {
                $reasons = [];
                if ($cargoPenalty > 0.0) {
                    $reasons[] = 'Desi/Baremi Aşımı ('.Number::currency($cargoPenalty, 'TRY', 'tr').')';
                }
                if ($latePenalty > 0.0) {
                    $reasons[] = 'Gecikme/İptal Bedeli ('.Number::currency($latePenalty, 'TRY', 'tr').')';
                }

                $penalized[] = [
                    'id' => (int) $o->id,
                    'orderNumber' => (string) $o->remote_order_number,
                    'connectionName' => (string) $o->connection_name,
                    'marketplace' => (string) $o->marketplace,
                    'cargoProvider' => (string) ($o->cargo_provider ?? 'Bilinmiyor'),
                    'deci' => $o->deci !== null ? (float) $o->deci : null,
                    'cargoPenalty' => Number::currency($cargoPenalty, 'TRY', 'tr'),
                    'latePenalty' => Number::currency($latePenalty, 'TRY', 'tr'),
                    'totalPenalty' => Number::currency($totalPenalty, 'TRY', 'tr'),
                    'rawTotalPenalty' => $totalPenalty,
                    'reasons' => implode(', ', $reasons),
                    'placedAt' => CarbonImmutable::parse((string) $o->placed_at, 'Europe/Istanbul')->translatedFormat('d M Y H:i'),
                ];
            }
        }

        return $penalized;
    }
}
