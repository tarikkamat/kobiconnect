<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Order;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetSalesSummaryTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Tüm bağlı pazaryerlerinden (Trendyol, Hepsiburada vb.) gelen satış, ciro ve sipariş adetlerinin kanal bazlı özetini getirir.';
    }

    public function handle(Request $request): Stringable|string
    {
        $days = (int) ($request['days'] ?? 7);
        $since = Carbon::now()->subDays($days);

        $orders = Order::with('connection')
            ->where('placed_at', '>=', $since)
            ->get();

        $totalRevenue = 0.0;
        $channelStats = [];

        foreach ($orders as $order) {
            /** @var array<string, mixed> $totals */
            $totals = (array) ($order->getAttribute('totals') ?? []);
            $amount = (float) ($totals['gross_amount'] ?? $totals['total'] ?? 0.0);
            $totalRevenue += $amount;

            $channelName = $order->connection ? $order->connection->name : 'Doğrudan';
            if (! isset($channelStats[$channelName])) {
                $channelStats[$channelName] = [
                    'channel' => $channelName,
                    'order_count' => 0,
                    'revenue' => 0.0,
                ];
            }

            $channelStats[$channelName]['order_count']++;
            $channelStats[$channelName]['revenue'] += $amount;
        }

        return (string) json_encode([
            'period_days' => $days,
            'total_orders' => $orders->count(),
            'total_revenue' => round($totalRevenue, 2),
            'channels' => array_values($channelStats),
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'days' => $schema->integer()->description('İncelenecek geçmiş gün sayısı (varsayılan: 7)'),
        ];
    }
}
