<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ConnectionStatus;
use App\Marketplaces\Data\Enums\SyncState;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use Inertia\Inertia;
use Inertia\Response;
use stdClass;

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
 * Yalnızca "bu tenant'ta veri var mı" sorusu senkron cevaplanır; yeni bir
 * tenant iskelet yerine doğrudan boş durumu görür, skeleton yanıp sönmez.
 */
class DashboardController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const array CONNECTION_STATUS_LABELS = [
        'active' => 'Bağlı',
        'paused' => 'Duraklatıldı',
        'error' => 'Hata',
    ];

    /**
     * @var array<string, string>
     */
    private const array RESOURCE_LABELS = [
        'orders' => 'Siparişler',
        'products' => 'Ürünler',
        'claims' => 'İadeler',
        'questions' => 'Sorular',
        'stock' => 'Stok',
        'prices' => 'Fiyatlar',
    ];

    public function __invoke(): Response
    {
        return Inertia::render('dashboard', [
            // Ucuz uc `exists()`; boş durumun skeleton arkasında saklanmamasi
            // icin bilerek deferred DEGIL.
            'setup' => [
                'hasConnections' => DB::table('channel_connections')->exists(),
                'hasProducts' => DB::table('products')->exists(),
                'hasOrders' => DB::table('orders')->exists(),
            ],
            'sales' => Inertia::defer(fn (): array => $this->sales()),
            'unmatched' => Inertia::defer(fn (): array => $this->unmatched()),
            'syncHealth' => Inertia::defer(fn (): array => $this->syncHealth()),
            'criticalStock' => Inertia::defer(fn (): array => $this->criticalStock()),
            'connections' => Inertia::defer(fn (): array => $this->connections()),
        ]);
    }

    /**
     * Bugün ve bu hafta gelen sipariş adedi ve tutarı.
     *
     * Gün sınırı Europe/Istanbul'a göre alınır ve UTC'ye çevrilerek sorulur:
     * `placed_at` zaman dilimsiz saklanıyor, "bugün" ise operatörün günü.
     *
     * @return array{today: array{count: int, total: string}, week: array{count: int, total: string}}
     */
    private function sales(): array
    {
        $local = Carbon::now('Europe/Istanbul');

        return [
            'today' => $this->salesSince($local->copy()->startOfDay()),
            'week' => $this->salesSince($local->copy()->startOfWeek()),
        ];
    }

    /**
     * @return array{count: int, total: string}
     */
    private function salesSince(Carbon $from): array
    {
        /** @var stdClass $row */
        $row = DB::table('orders')
            ->where('placed_at', '>=', $from->utc())
            // `totals` her zaman `net` tasimaz; eksik alan toplami bozmamali.
            ->selectRaw("count(*) as orders, coalesce(sum((totals->>'net')::numeric), 0) as total")
            ->first();

        return [
            'count' => (int) $row->orders,
            // Para sunucuda bicimlenir — FRONTEND-PLAN §7.
            'total' => (string) Number::currency((float) $row->total, 'TRY', 'tr'),
        ];
    }

    /**
     * Eşleşmemiş sipariş satırları: barkodu katalogda bulunamayan satırlar.
     * Operatörün ilk bakacağı yer burasıdır — sipariş kaydedildi ama hangi
     * ürün olduğu bilinmiyor, o yüzden hazırlanamaz.
     *
     * @return array{lines: int, orders: int}
     */
    private function unmatched(): array
    {
        return [
            'lines' => DB::table('order_lines')->whereNull('variant_id')->count(),
            'orders' => DB::table('order_lines')->whereNull('variant_id')->distinct()->count('order_id'),
        ];
    }

    /**
     * Senkron sağlığı: son koşular + outbox'ta başarısız kalan işlem sayısı.
     *
     * @return array{failedOperations: int, pendingOperations: int, runs: list<array<string, mixed>>}
     */
    private function syncHealth(): array
    {
        $operations = DB::table('channel_operations')
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'failedOperations' => (int) $operations->get(SyncState::Failed->value, 0),
            'pendingOperations' => (int) $operations->get(SyncState::Pending->value, 0),
            'runs' => array_values(DB::table('sync_runs')
                ->leftJoin('channel_connections', 'channel_connections.id', '=', 'sync_runs.connection_id')
                ->orderByDesc('sync_runs.started_at')
                ->limit(5)
                ->get([
                    'sync_runs.id', 'sync_runs.resource', 'sync_runs.status',
                    'sync_runs.started_at', 'channel_connections.name as connection',
                ])
                ->map(fn (stdClass $run): array => [
                    'id' => (int) $run->id,
                    'connection' => $run->connection,
                    'resource' => self::RESOURCE_LABELS[$run->resource] ?? $run->resource,
                    'status' => (string) $run->status,
                    'startedAt' => $this->dateTime($run->started_at),
                ])
                ->all()),
        ];
    }

    /**
     * Kritik stok: satılabilir miktar emniyet stokunun altına inmiş varyantlar.
     * `available` üretilmiş bir kolondur (on_hand - reserved), yazılmaz.
     *
     * @return array{count: int, items: list<array<string, mixed>>}
     */
    private function criticalStock(): array
    {
        $query = DB::table('inventory_items')
            ->join('product_variants', 'product_variants.id', '=', 'inventory_items.variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->whereColumn('inventory_items.available', '<=', 'inventory_items.safety_stock');

        return [
            'count' => (clone $query)->count(),
            'items' => array_values((clone $query)
                ->orderBy('inventory_items.available')
                ->orderBy('product_variants.sku')
                ->limit(5)
                ->get([
                    'product_variants.id', 'product_variants.sku', 'products.name',
                    'inventory_items.available', 'inventory_items.safety_stock',
                ])
                ->map(fn (stdClass $item): array => [
                    'id' => (int) $item->id,
                    'sku' => (string) $item->sku,
                    'product' => (string) $item->name,
                    'available' => (int) $item->available,
                    'safetyStock' => (int) $item->safety_stock,
                ])
                ->all()),
        ];
    }

    /**
     * Kanal bağlantı durumu. Hata veren bir bağlantı sessizce durur; panelin
     * bunu söylemesi gerekir.
     *
     * @return array{items: list<array<string, mixed>>, errored: int}
     */
    private function connections(): array
    {
        $connections = DB::table('channel_connections')
            ->orderBy('name')
            ->get(['id', 'name', 'marketplace', 'status', 'last_health_check_at']);

        return [
            'errored' => $connections->where('status', ConnectionStatus::Error->value)->count(),
            'items' => array_values($connections
                ->map(fn (stdClass $connection): array => [
                    'id' => (int) $connection->id,
                    'name' => (string) $connection->name,
                    'marketplace' => (string) $connection->marketplace,
                    'status' => (string) $connection->status,
                    'statusLabel' => self::CONNECTION_STATUS_LABELS[$connection->status] ?? $connection->status,
                    'checkedAt' => $this->dateTime($connection->last_health_check_at),
                ])
                ->all()),
        ];
    }

    /**
     * Tarihler sunucuda bicimlenir, Europe/Istanbul — FRONTEND-PLAN §7.
     */
    private function dateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value)->timezone('Europe/Istanbul')->format('d.m.Y H:i');
    }
}
