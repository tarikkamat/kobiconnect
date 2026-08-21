<?php

declare(strict_types=1);

use App\Http\Controllers\Inventory\StockController;
use App\Http\Controllers\Inventory\WarehouseController;
use Illuminate\Support\Facades\Route;

/*
| Envanter ve depolar.
| `routes/tenant.php` icinden ['auth','verified','license'] grubunda yuklenir;
| burada middleware TEKRAR TANIMLANMAZ.
*/

Route::prefix('inventory')->group(function (): void {
    // Varyant x depo matrisi. Hucre anahtari (variant, warehouse) ciftidir;
    // satir henuz yoksa ilk yazmada yaratilir.
    Route::get('stock', [StockController::class, 'index'])->name('stock.index');
    Route::patch('stock/{variant}/{warehouse}', [StockController::class, 'update'])->name('stock.update');

    Route::get('warehouses', [WarehouseController::class, 'index'])->name('warehouses.index');
    Route::post('warehouses', [WarehouseController::class, 'store'])->name('warehouses.store');
    Route::patch('warehouses/{warehouse}', [WarehouseController::class, 'update'])->name('warehouses.update');
    Route::delete('warehouses/{warehouse}', [WarehouseController::class, 'destroy'])->name('warehouses.destroy');
});
