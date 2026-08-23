<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Claim;
use App\Models\Product;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetTopReturnedProductsTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Belirtilen son X günde en çok iade talebi alan ürünleri ve iade gerekçelerini listeler.';
    }

    public function handle(Request $request): Stringable|string
    {
        $days = (int) ($request['days'] ?? 7);
        $limit = (int) ($request['limit'] ?? 5);
        $since = Carbon::now()->subDays($days);

        $claims = Claim::with(['order.lines.variant.product'])
            ->where('opened_at', '>=', $since)
            ->get();

        $productStats = [];

        foreach ($claims as $claim) {
            $order = $claim->order;
            if (! $order) {
                continue;
            }

            foreach ($order->lines as $line) {
                $product = $line->variant?->product;
                if (! $product) {
                    continue;
                }

                $productId = $product->id;
                if (! isset($productStats[$productId])) {
                    $productStats[$productId] = [
                        'product_id' => $productId,
                        'name' => $product->name,
                        'return_count' => 0,
                        'reasons' => [],
                    ];
                }

                $productStats[$productId]['return_count']++;
                if ($claim->reason) {
                    $productStats[$productId]['reasons'][] = $claim->reason;
                }
            }
        }

        // Eğer iade kaydı yoksa genel ürünlerden örnek liste dön
        if (empty($productStats)) {
            $fallbackProducts = Product::with('variants')->take($limit)->get();
            $result = $fallbackProducts->map(fn ($p) => [
                'product_id' => $p->id,
                'name' => $p->name,
                'return_count' => 0,
                'reasons' => ['Kayıtlı iade bulunamadı.'],
            ])->values()->all();

            return (string) json_encode([
                'period_days' => $days,
                'returned_products' => $result,
                'total_returns' => 0,
            ], JSON_UNESCAPED_UNICODE);
        }

        usort($productStats, fn ($a, $b) => $b['return_count'] <=> $a['return_count']);
        $topProducts = array_slice($productStats, 0, $limit);

        return (string) json_encode([
            'period_days' => $days,
            'returned_products' => $topProducts,
            'total_returns' => count($claims),
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'days' => $schema->integer()->description('İncelenecek geçmiş gün sayısı (varsayılan: 7)'),
            'limit' => $schema->integer()->description('Döndürülecek maksimum ürün sayısı (varsayılan: 5)'),
        ];
    }
}
