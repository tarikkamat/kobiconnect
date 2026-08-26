<?php

declare(strict_types=1);

namespace App\Queries\Dashboard;

use App\Models\InventoryItem;
use Illuminate\Database\Eloquent\Builder;

/**
 * Satilabilir miktari emniyet stokunun altina inmis varyantlar. `available`
 * uretilmis bir kolondur (on_hand - reserved), yazilmaz.
 */
final class CriticalStock
{
    private const int PREVIEW = 5;

    /**
     * @return array{count: int, items: list<array<string, mixed>>}
     */
    public function get(): array
    {
        $critical = fn (): Builder => InventoryItem::query()
            ->whereColumn('available', '<=', 'safety_stock');

        $items = $critical()
            ->with(['variant:id,sku,product_id', 'variant.product:id,name'])
            ->orderBy('available')
            ->limit(self::PREVIEW)
            ->get(['id', 'variant_id', 'available', 'safety_stock']);

        return [
            'count' => $critical()->count(),
            'items' => array_values($items
                ->map(fn (InventoryItem $item): array => [
                    'id' => $item->variant_id,
                    'sku' => (string) $item->variant?->sku,
                    'product' => (string) $item->variant?->product?->name,
                    'available' => (int) $item->available,
                    'safetyStock' => (int) $item->safety_stock,
                ])
                ->all()),
        ];
    }
}
