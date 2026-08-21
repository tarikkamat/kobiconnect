<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\InventoryItem;
use App\Models\Price;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use stdClass;

/**
 * Secili urunlerin varyantlarinda toplu fiyat/stok degisikligi.
 *
 * Ayni `plan()` hem onizlemeyi hem yazmayi besler: kullanicinin gordugu ornek
 * satir ile uygulanan deger AYNI hesaptan cikar — FRONTEND-PLAN §4.5.
 */
final class BulkEditVariants
{
    /** Onizlemede gosterilen ornek satir sayisi. */
    public const int SAMPLE_LIMIT = 5;

    /**
     * @param  list<int>  $productIds
     * @return array{affected: int, samples: list<array{sku: string, current: string, next: string}>}
     */
    public function preview(array $productIds, string $field, string $mode, float $value): array
    {
        $rows = $this->plan($productIds, $field, $mode, $value);

        return [
            'affected' => count($rows),
            'samples' => array_map(
                fn (array $row): array => [
                    'sku' => $row['sku'],
                    'current' => $this->display($field, $row['current']),
                    'next' => $this->display($field, $row['next']),
                ],
                array_slice($rows, 0, self::SAMPLE_LIMIT),
            ),
        ];
    }

    /**
     * @param  list<int>  $productIds
     * @return int Yazilan satir sayisi.
     */
    public function apply(array $productIds, string $field, string $mode, float $value): int
    {
        $rows = $this->plan($productIds, $field, $mode, $value);

        if ($rows === []) {
            return 0;
        }

        // ponytail: satir satir updateOrCreate. `prices` uzerinde (variant_id,
        // currency) UNIQUE degil, yalnizca index — upsert kullanilamaz. 500
        // urun tavaninin ustune cikilirsa once o kisit unique yapilmali.
        DB::transaction(function () use ($rows, $field): void {
            foreach ($rows as $row) {
                if ($field === 'price') {
                    Price::query()->updateOrCreate(
                        ['variant_id' => $row['variantId'], 'currency' => 'TRY'],
                        ['list_price' => $row['next']],
                    );

                    continue;
                }

                InventoryItem::query()->updateOrCreate(
                    ['variant_id' => $row['variantId'], 'warehouse_id' => $row['warehouseId']],
                    ['on_hand' => (int) $row['next']],
                );
            }
        });

        return count($rows);
    }

    /**
     * Etkilenecek satirlar ve yeni degerleri.
     *
     * `set` her secili varyanta yazar (satir yoksa yaratir). `percent` ve
     * `amount` yalnizca MEVCUT satirlari degistirir: olmayan bir fiyatin
     * %10'unu artirmak anlamsizdir ve sessizce 0 uretirdi.
     *
     * @param  list<int>  $productIds
     * @return list<array{variantId: int, warehouseId: int|null, sku: string, current: float|null, next: float}>
     */
    private function plan(array $productIds, string $field, string $mode, float $value): array
    {
        $warehouse = $field === 'stock'
            ? Warehouse::query()->orderByDesc('is_default')->orderBy('id')->first()
            : null;

        if ($field === 'stock' && $warehouse === null) {
            return [];
        }

        $query = DB::table('product_variants')
            ->whereIn('product_variants.product_id', $productIds)
            ->orderBy('product_variants.sku');

        $query = $field === 'price'
            ? $query
                ->leftJoin('prices', function ($join): void {
                    $join->on('prices.variant_id', '=', 'product_variants.id')
                        ->where('prices.currency', '=', 'TRY');
                })
                ->select(['product_variants.id', 'product_variants.sku', 'prices.list_price as current'])
            : $query
                ->leftJoin('inventory_items', function ($join) use ($warehouse): void {
                    $join->on('inventory_items.variant_id', '=', 'product_variants.id')
                        ->where('inventory_items.warehouse_id', '=', $warehouse?->getKey());
                })
                ->select(['product_variants.id', 'product_variants.sku', 'inventory_items.on_hand as current']);

        $rows = [];

        foreach ($query->get() as $variant) {
            /** @var stdClass $variant */
            $current = $variant->current === null ? null : (float) $variant->current;

            if ($current === null && $mode !== 'set') {
                continue;
            }

            $rows[] = [
                'variantId' => (int) $variant->id,
                'warehouseId' => $warehouse?->getKey(),
                'sku' => (string) $variant->sku,
                'current' => $current,
                'next' => $this->next($field, $mode, $current ?? 0.0, $value),
            ];
        }

        return $rows;
    }

    private function next(string $field, string $mode, float $current, float $value): float
    {
        $next = match ($mode) {
            'percent' => $current * (1 + $value / 100),
            'amount' => $current + $value,
            default => $value,
        };

        // Negatif stok ve negatif fiyat yok; stok tam sayi.
        $next = max(0.0, $next);

        return $field === 'stock' ? (float) (int) round($next) : round($next, 2);
    }

    /**
     * Para sunucuda bicimlenir — FRONTEND-PLAN §7.
     */
    private function display(string $field, ?float $amount): string
    {
        if ($amount === null) {
            return '—';
        }

        return $field === 'price'
            ? (string) Number::currency($amount, 'TRY', 'tr')
            : (string) (int) $amount;
    }
}
