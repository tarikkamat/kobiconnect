<?php

declare(strict_types=1);

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\VariantPriceRequest;
use App\Http\Requests\Catalog\VariantStockRequest;
use App\Models\InventoryItem;
use App\Models\Price;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Satir ici stok/fiyat duzenlemesi. Istemci tarafinda `optimistic` visit ile
 * cagrilir; 422 donerse Inertia degeri kendisi geri alir — FRONTEND-PLAN §3.
 */
class VariantController extends Controller
{
    public function stock(VariantStockRequest $request, ProductVariant $variant): RedirectResponse
    {
        Gate::authorize('updateStock', $variant);

        // ponytail: tek (varsayilan) depo uzerinden duzenleme. Depo secimi
        // gerektiginde warehouse_id istekten gelir.
        $warehouse = Warehouse::query()->orderByDesc('is_default')->orderBy('id')->first();

        if ($warehouse === null) {
            throw ValidationException::withMessages([
                'on_hand' => 'Stok girilebilmesi için önce bir depo tanımlanmalı.',
            ]);
        }

        InventoryItem::query()->updateOrCreate(
            ['variant_id' => $variant->getKey(), 'warehouse_id' => $warehouse->getKey()],
            ['on_hand' => $request->integer('on_hand')],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Stok güncellendi.']);

        return back();
    }

    public function price(VariantPriceRequest $request, ProductVariant $variant): RedirectResponse
    {
        Gate::authorize('updatePrice', $variant);

        // ponytail: varyant basina tek gecerli TRY fiyati varsayilir; donemsel
        // fiyatlandirma (valid_from/valid_to) geldiginde burasi degisir.
        Price::query()->updateOrCreate(
            ['variant_id' => $variant->getKey(), 'currency' => 'TRY'],
            ['list_price' => $request->float('list_price')],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Fiyat güncellendi.']);

        return back();
    }
}
