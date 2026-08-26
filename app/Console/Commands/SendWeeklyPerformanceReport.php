<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\Digest\WeeklyPerformanceReport;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AppTime;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Number;
use stdClass;

final class SendWeeklyPerformanceReport extends Command
{
    protected $signature = 'email:weekly-performance';

    protected $description = 'Haftalık performans raporunu yetkili kullanıcılara gönderir.';

    public function handle(): int
    {
        // Kimlikleri geciyoruz, modelleri degil — SyncCommand ile ayni idiom.
        $tenants = Tenant::query()->pluck('id')->map(strval(...));

        tenancy()->runForMultiple($tenants, function (): void {
            rescue(fn () => $this->sendForCurrentTenant());
        });

        return self::SUCCESS;
    }

    private function sendForCurrentTenant(): void
    {
        $lastWeekStart = AppTime::now()->subWeek()->startOfWeek();
        $lastWeekEnd = $lastWeekStart->endOfWeek();
        $period = sprintf('%s - %s %s', $lastWeekStart->isoFormat('D MMMM'), $lastWeekEnd->isoFormat('D MMMM'), $lastWeekEnd->format('Y'));

        // Orders
        $orders = DB::table('orders')
            ->where('placed_at', '>=', $lastWeekStart->utc())
            ->where('placed_at', '<=', $lastWeekEnd->utc())
            ->selectRaw("count(*) as count, coalesce(sum((totals->>'net')::numeric), 0) as total")
            ->first();

        if ((int) $orders->count === 0) {
            return;
        }

        $count = (int) $orders->count;
        $total = (float) $orders->total;
        $average = $count > 0 ? $total / $count : 0;

        // Previous week for change calculation
        $previousWeekStart = $lastWeekStart->subWeek();
        $previousWeekEnd = $previousWeekStart->endOfWeek();
        $previousOrders = DB::table('orders')
            ->where('placed_at', '>=', $previousWeekStart->utc())
            ->where('placed_at', '<=', $previousWeekEnd->utc())
            ->selectRaw("count(*) as count, coalesce(sum((totals->>'net')::numeric), 0) as total")
            ->first();

        $previousTotal = (float) $previousOrders->total;
        $change = $previousTotal > 0
            ? sprintf('%+.0f%%', (($total - $previousTotal) / $previousTotal) * 100)
            : null;

        // Channels
        $channels = array_values(DB::table('orders')
            ->join('channel_connections', 'channel_connections.id', '=', 'orders.connection_id')
            ->where('orders.placed_at', '>=', $lastWeekStart->utc())
            ->where('orders.placed_at', '<=', $lastWeekEnd->utc())
            ->groupBy('channel_connections.name')
            ->selectRaw("channel_connections.name, count(*) as count, coalesce(sum((orders.totals->>'net')::numeric), 0) as total")
            ->orderByDesc('total')
            ->get()
            ->map(fn (stdClass $row): array => [
                'name' => $row->name,
                'count' => (int) $row->count,
                'total' => (string) Number::currency((float) $row->total, 'TRY', 'tr'),
            ])
            ->all());

        // Top 5 Products
        $topProducts = array_values(DB::table('order_lines')
            ->join('orders', 'orders.id', '=', 'order_lines.order_id')
            ->leftJoin('product_variants', 'product_variants.id', '=', 'order_lines.variant_id')
            ->leftJoin('products', 'products.id', '=', 'product_variants.product_id')
            ->where('orders.placed_at', '>=', $lastWeekStart->utc())
            ->where('orders.placed_at', '<=', $lastWeekEnd->utc())
            ->groupBy('order_lines.sku', 'products.name')
            ->selectRaw('order_lines.sku, products.name, sum(order_lines.quantity) as quantity, sum(order_lines.quantity * order_lines.unit_price) as total')
            ->orderByDesc('quantity')
            ->limit(5)
            ->get()
            ->map(fn (stdClass $row): array => [
                'sku' => (string) ($row->sku ?? '-'),
                'name' => (string) ($row->name ?? '-'),
                'quantity' => (int) $row->quantity,
                'total' => (string) Number::currency((float) $row->total, 'TRY', 'tr'),
            ])
            ->all());

        // Claims
        $claimsQuery = DB::table('claims')
            ->where('opened_at', '>=', $lastWeekStart->utc())
            ->where('opened_at', '<=', $lastWeekEnd->utc());
        $claimsCount = $claimsQuery->count();

        $claimsTotalVal = DB::table('claim_items')
            ->join('claims', 'claims.id', '=', 'claim_items.claim_id')
            ->join('order_lines', 'order_lines.id', '=', 'claim_items.order_line_id')
            ->where('claims.opened_at', '>=', $lastWeekStart->utc())
            ->where('claims.opened_at', '<=', $lastWeekEnd->utc())
            ->sum(DB::raw('claim_items.quantity * order_lines.unit_price'));

        $claimsTotal = (string) Number::currency((float) $claimsTotalVal, 'TRY', 'tr');

        // Critical Stock
        $criticalStock = DB::table('inventory_items')
            ->whereColumn('available', '<=', 'safety_stock')
            ->where('safety_stock', '>', 0)
            ->count();

        // Failed Syncs
        $failedSyncs = DB::table('sync_runs')
            ->where('started_at', '>=', $lastWeekStart->utc())
            ->where('status', 'failed')
            ->count();

        // Errored Connections
        $erroredConnections = DB::table('channel_connections')
            ->where('status', 'error')
            ->count();

        $data = [
            'period' => $period,
            'orders' => [
                'count' => $count,
                'total' => (string) Number::currency($total, 'TRY', 'tr'),
                'average' => (string) Number::currency($average, 'TRY', 'tr'),
                'change' => $change,
            ],
            'channels' => $channels,
            'topProducts' => $topProducts,
            'claims' => [
                'count' => $claimsCount,
                'total' => $claimsTotal,
            ],
            'criticalStock' => $criticalStock,
            'failedSyncs' => $failedSyncs,
            'erroredConnections' => $erroredConnections,
        ];

        $recipients = User::query()
            ->whereHas('roles.permissions', function ($query) {
                $query->where('name', 'reports.view');
            })
            ->get();

        foreach ($recipients as $user) {
            Mail::to($user)->queue(new WeeklyPerformanceReport($data));
        }
    }
}
