<?php

declare(strict_types=1);

use App\Http\Controllers\Reports\ReportController;
use Illuminate\Support\Facades\Route;

/*
| Finans ve Satış Raporları.
| `routes/tenant.php` icinden ['auth','verified'] grubunda yuklenir.
*/
Route::prefix('reports')->group(function (): void {
    Route::get('/', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/channels', [ReportController::class, 'channels'])->name('reports.channels');
    Route::get('/products', [ReportController::class, 'products'])->name('reports.products');
    Route::get('/penalties', [ReportController::class, 'penalties'])->name('reports.penalties');
    Route::get('/orders', [ReportController::class, 'orders'])->name('reports.orders');
});
