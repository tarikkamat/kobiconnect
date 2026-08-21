<?php

declare(strict_types=1);

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\BulkEditVariants;
use App\Actions\Catalog\CreateProduct;
use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\ProductBulkEditRequest;
use App\Http\Requests\Catalog\ProductStoreRequest;
use App\Http\Requests\Catalog\ProductUpdateRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ChannelConnection;
use App\Models\ChannelListing;
use App\Models\InventoryItem;
use App\Models\License;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\UsageCounter;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Number;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    /**
     * Arayuz metinleri Turkce, kanonik enum degerleri degil — FRONTEND-PLAN §7.
     *
     * @var array<string, string>
     */
    private const array STATUS_LABELS = [
        'draft' => 'Taslak',
        'active' => 'Aktif',
        'archived' => 'Arşivlendi',
    ];

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Product::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(ProductStatus::class)],
            'stock' => ['nullable', 'in:var,yok'],
            'connection' => ['nullable', 'integer'],
            'sort' => ['nullable', 'in:name,created_at'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? null;
        $stock = $filters['stock'] ?? null;
        $connection = isset($filters['connection']) ? (int) $filters['connection'] : null;
        $sort = $filters['sort'] ?? 'created_at';
        $direction = $filters['direction'] ?? 'desc';

        $products = Product::query()
            ->with([
                'brand:id,name',
                'variants:id,product_id',
                'variants.inventoryItems:id,variant_id,available',
                'variants.prices:id,variant_id,currency,list_price',
            ])
            ->when($search !== '', fn (Builder $query) => $query->search($search))
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status))
            ->when($stock === 'var', fn (Builder $query) => $query->whereHas(
                'variants.inventoryItems',
                fn (Builder $items) => $items->where('available', '>', 0),
            ))
            ->when($stock === 'yok', fn (Builder $query) => $query->whereDoesntHave(
                'variants.inventoryItems',
                fn (Builder $items) => $items->where('available', '>', 0),
            ))
            ->when($connection !== null, fn (Builder $query) => $query->whereHas(
                'variants.listings',
                fn (Builder $listings) => $listings->where('connection_id', $connection),
            ))
            ->orderBy($sort, $direction)
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Product $product): array => [
                'id' => $product->getKey(),
                'name' => $product->name,
                'status' => $product->status->value,
                'statusLabel' => self::STATUS_LABELS[$product->status->value],
                'brand' => $product->brand?->name,
                'variantCount' => $product->variants->count(),
                'stock' => $this->availableStock($product->variants),
                'price' => $this->lowestPrice($product->variants),
                // Tarih de sunucuda bicimlenir, Europe/Istanbul — FRONTEND-PLAN §7.
                'createdAt' => $product->created_at?->timezone('Europe/Istanbul')->format('d.m.Y'),
            ]);

        return Inertia::render('catalog/products/index', [
            // Inertia::scroll() birlestirme davranisini ve sayfalama meta'sini
            // <InfiniteScroll> icin normalize eder.
            'products' => Inertia::scroll($products),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'stock' => $stock,
                'connection' => $connection,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'statuses' => $this->statusOptions(),
            'connections' => ChannelConnection::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Product::class);

        return Inertia::render('catalog/products/create', [
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
            'categories' => Category::query()->orderBy('path')->get(['id', 'name']),
            'statuses' => $this->statusOptions(),
        ]);
    }

    public function store(ProductStoreRequest $request, CreateProduct $createProduct): RedirectResponse
    {
        Gate::authorize('create', Product::class);

        /** @var array{name: string, description?: string|null, brand_id?: int|null, category_id?: int|null, status: string, variants: list<array{sku: string, barcode?: string|null, list_price?: float|string|null, on_hand?: int|string|null}>} $data */
        $data = $request->validated();

        // Kota kapisi Action'in icinde; middleware'de degil (BACKEND-PLAN §3.2).
        $product = $createProduct($this->license(), $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ürün oluşturuldu.']);

        return to_route('products.show', ['product' => $product->getKey()]);
    }

    public function show(Product $product): Response
    {
        Gate::authorize('view', $product);

        $product->load(['brand:id,name', 'category:id,name', 'variants.inventoryItems', 'variants.prices']);

        // Satir ici stok duzenlemesi tek depoya baglidir; cok depolu duzenleme
        // gerektiginde bu secim varyant satirina tasinir.
        $warehouse = Warehouse::query()->orderByDesc('is_default')->orderBy('id')->first();

        return Inertia::render('catalog/products/show', [
            'product' => [
                'id' => $product->getKey(),
                'name' => $product->name,
                'description' => $product->description,
                'brandId' => $product->brand_id,
                'categoryId' => $product->category_id,
                'status' => $product->status->value,
                'statusLabel' => self::STATUS_LABELS[$product->status->value],
                // Silme uyarisinin dayanagi: bu urun pazaryerinde yayinda olabilir.
                'listingCount' => $this->listingCount($product),
            ],
            'variants' => $product->variants
                ->map(fn (ProductVariant $variant): array => $this->variantRow($variant, $warehouse))
                ->all(),
            // Gorseller sekmesine inilmeden cekilmez — <WhenVisible>.
            'images' => Inertia::optional(fn (): array => $product->images()
                ->orderBy('position')
                ->get()
                ->map(fn (ProductImage $image): array => [
                    'id' => $image->getKey(),
                    'url' => $image->url,
                    'variantId' => $image->variant_id,
                    'position' => $image->position,
                ])
                ->all()),
            'warehouse' => $warehouse === null ? null : [
                'id' => $warehouse->getKey(),
                'name' => $warehouse->name,
            ],
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
            'categories' => Category::query()->orderBy('path')->get(['id', 'name']),
            'statuses' => $this->statusOptions(),
        ]);
    }

    public function update(ProductUpdateRequest $request, Product $product): RedirectResponse
    {
        Gate::authorize('update', $product);

        $product->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ürün güncellendi.']);

        return back();
    }

    /**
     * Silme sessiz degildir: urun bir pazaryerinde yayindaysa listeleme sayisi
     * geri bildirilir ve kullanicidan acik onay istenir. Onay olmadan silinmez.
     */
    public function destroy(Request $request, Product $product): RedirectResponse
    {
        Gate::authorize('delete', $product);

        $listings = $this->listingCount($product);

        if ($listings > 0 && ! $request->boolean('acknowledge_listings')) {
            throw ValidationException::withMessages([
                'acknowledge_listings' => "Bu ürünün {$listings} kanal listelemesi var; ürün pazaryerinde yayında olabilir. Silmek için uyarıyı onaylayın.",
            ]);
        }

        $product->delete();

        // Kota sayaci gercege yakin kalmali; aksi halde Lisans & Kullanim
        // ekrani silinmis urunleri saymaya devam eder.
        if (($license = $this->license(abortIfMissing: false)) !== null) {
            UsageCounter::record($license->tenant_id, CreateProduct::PRODUCT_METRIC, -1);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ürün silindi.']);

        return to_route('products.index');
    }

    /**
     * Toplu fiyat/stok degisikliginin ONIZLEMESI — FRONTEND-PLAN §4.5.
     *
     * Sayfa gezinmesi olmadan cagrilir (`useHttp`), bu yuzden JSON doner:
     * kullanici kac satirin degisecegini ve ornek sonucu uygulamadan once gorur.
     */
    public function bulkPreview(ProductBulkEditRequest $request, BulkEditVariants $bulkEdit): JsonResponse
    {
        Gate::authorize('update', Product::class);

        return response()->json($bulkEdit->preview(
            array_values(array_map(intval(...), $request->array('product_ids'))),
            $request->string('field')->toString(),
            $request->string('mode')->toString(),
            $request->float('value'),
        ));
    }

    public function bulkUpdate(ProductBulkEditRequest $request, BulkEditVariants $bulkEdit): RedirectResponse
    {
        Gate::authorize('update', Product::class);

        $field = $request->string('field')->toString();

        $written = $bulkEdit->apply(
            array_values(array_map(intval(...), $request->array('product_ids'))),
            $field,
            $request->string('mode')->toString(),
            $request->float('value'),
        );

        Inertia::flash('toast', [
            'type' => $written === 0 ? 'error' : 'success',
            'message' => $written === 0
                ? 'Hiçbir satır değişmedi. Yüzde ve tutar değişimi yalnızca mevcut '.($field === 'price' ? 'fiyatları' : 'stokları').' günceller.'
                : "{$written} varyant güncellendi.",
        ]);

        return back();
    }

    private function listingCount(Product $product): int
    {
        return ChannelListing::query()
            ->whereIn('variant_id', $product->variants()->select('id'))
            ->count();
    }

    /**
     * Panel route'lari `license` middleware'i ile korunur, dolayisiyla buraya
     * lisanssiz gelinmez; yine de sessizce devam etmek yerine 402 veriyoruz.
     */
    private function license(bool $abortIfMissing = true): ?License
    {
        $tenant = tenant();

        $license = $tenant === null
            ? null
            : License::query()->with('plan')->where('tenant_id', $tenant->getTenantKey())->first();

        if ($license === null && $abortIfMissing) {
            abort(402, 'Aboneliğiniz aktif değil. Devam etmek için lütfen planınızı yenileyin.');
        }

        return $license;
    }

    /**
     * @return array{id: int, sku: string, barcode: string|null, onHand: int, available: int, price: float|null, priceFormatted: string|null}
     */
    private function variantRow(ProductVariant $variant, ?Warehouse $warehouse): array
    {
        $item = $warehouse === null ? null : $variant->inventoryItems->first(
            fn (InventoryItem $inventoryItem): bool => $inventoryItem->warehouse_id === $warehouse->getKey(),
        );

        $price = $variant->prices->firstWhere('currency', 'TRY');

        return [
            'id' => $variant->getKey(),
            'sku' => $variant->sku,
            'barcode' => $variant->barcode,
            'onHand' => $item === null ? 0 : $item->on_hand,
            'available' => (int) $variant->inventoryItems->sum('available'),
            'price' => $price === null ? null : (float) $price->list_price,
            'priceFormatted' => $price === null ? null : $this->money((float) $price->list_price),
        ];
    }

    /**
     * @param  Collection<int, ProductVariant>|\Illuminate\Database\Eloquent\Collection<int, ProductVariant>  $variants
     */
    private function availableStock($variants): int
    {
        return (int) $variants->sum(
            fn (ProductVariant $variant): int => (int) $variant->inventoryItems->sum('available'),
        );
    }

    /**
     * @param  Collection<int, ProductVariant>|\Illuminate\Database\Eloquent\Collection<int, ProductVariant>  $variants
     */
    private function lowestPrice($variants): ?string
    {
        $lowest = $variants
            ->flatMap(fn (ProductVariant $variant) => $variant->prices)
            ->min('list_price');

        return $lowest === null ? null : $this->money((float) $lowest);
    }

    /**
     * Para birimi sunucuda bicimlendirilir — FRONTEND-PLAN §7.
     */
    private function money(float $amount): string
    {
        return (string) Number::currency($amount, 'TRY', 'tr');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return array_map(
            fn (ProductStatus $status): array => [
                'value' => $status->value,
                'label' => self::STATUS_LABELS[$status->value],
            ],
            ProductStatus::cases(),
        );
    }
}
