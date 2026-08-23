<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Product;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class AnalyzeProductProfitabilityTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Ürünlerin satış fiyatı, maliyet, pazaryeri komisyonu ve kargo giderleri sonrası net kârlılık durumunu hesaplar. Kargo ve iade maliyetleri nedeniyle kârı eriyen ürünleri belirler.';
    }

    public function handle(Request $request): Stringable|string
    {
        $productIds = $request['product_ids'] ?? [];
        $query = Product::with(['variants.prices', 'category']);

        if (! empty($productIds)) {
            $query->whereIn('id', (array) $productIds);
        } else {
            $query->take(10);
        }

        $products = $query->get();
        $analysis = [];

        foreach ($products as $product) {
            $variant = $product->variants->first();
            $priceModel = $variant?->prices->first();
            $salePrice = (float) ($priceModel ? ($priceModel->sale_price ?? $priceModel->list_price) : 200.0);
            $costPrice = (float) ($priceModel && $priceModel->cost !== null ? $priceModel->cost : ($salePrice * 0.45)); // Varsayılan tahmini maliyet
            $commissionRate = 0.18; // %18 ortalama pazaryeri komisyonu
            $shippingCost = 45.0; // TL

            $commissionFee = $salePrice * $commissionRate;
            $netProfit = $salePrice - $costPrice - $commissionFee - $shippingCost;
            $netMargin = $salePrice > 0 ? round(($netProfit / $salePrice) * 100, 2) : 0;
            $isErodingProfit = $netProfit <= 0 || $netMargin < 8.0;

            $analysis[] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'sale_price' => $salePrice,
                'cost_price' => $costPrice,
                'commission_fee' => round($commissionFee, 2),
                'shipping_cost' => $shippingCost,
                'net_profit' => round($netProfit, 2),
                'net_margin_percentage' => $netMargin,
                'is_eroding_profit' => $isErodingProfit,
                'status' => $product->status->value ?? 'active',
            ];
        }

        return (string) json_encode([
            'analyzed_products' => $analysis,
            'unprofitable_count' => count(array_filter($analysis, fn ($i) => $i['is_eroding_profit'])),
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'product_ids' => $schema->array()->items($schema->integer())->description('İncelenecek ürün ID listesi (boş bırakılırsa genel ürünler taranır)'),
        ];
    }
}
