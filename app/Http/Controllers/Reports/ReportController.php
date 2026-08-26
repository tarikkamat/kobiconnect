<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\ChannelConnection;
use App\Queries\Reports\ChannelBreakdown;
use App\Queries\Reports\PenalizedOrders;
use App\Queries\Reports\ReportRange;
use App\Queries\Reports\SalesSummary;
use App\Queries\Reports\SalesTrend;
use App\Queries\Reports\StatusDistribution;
use App\Queries\Reports\TopProducts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Rapor ekranlari. Bu sinif yetkiyi kontrol eder ve prop'lari toplar; her
 * sorgu app/Queries/Reports altinda kendi sinifindadir.
 *
 * Sebep: bes ekran ayni tarih/kanal filtresini ve ayni para toplamlarini
 * paylasiyor, ama her biri kendi raporunu farkli grupluyor. Hepsi tek sinifta
 * durdugunda ortak parcalar dokuz kez kopyalanmisti.
 */
class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $range = $this->range($request);

        return Inertia::render('reports/index', [
            ...$this->shared($range),
            'kpis' => (new SalesSummary($range))->get(),
            'salesTrend' => (new SalesTrend($range))->get(),
        ]);
    }

    /**
     * Pazaryeri ve satis kanali dagilimi.
     */
    public function channels(Request $request): Response
    {
        $range = $this->range($request);
        $kpis = (new SalesSummary($range))->get();

        return Inertia::render('reports/channels', [
            ...$this->shared($range),
            'kpis' => $kpis,
            'channelBreakdown' => (new ChannelBreakdown($range))->get((float) $kpis['rawGrossSales']),
        ]);
    }

    /**
     * Urun satis ve gelir performansi.
     */
    public function products(Request $request): Response
    {
        $range = $this->range($request);
        $search = $request->filled('search') ? (string) $request->input('search') : null;

        return Inertia::render('reports/products', [
            ...$this->shared($range),
            'filters' => ['connection' => $range->connectionId, 'search' => $search],
            'products' => (new TopProducts($range))->get($search),
        ]);
    }

    /**
     * Kargo kesintileri, desi asimi ve cezalar.
     */
    public function penalties(Request $request): Response
    {
        $range = $this->range($request);

        return Inertia::render('reports/penalties', [
            ...$this->shared($range),
            'kpis' => (new SalesSummary($range))->get(),
            'penalizedOrders' => (new PenalizedOrders($range))->get(),
        ]);
    }

    /**
     * Siparis hacmi ve operasyonel statu dagilimi.
     */
    public function orders(Request $request): Response
    {
        $range = $this->range($request);

        return Inertia::render('reports/orders', [
            ...$this->shared($range),
            'totalOrders' => $range->orders()->count(),
            'statusDistribution' => (new StatusDistribution($range))->get(),
        ]);
    }

    /**
     * Rapor izni ayri bir yetenek olarak tanimlanmadiysa siparis gorme izni
     * yeterlidir; rapor siparis verisinin ozetinden baska bir sey degil.
     */
    private function range(Request $request): ReportRange
    {
        if (Gate::has('reports.view')) {
            Gate::authorize('reports.view');
        } else {
            Gate::authorize('orders.view');
        }

        return ReportRange::fromRequest($request);
    }

    /**
     * Bes ekranin da tasidigi prop'lar.
     *
     * @return array<string, mixed>
     */
    private function shared(ReportRange $range): array
    {
        return [
            'range' => $range->toArray(),
            'filters' => ['connection' => $range->connectionId],
            'connections' => ChannelConnection::query()
                ->orderBy('name')
                ->get(['id', 'name', 'marketplace'])
                ->all(),
        ];
    }
}
