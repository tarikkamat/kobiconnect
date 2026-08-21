<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StockAdjustmentRequest;
use App\Models\InventoryItem;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Varyant x depo stok matrisi.
 *
 * Katalogdaki satir ici duzenleme tek varsayilan depo varsayar; cok depolu
 * gorunum burasidir. Ikisi ayni `inventory_items` satirlarina yazar, farkli
 * ekranlardir.
 *
 * `available` (= `on_hand - reserved`) veritabaninda generated column'dur:
 * okunur, asla yazilmaz. Arayuz bunu salt-okunur gosterir, FormRequest
 * gonderilmesini reddeder — tek dogruluk kaynagi veritabanidir.
 */
class StockController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ProductVariant::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'low' => ['nullable', 'boolean'],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));
        $low = (bool) ($filters['low'] ?? false);

        $warehouses = Warehouse::query()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'is_default']);

        $variants = ProductVariant::query()
            ->with(['product:id,name', 'inventoryItems'])
            ->when($search !== '', fn (Builder $query) => $query->where(
                fn (Builder $group) => $group
                    ->where('sku', 'ilike', '%'.$search.'%')
                    ->orWhere('barcode', 'ilike', '%'.$search.'%')
                    ->orWhereHas('product', fn (Builder $product) => $product->where('name', 'ilike', '%'.$search.'%')),
            ))
            // Kritik stok: kullanilabilir miktar guvenlik stogunun altina inmis.
            ->when($low, fn (Builder $query) => $query->whereHas(
                'inventoryItems',
                fn (Builder $items) => $items->whereColumn('available', '<=', 'safety_stock'),
            ))
            ->orderBy('sku')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (ProductVariant $variant): array => [
                'id' => $variant->getKey(),
                'sku' => $variant->sku,
                'barcode' => $variant->barcode,
                'productId' => $variant->product_id,
                'productName' => $variant->product->name,
                // Hucreler depo sirasiyla; eksik satir sifirla doldurulur ki
                // istemci tarafinda eslestirme yapmak gerekmesin.
                'cells' => $warehouses
                    ->map(function (Warehouse $warehouse) use ($variant): array {
                        $item = $variant->inventoryItems->firstWhere('warehouse_id', $warehouse->getKey());

                        if ($item === null) {
                            return [
                                'warehouseId' => $warehouse->getKey(),
                                'onHand' => 0,
                                'reserved' => 0,
                                'available' => 0,
                                'safetyStock' => 0,
                                'low' => false,
                            ];
                        }

                        return [
                            'warehouseId' => $warehouse->getKey(),
                            'onHand' => $item->on_hand,
                            'reserved' => $item->reserved,
                            'available' => $item->available,
                            'safetyStock' => $item->safety_stock,
                            'low' => $item->available <= $item->safety_stock,
                        ];
                    })
                    ->all(),
            ]);

        return Inertia::render('inventory/stock/index', [
            'variants' => Inertia::scroll($variants),
            'warehouses' => $warehouses
                ->map(fn (Warehouse $warehouse): array => [
                    'id' => $warehouse->getKey(),
                    'name' => $warehouse->name,
                    'code' => $warehouse->code,
                    'isDefault' => $warehouse->is_default,
                ])
                ->all(),
            'filters' => ['search' => $search, 'low' => $low],
        ]);
    }

    public function update(StockAdjustmentRequest $request, ProductVariant $variant, Warehouse $warehouse): RedirectResponse
    {
        // Stok yetkisi katalog metnini duzenleme yetkisinden ayridir: depo
        // rolu stogu gunceller ama urun bilgisine dokunamaz (§4.3).
        Gate::authorize('updateStock', $variant);

        $item = InventoryItem::query()->firstOrNew(
            ['variant_id' => $variant->getKey(), 'warehouse_id' => $warehouse->getKey()],
            ['on_hand' => 0, 'reserved' => 0, 'safety_stock' => 0],
        );

        $before = (int) $item->on_hand;

        $changes = array_filter(
            $request->safe()->only(['on_hand', 'reserved', 'safety_stock']),
            fn (mixed $value): bool => $value !== null,
        );

        $item->fill($changes)->save();

        if (array_key_exists('on_hand', $changes) && $before !== (int) $item->on_hand) {
            $this->recordAdjustment($request, $variant, $warehouse, $before, (int) $item->on_hand);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Stok güncellendi.']);

        return back();
    }

    /**
     * Duzeltme izi: kim, ne zaman, ne kadar, neden.
     *
     * ponytail: tam bir envanter hareket defteri (`inventory_movements`) DEGIL.
     * Bu fazda satis/iade/senkron kaynakli hareketler zaten `on_hand`'i
     * dogrudan yaziyor; yalnizca elle duzeltmeyi defterlestirmek yanlis bir
     * butunluk hissi verirdi. Iz yapisal log kaydidir. Stok gecmisi ekranda
     * gosterilecekse `inventory_adjustments` tablosu buraya girer ve bu metot
     * onu yazar.
     */
    private function recordAdjustment(
        StockAdjustmentRequest $request,
        ProductVariant $variant,
        Warehouse $warehouse,
        int $before,
        int $after,
    ): void {
        $user = $request->user();

        Log::info('inventory.stock_adjusted', [
            'tenant' => tenant('id'),
            'user_id' => $user?->getKey(),
            'user_name' => $user?->name,
            'variant_id' => $variant->getKey(),
            'sku' => $variant->sku,
            'warehouse_id' => $warehouse->getKey(),
            'warehouse_code' => $warehouse->code,
            'on_hand_before' => $before,
            'on_hand_after' => $after,
            'delta' => $after - $before,
            'reason' => (string) $request->validated('reason'),
            'at' => now()->toIso8601String(),
        ]);
    }
}
