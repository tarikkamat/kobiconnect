<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\ChannelListing;
use App\Models\InventoryItem;
use App\Models\Price;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

/**
 * Yeni urun + varyantlari ve gorselleri.
 */
final class CreateProduct
{
    /**
     * @param  array{
     *     name: string,
     *     description?: string|null,
     *     brand_id?: int|null,
     *     category_id?: int|null,
     *     status: string,
     *     variants: list<array{
     *         sku: string,
     *         barcode?: string|null,
     *         attributes?: array<string, mixed>|null,
     *         image_url?: string|null,
     *         list_price?: float|string|null,
     *         on_hand?: int|string|null,
     *     }>,
     *     channel_ids?: list<int>|null,
     *     images?: list<array{url: string, position?: int|null}>|null
     * }  $data
     */
    public function __invoke(array $data): Product
    {
        return DB::transaction(function () use ($data): Product {
            $product = Product::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'brand_id' => $data['brand_id'] ?? null,
                'category_id' => $data['category_id'] ?? null,
                'status' => $data['status'],
            ]);

            // Stok her zaman bir depoya yazilir; varsayilan depo yoksa
            // varyant stoksuz dogar (envanter ekranindan girilebilir).
            $warehouse = Warehouse::query()->orderByDesc('is_default')->orderBy('id')->first();

            foreach ($data['variants'] as $row) {
                $variant = ProductVariant::create([
                    'product_id' => $product->getKey(),
                    'sku' => $row['sku'],
                    'barcode' => ($row['barcode'] ?? '') === '' ? null : $row['barcode'],
                    'attributes' => $row['attributes'] ?? [],
                ]);

                if (($row['list_price'] ?? null) !== null && $row['list_price'] !== '') {
                    Price::create([
                        'variant_id' => $variant->getKey(),
                        'currency' => 'TRY',
                        'list_price' => (float) $row['list_price'],
                    ]);
                }

                if ($warehouse !== null && ($row['on_hand'] ?? null) !== null && $row['on_hand'] !== '') {
                    InventoryItem::create([
                        'variant_id' => $variant->getKey(),
                        'warehouse_id' => $warehouse->getKey(),
                        'on_hand' => (int) $row['on_hand'],
                    ]);
                }

                if (! empty($row['image_url'])) {
                    ProductImage::create([
                        'product_id' => $product->getKey(),
                        'variant_id' => $variant->getKey(),
                        'url' => $row['image_url'],
                        'position' => 0,
                    ]);
                }

                if (! empty($data['channel_ids'])) {
                    foreach ($data['channel_ids'] as $channelId) {
                        ChannelListing::firstOrCreate([
                            'connection_id' => (int) $channelId,
                            'variant_id' => $variant->getKey(),
                        ]);
                    }
                }
            }

            if (! empty($data['images'])) {
                foreach ($data['images'] as $idx => $img) {
                    if (! empty($img['url'])) {
                        ProductImage::create([
                            'product_id' => $product->getKey(),
                            'url' => $img['url'],
                            'position' => $img['position'] ?? $idx,
                        ]);
                    }
                }
            }

            return $product;
        });
    }
}
