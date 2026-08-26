<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Enums\ListingSyncState;
use App\Enums\ProcessingStatus;
use App\Enums\ProductStatus;
use App\Marketplaces\Contracts\SupportsProductSync;
use App\Marketplaces\Data\Enums\SyncDirection;
use App\Marketplaces\Data\ProductData;
use App\Marketplaces\Data\VariantData;
use App\Marketplaces\Support\Capability;
use App\Marketplaces\Support\Exceptions\UnsupportedCapabilityException;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ChannelConnection;
use App\Models\ChannelListing;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\SyncCursor;
use App\Models\SyncRun;
use App\Models\Warehouse;
use App\Support\Sync\ConnectionDriver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Pazaryerindeki ürünleri çekip kanonik ürün modellerine aktarır.
 */
final class ImportProducts
{
    public function __construct(
        private readonly ConnectionDriver $connectionDriver,
    ) {}

    /**
     * @return array{created: int, updated: int, matched: int, pages: int, total: int}
     */
    public function handle(ChannelConnection $connection, int $maxPages = 50): array
    {
        $driver = $this->connectionDriver->for($connection);

        if (! $driver instanceof SupportsProductSync) {
            throw UnsupportedCapabilityException::for(Capability::ProductSync, $driver);
        }

        $cursorRecord = SyncCursor::query()->firstOrNew([
            'connection_id' => $connection->getKey(),
            'resource' => 'products',
        ]);

        $watermark = $cursorRecord->watermark?->toDateTimeImmutable();
        $cursor = $cursorRecord->cursor;

        $stats = [
            'created' => 0,
            'updated' => 0,
            'matched' => 0,
            'pages' => 0,
            'total' => 0,
        ];

        $run = SyncRun::create([
            'connection_id' => $connection->getKey(),
            'resource' => 'products',
            'direction' => SyncDirection::Pull,
            'cursor_from' => $watermark?->format(DATE_ATOM),
            'started_at' => now(),
            'stats' => [],
            'status' => ProcessingStatus::Running,
        ]);

        try {
            $defaultWarehouse = Warehouse::query()->orderByDesc('is_default')->orderBy('id')->first();

            do {
                $page = $driver->pullProducts($watermark, $cursor);
                $stats['pages']++;

                foreach ($page->items as $productData) {
                    $result = $this->persist($connection, $productData, $defaultWarehouse);
                    $stats[$result]++;
                    $stats['total']++;
                }

                $cursor = $page->cursor;

                if ($page->watermark !== null && ($watermark === null || $page->watermark > $watermark)) {
                    $watermark = $page->watermark;
                }
            } while ($page->hasMore && $stats['pages'] < $maxPages);

            $cursorRecord->fill([
                'watermark' => $watermark,
                'cursor' => $page->hasMore ? $cursor : null,
            ])->save();

            $run->update([
                'status' => ProcessingStatus::Completed,
                'finished_at' => now(),
                'cursor_to' => $watermark?->format(DATE_ATOM),
                'stats' => $stats,
            ]);

            return $stats;
        } catch (Throwable $exception) {
            $run->update([
                'status' => ProcessingStatus::Failed,
                'finished_at' => now(),
                'stats' => $stats,
                'error' => ['class' => $exception::class, 'message' => $exception->getMessage()],
            ]);

            throw $exception;
        }
    }

    /**
     * @return 'created'|'matched'|'updated'
     */
    private function persist(
        ChannelConnection $connection,
        ProductData $productData,
        ?Warehouse $defaultWarehouse,
    ): string {
        return DB::transaction(function () use ($connection, $productData, $defaultWarehouse): string {
            $brandId = $this->resolveBrand($connection, $productData->brandId);
            $categoryId = $this->resolveCategory($connection, $productData->categoryId);

            /** @var list<VariantData> $variantsData */
            $variantsData = $productData->variants !== [] ? $productData->variants : [
                new VariantData(
                    reference: $productData->reference,
                    sku: $productData->reference,
                ),
            ];

            // Mevcut varyant kontrolü (SKU veya Barkod ile)
            $matchedVariant = null;
            foreach ($variantsData as $variantData) {
                $query = ProductVariant::query()->where('sku', $variantData->sku);
                if ($variantData->barcode !== null && $variantData->barcode !== '') {
                    $query->orWhere('barcode', $variantData->barcode);
                }

                $matchedVariant = $query->first();
                if ($matchedVariant !== null) {
                    break;
                }
            }

            if ($matchedVariant !== null) {
                // Ürün zaten var — Kanal listelemesini güncelle/oluştur
                ChannelListing::withoutEvents(function () use ($connection, $matchedVariant, $productData): void {
                    ChannelListing::updateOrCreate(
                        [
                            'connection_id' => $connection->getKey(),
                            'variant_id' => $matchedVariant->getKey(),
                        ],
                        [
                            'remote_id' => $matchedVariant->remoteId ?? $productData->remoteId,
                            'remote_status' => $productData->status?->value,
                            'sync_state' => ListingSyncState::Synced,
                            'last_pulled_at' => now(),
                        ]
                    );
                });

                return 'matched';
            }

            // Yeni ürün oluştur
            $product = Product::create([
                'name' => $productData->name !== '' ? $productData->name : $productData->reference,
                'description' => $productData->description,
                'brand_id' => $brandId,
                'category_id' => $categoryId,
                'status' => ProductStatus::Active,
                'attributes' => [],
            ]);

            foreach ($variantsData as $variantData) {
                $vatRate = 20.00;
                if ($variantData->vatRate !== null && is_numeric($variantData->vatRate)) {
                    $vatRate = (float) $variantData->vatRate;
                }

                $attributes = [];
                foreach ($variantData->attributes as $attr) {
                    $attributes[] = [
                        'code' => $attr->attributeCode,
                        'value' => $attr->value,
                    ];
                }

                $variant = ProductVariant::create([
                    'product_id' => $product->getKey(),
                    'sku' => $variantData->sku ?: $productData->reference,
                    'barcode' => $variantData->barcode,
                    'vat_rate' => $vatRate,
                    'attributes' => $attributes,
                ]);

                if ($defaultWarehouse !== null) {
                    InventoryItem::firstOrCreate(
                        [
                            'variant_id' => $variant->getKey(),
                            'warehouse_id' => $defaultWarehouse->getKey(),
                        ],
                        [
                            'on_hand' => 0,
                            'reserved' => 0,
                            'safety_stock' => 0,
                        ]
                    );
                }

                ChannelListing::withoutEvents(function () use ($connection, $variant, $variantData, $productData): void {
                    ChannelListing::create([
                        'connection_id' => $connection->getKey(),
                        'variant_id' => $variant->getKey(),
                        'remote_id' => $variantData->remoteId ?? $productData->remoteId,
                        'remote_status' => $productData->status?->value,
                        'sync_state' => ListingSyncState::Synced,
                        'last_pulled_at' => now(),
                    ]);
                });
            }

            foreach ($productData->images as $position => $imageUrl) {
                if ($imageUrl !== '') {
                    ProductImage::create([
                        'product_id' => $product->getKey(),
                        'variant_id' => null,
                        'url' => $imageUrl,
                        'position' => $position,
                    ]);
                }
            }

            return 'created';
        });
    }

    private function resolveBrand(ChannelConnection $connection, ?string $brandName): ?int
    {
        if ($brandName === null || trim($brandName) === '') {
            return null;
        }

        $brandName = trim($brandName);

        $mappedBrandId = DB::table('channel_brand_mappings')
            ->where('connection_id', $connection->getKey())
            ->where('remote_brand_id', $brandName)
            ->value('brand_id');

        if ($mappedBrandId !== null) {
            return (int) $mappedBrandId;
        }

        $brand = Brand::firstOrCreate(
            ['name' => $brandName],
            ['slug' => Str::slug($brandName) ?: Str::random(8)]
        );

        return $brand->getKey();
    }

    private function resolveCategory(ChannelConnection $connection, ?string $categoryId): ?int
    {
        if ($categoryId === null || trim($categoryId) === '') {
            return null;
        }

        $remoteId = trim($categoryId);

        $mappedCategoryId = DB::table('channel_category_mappings')
            ->where('connection_id', $connection->getKey())
            ->where('remote_category_id', $remoteId)
            ->value('category_id');

        if ($mappedCategoryId !== null) {
            return (int) $mappedCategoryId;
        }

        if (is_numeric($remoteId)) {
            $category = Category::find((int) $remoteId);
            if ($category !== null) {
                return $category->getKey();
            }
        }

        return null;
    }
}
