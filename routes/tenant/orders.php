<?php

declare(strict_types=1);

use App\Http\Controllers\Orders\OrderController;
use Illuminate\Support\Facades\Route;

/*
| Siparisler, kargo paketleri, iadeler.
| `routes/tenant.php` icinden ['auth','verified','license'] grubunda yuklenir;
| burada middleware TEKRAR TANIMLANMAZ.
|
| Bu faz yalnizca OKUMA: statu guncelleme, kargo bildirme ve fatura gonderme
| outbox motoruna baglidir ve burada route'u yoktur.
*/
Route::prefix('orders')->group(function (): void {
    Route::get('/', [OrderController::class, 'index'])->name('orders.index');

    // Route model binding yok: kanonik Order modeli henuz cekirdek tarafinda
    // yaratilmadi (rapora bakin), controller id ile sorguluyor.
    Route::get('{order}', [OrderController::class, 'show'])
        ->whereNumber('order')
        ->name('orders.show');
});
