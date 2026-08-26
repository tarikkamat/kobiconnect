<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\Digest\DailySalesSummary;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AppTime;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Number;
use stdClass;

final class SendDailySalesSummary extends Command
{
    protected $signature = 'email:daily-sales-summary';

    protected $description = 'Dünkü satış özetini yetkili kullanıcılara e-posta ile gönderir.';

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
        $yesterday = AppTime::now()->subDay()->startOfDay();
        $today = $yesterday->addDay();

        $orders = DB::table('orders')
            ->where('placed_at', '>=', $yesterday->utc())
            ->where('placed_at', '<', $today->utc())
            ->selectRaw("count(*) as count, coalesce(sum((totals->>'net')::numeric), 0) as total")
            ->first();

        if ((int) $orders->count === 0) {
            return;
        }

        $count = (int) $orders->count;
        $total = (float) $orders->total;
        $average = $count > 0 ? $total / $count : 0;

        // Önceki gün karşılaştırması
        $previousDay = $yesterday->subDay();
        $previousOrders = DB::table('orders')
            ->where('placed_at', '>=', $previousDay->utc())
            ->where('placed_at', '<', $yesterday->utc())
            ->selectRaw("count(*) as count, coalesce(sum((totals->>'net')::numeric), 0) as total")
            ->first();

        $previousTotal = (float) $previousOrders->total;
        $change = $previousTotal > 0
            ? sprintf('%+.0f%%', (($total - $previousTotal) / $previousTotal) * 100)
            : null;

        // Kanal dağılımı
        $channels = array_values(DB::table('orders')
            ->join('channel_connections', 'channel_connections.id', '=', 'orders.connection_id')
            ->where('orders.placed_at', '>=', $yesterday->utc())
            ->where('orders.placed_at', '<', $today->utc())
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

        // En çok satan 3 SKU
        $topSkus = array_values(DB::table('order_lines')
            ->join('orders', 'orders.id', '=', 'order_lines.order_id')
            ->leftJoin('product_variants', 'product_variants.id', '=', 'order_lines.variant_id')
            ->leftJoin('products', 'products.id', '=', 'product_variants.product_id')
            ->where('orders.placed_at', '>=', $yesterday->utc())
            ->where('orders.placed_at', '<', $today->utc())
            ->groupBy('order_lines.sku', 'products.name')
            ->selectRaw('order_lines.sku, products.name, sum(order_lines.quantity) as quantity')
            ->orderByDesc('quantity')
            ->limit(3)
            ->get()
            ->map(fn (stdClass $row): array => [
                'sku' => (string) ($row->sku ?? '-'),
                'name' => (string) ($row->name ?? '-'),
                'quantity' => (int) $row->quantity,
            ])
            ->all());

        // İptaller
        $cancellations = (int) DB::table('orders')
            ->where('placed_at', '>=', $yesterday->utc())
            ->where('placed_at', '<', $today->utc())
            ->where('status', 'cancelled')
            ->count();

        $data = [
            'count' => $count,
            'total' => (string) Number::currency($total, 'TRY', 'tr'),
            'average' => (string) Number::currency($average, 'TRY', 'tr'),
            'change' => $change,
            'channels' => $channels,
            'topSkus' => $topSkus,
            'cancellations' => $cancellations,
        ];

        $recipients = User::query()
            ->whereHas('roles.permissions', function ($query) {
                $query->where('name', 'reports.view');
            })
            ->get();

        foreach ($recipients as $user) {
            Mail::to($user)->queue(new DailySalesSummary($data));
        }
    }
}
