<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\InventoryItem;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetInventoryAlertsTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Stokları tükenmekte olan, kritik seviyenin altına düşmüş veya bitmiş ürünlerin stok uyarılarını listeler.';
    }

    public function handle(Request $request): Stringable|string
    {
        $threshold = (int) ($request['threshold'] ?? 5);

        $lowStockItems = InventoryItem::with(['variant.product', 'warehouse'])
            ->where('on_hand', '<=', $threshold)
            ->take(15)
            ->get();

        $alerts = [];
        foreach ($lowStockItems as $item) {
            $variant = $item->variant;
            $product = $variant?->product;
            $warehouse = $item->warehouse;

            $alerts[] = [
                'product_name' => $product ? $product->name : 'Bilinmeyen Ürün',
                'sku' => $variant ? $variant->sku : '-',
                'barcode' => $variant ? ($variant->barcode ?? '-') : '-',
                'warehouse' => $warehouse ? $warehouse->name : 'Ana Depo',
                'on_hand' => $item->on_hand,
                'reserved' => $item->reserved,
                'available' => $item->available,
                'is_out_of_stock' => $item->on_hand <= 0,
            ];
        }

        return (string) json_encode([
            'threshold' => $threshold,
            'alert_count' => count($alerts),
            'items' => $alerts,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'threshold' => $schema->integer()->description('Kritik stok eşiği (bu değer ve altındakiler getirilir, varsayılan: 5)'),
        ];
    }
}
