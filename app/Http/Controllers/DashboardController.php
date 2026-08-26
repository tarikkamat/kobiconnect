<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ChannelConnection;
use App\Models\Order;
use App\Models\Product;
use App\Queries\Dashboard\ConnectionHealth;
use App\Queries\Dashboard\CriticalStock;
use App\Queries\Dashboard\SalesSnapshot;
use App\Queries\Dashboard\SyncHealth;
use App\Queries\Dashboard\UnmatchedLines;
use App\Support\AppTime;
use App\Support\DashboardDemoData;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gösterge paneli — FRONTEND-PLAN §12 faz 9.
 *
 * Panel bilerek en sona bırakılmıştı: hangi metriğin işe yaradığı ancak veri
 * aktıktan sonra bilinir. Buradaki beş widget'ın hepsinin bir karşılığı var;
 * hiçbiri "grafik olsun diye" konulmadı ve her biri kendi ekranına götürür.
 *
 * Widget'ların tamamı `Inertia::defer()` ile gelir (FRONTEND-PLAN §3): ilk
 * boyama beş toplama sorgusunu beklemez. Hepsi varsayılan grupta olduğu için
 * istemci TEK bir ek istek atar.
 *
 * Grafikler (kpis, salesTrend, channelShare, orderVolume, salesTarget)
 * MVP sunumu için ÖRNEK veriyle gelir; üretimi tek bir yerde,
 * `App\Support\DashboardDemoData` içinde durur ve panelde rozetle işaretlidir.
 *
 * Yalnızca "bu tenant'ta veri var mı" sorusu senkron cevaplanır; yeni bir
 * tenant iskelet yerine doğrudan boş durumu görür, skeleton yanıp sönmez.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardDemoData $demo): Response
    {
        [$from, $to] = $this->range($request);
        $demo->forRange($from, $to);

        return Inertia::render('dashboard', [
            // Secili donem her widget'in ustunde tek kaynaktan durur; secici
            // varsayilani buradan okur, istemci tarih hesabi yapmaz.
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            // Ucuz uc `exists()`; boş durumun skeleton arkasında saklanmamasi
            // icin bilerek deferred DEGIL.
            'setup' => [
                'hasConnections' => ChannelConnection::query()->exists(),
                'hasProducts' => Product::query()->exists(),
                'hasOrders' => Order::query()->exists(),
            ],
            'sales' => Inertia::defer(fn (): array => (new SalesSnapshot)->get($from, $to)),
            'unmatched' => Inertia::defer(fn (): array => (new UnmatchedLines)->get()),
            'syncHealth' => Inertia::defer(fn (): array => (new SyncHealth)->get()),
            'criticalStock' => Inertia::defer(fn (): array => (new CriticalStock)->get()),
            'connections' => Inertia::defer(fn (): array => (new ConnectionHealth)->get()),

            // Grafikler ORNEK veriyle gelir (App\Support\DashboardDemoData);
            // kartlarda "Örnek veri" rozetiyle isaretlidir. Ayni varsayilan
            // grupta olduklari icin istemci hala TEK ek istek atar.
            'kpis' => Inertia::defer(fn (): array => $demo->kpis()),
            'salesTrend' => Inertia::defer(fn (): array => $demo->salesTrend()),
            'channelShare' => Inertia::defer(fn (): array => $demo->channelShare()),
            'orderVolume' => Inertia::defer(fn (): array => $demo->orderVolume()),
            'salesTarget' => Inertia::defer(fn (): array => $demo->salesTarget()),
        ]);
    }

    /**
     * Panelin dönem sınırları. Varsayılan son 30 gün; `from`/`to` sorgu
     * parametreleriyle herhangi iki tarih arasına çekilir. Ters verilmiş bir
     * aralık hataya katlanmaz, sessizce düzeltilir: operatör panelde form
     * doğrulamasıyla uğraşmaz.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function range(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $today = AppTime::now()->startOfDay();

        $to = isset($validated['to'])
            ? AppTime::parse($validated['to'])->startOfDay()
            : $today;
        $from = isset($validated['from'])
            ? AppTime::parse($validated['from'])->startOfDay()
            : $to->subDays(29);

        return $from->greaterThan($to) ? [$to, $from] : [$from, $to];
    }
}
