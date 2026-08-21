<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\WarehouseRequest;
use App\Models\InventoryItem;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Depo tanimlari.
 *
 * Uc kural sunucuda zorlanir, hicbiri arayuze birakilmaz:
 *  1. Her zaman en az bir depo kalir.
 *  2. Varsayilan depo silinemez ve varsayilanligi dogrudan kaldirilamaz —
 *     baska bir depo varsayilan yapilarak tasinir.
 *  3. Icinde stok bulunan depo silinemez: FK cascade envanter satirlarini
 *     sessizce goturur, bu bir veri kaybidir.
 */
class WarehouseController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Warehouse::class);

        // Warehouse modelinde `inventoryItems` iliskisi yok (model dosyalari bu
        // is kumesinin disinda), bu yuzden sayimlar tek bir gruplama sorgusuyla
        // toplaniyor. Depo sayisi bir avuctur; N+1 riski yok.
        $stats = InventoryItem::query()
            ->selectRaw('warehouse_id, count(*) as item_count, coalesce(sum(on_hand), 0) as on_hand_total')
            ->groupBy('warehouse_id')
            ->get()
            ->keyBy('warehouse_id');

        return Inertia::render('inventory/warehouses/index', [
            'warehouses' => Warehouse::query()
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get()
                ->map(function (Warehouse $warehouse) use ($stats): array {
                    $row = $stats->get($warehouse->getKey());

                    return [
                        'id' => $warehouse->getKey(),
                        'name' => $warehouse->name,
                        'code' => $warehouse->code,
                        'isDefault' => $warehouse->is_default,
                        'address' => [
                            'line' => $warehouse->address['line'] ?? null,
                            'city' => $warehouse->address['city'] ?? null,
                            'district' => $warehouse->address['district'] ?? null,
                            'postalCode' => $warehouse->address['postal_code'] ?? null,
                        ],
                        'itemCount' => $row === null ? 0 : (int) $row->getAttribute('item_count'),
                        'onHandTotal' => $row === null ? 0 : (int) $row->getAttribute('on_hand_total'),
                    ];
                })
                ->all(),
        ]);
    }

    public function store(WarehouseRequest $request): RedirectResponse
    {
        Gate::authorize('create', Warehouse::class);

        $data = $this->payload($request);

        // Ilk depo kosulsuz varsayilandir: varsayilansiz bir kurulum, katalog
        // tarafindaki satir ici stok duzenlemesini de calisamaz hale getirir.
        $data['is_default'] = Warehouse::query()->doesntExist() || $data['is_default'];

        DB::transaction(function () use ($data): void {
            if ($data['is_default']) {
                Warehouse::query()->where('is_default', true)->update(['is_default' => false]);
            }

            Warehouse::create($data);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Depo eklendi.']);

        return back();
    }

    public function update(WarehouseRequest $request, Warehouse $warehouse): RedirectResponse
    {
        Gate::authorize('update', $warehouse);

        $data = $this->payload($request);

        if ($warehouse->is_default && ! $data['is_default']) {
            throw ValidationException::withMessages([
                'is_default' => 'Varsayılan depo işareti kaldırılamaz. Başka bir depoyu varsayılan yaparak taşıyın.',
            ]);
        }

        DB::transaction(function () use ($data, $warehouse): void {
            if ($data['is_default']) {
                Warehouse::query()
                    ->where('is_default', true)
                    ->whereKeyNot($warehouse->getKey())
                    ->update(['is_default' => false]);
            }

            $warehouse->update($data);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Depo güncellendi.']);

        return back();
    }

    public function destroy(Warehouse $warehouse): RedirectResponse
    {
        Gate::authorize('delete', $warehouse);

        if ($warehouse->is_default) {
            throw ValidationException::withMessages([
                'warehouse' => 'Varsayılan depo silinemez. Önce başka bir depoyu varsayılan yapın.',
            ]);
        }

        if (Warehouse::query()->count() <= 1) {
            throw ValidationException::withMessages([
                'warehouse' => 'En az bir depo kalmalı; son depo silinemez.',
            ]);
        }

        $withStock = InventoryItem::query()
            ->where('warehouse_id', $warehouse->getKey())
            ->where('on_hand', '!=', 0)
            ->count();

        if ($withStock > 0) {
            throw ValidationException::withMessages([
                'warehouse' => "Bu depoda {$withStock} varyantın stoğu duruyor. Silmek envanter kayıtlarını da siler; önce stoğu sıfırlayın veya başka depoya taşıyın.",
            ]);
        }

        $warehouse->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Depo silindi.']);

        return back();
    }

    /**
     * @return array{name: string, code: string, is_default: bool, address: array<string, string>|null}
     */
    private function payload(WarehouseRequest $request): array
    {
        /** @var array<string, string|null> $address */
        $address = array_filter($request->validated('address', []), fn (mixed $value): bool => $value !== null && $value !== '');

        return [
            'name' => (string) $request->validated('name'),
            'code' => (string) $request->validated('code'),
            'is_default' => $request->boolean('is_default'),
            'address' => $address === [] ? null : $address,
        ];
    }
}
