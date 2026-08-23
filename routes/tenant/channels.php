<?php

declare(strict_types=1);

use App\Http\Controllers\Channels\AppStoreController;
use App\Http\Controllers\Channels\ConnectionController;
use App\Http\Controllers\Channels\MappingController;
use Illuminate\Support\Facades\Route;

/*
| Kanal baglantilari — pazaryeri kimlik bilgileri, saglik durumu, webhook token.
| `routes/tenant.php` icinden ['auth','verified'] grubunda yuklenir;
| burada middleware TEKRAR TANIMLANMAZ.
*/

Route::prefix('channels')->group(function (): void {
    /*
    | Uygulama magazasi — tek ekran. Detay sayfasi yok: kart dogrudan kurulum
    | cekmecesini acar. Eski `channels/connections` adresi yer imlerini
    | kirmamak icin vitrine dusuyor.
    */
    Route::get('apps', [AppStoreController::class, 'index'])->name('apps.index');
    Route::get('connections', [ConnectionController::class, 'index'])->name('connections.index');

    Route::post('connections', [ConnectionController::class, 'store'])->name('connections.store');
    Route::patch('connections/{connection}', [ConnectionController::class, 'update'])->name('connections.update');
    Route::delete('connections/{connection}', [ConnectionController::class, 'destroy'])->name('connections.destroy');

    // Elle saglik kontrolu; zamanlanmis kontrol bu fazda yok.
    Route::post('connections/{connection}/health', [ConnectionController::class, 'health'])->name('connections.health');

    /*
    | Esleme sihirbazi — FRONTEND-PLAN §4.1. Sihirbaz tek sayfadir; adimlar
    | istemcide gecilir, sunucuya yalnizca kayit icin gidilir. Her adimin kendi
    | ucu var cunku her adim ayri kaydedilebilmeli: sihirbaz uzundur, yarida
    | birakilir ve gunler sonra devam ettirilir.
    */
    Route::prefix('mapping')->name('mapping.')->group(function (): void {
        Route::get('/', [MappingController::class, 'index'])->name('index');
        Route::get('{connection}/{category}', [MappingController::class, 'show'])->name('show');
        Route::post('{connection}/{category}/category', [MappingController::class, 'storeCategory'])->name('category');
        Route::post('{connection}/{category}/attributes', [MappingController::class, 'storeAttributes'])->name('attributes');
        Route::post('{connection}/{category}/values', [MappingController::class, 'storeValues'])->name('values');
        Route::post('{connection}/{category}/brands', [MappingController::class, 'storeBrands'])->name('brands');
    });
});
