<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Actions\Licensing\CheckQuota;
use App\Models\InventoryItem;
use App\Models\License;
use App\Models\Price;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\UsageCounter;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Yeni urun + varyantlari.
 *
 * Urun kotasi BURADA, Action seviyesinde kontrol edilir — middleware'de degil
 * (BACKEND-PLAN §3.2): "Baslangic planinizin urun kotasi doldu (1000/1000)"
 * cumlesini ancak islemi yapan taraf kurabilir.
 */
final class CreateProduct
{
    public const string PRODUCT_METRIC = 'products.max';

    public function __construct(private readonly CheckQuota $checkQuota) {}

    /**
     * @param  array{
     *     name: string,
     *     description?: string|null,
     *     brand_id?: int|null,
     *     category_id?: int|null,
     *     status: string,
     *     variants: list<array{sku: string, barcode?: string|null, list_price?: float|string|null, on_hand?: int|string|null}>
     * }  $data
     */
    public function __invoke(License $license, array $data): Product
    {
        $this->reconcileProductCounter($license->tenant_id);

        // Kontrol eder *ve* sayaci artirir; kota dolmussa 402 ile anlamli mesaj.
        ($this->checkQuota)($license, self::PRODUCT_METRIC);

        try {
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
                }

                return $product;
            });
        } catch (Throwable $exception) {
            // Kota sayaci artmis olurdu; sizan bir birim musteriyi kalici
            // olarak engeller (InviteUser ile ayni gerekce).
            UsageCounter::record($license->tenant_id, self::PRODUCT_METRIC, -1);

            throw $exception;
        }
    }

    /**
     * ponytail: `usage_counters` urun satiri bugune kadar hicbir yerde
     * artirilmiyordu; ice aktarma ve seed ile gelen urunler sayilmiyor. Tek
     * dogruluk kaynagi `products` tablosu, o yuzden kapiya girmeden once sayaci
     * gercege esitliyoruz (InviteUser::reconcileSeatCounter ile ayni desen).
     * Senkron da urun yaratmaya baslarsa bu tazeleme orada da gerekir.
     */
    private function reconcileProductCounter(string $tenantId): void
    {
        $drift = Product::query()->count() - UsageCounter::valueFor($tenantId, self::PRODUCT_METRIC);

        if ($drift !== 0) {
            UsageCounter::record($tenantId, self::PRODUCT_METRIC, $drift);
        }
    }
}
