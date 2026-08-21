<?php

declare(strict_types=1);

use App\Http\Controllers\Channels\AppStoreController;
use App\Http\Controllers\Channels\ConnectionController;
use App\Http\Controllers\Channels\ListingController;
use App\Http\Controllers\Channels\MappingController;
use App\Http\Controllers\Channels\MatchInboxController;
use Illuminate\Support\Facades\Route;

/*
| Kanal baglantilari — pazaryeri kimlik bilgileri, saglik durumu, webhook token.
| `routes/tenant.php` icinden ['auth','verified','license'] grubunda yuklenir;
| burada middleware TEKRAR TANIMLANMAZ.
*/

Route::prefix('channels')->group(function (): void {
    /*
    | Uygulama magazasi — vitrin ve uygulama detayi. Baglantilarin OKUMA tarafi
    | buradadir: musteri once uygulamayi secer, sonra kurar. Eski
    | `channels/connections` adresi yer imlerini kirmamak icin vitrine dusuyor.
    */
    Route::get('apps', [AppStoreController::class, 'index'])->name('apps.index');
    Route::get('apps/{app}', [AppStoreController::class, 'show'])->name('apps.show');
    // `route()` sart: tenant path prefix'ini URL::defaults uretir, elle yazilan
    // '/channels/apps' tenant'i dusururdu.
    Route::get('connections', fn () => redirect()->route('apps.index'));

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

    /*
    | Varyant x kanal matrisi. Salt okuma: bu ekran duzeltmeyi kendisi yapmaz,
    | duzeltme yoluna GOTURUR (urun sayfasi, islem kuyrugu, on eslesme kutusu).
    */
    Route::get('listings', [ListingController::class, 'index'])->name('listings.index');

    /*
    | On eslesme gelen kutusu — HEPSIBURADA.md §3 H10.
    | Karar verilmeyen urun SATILMAZ, bu yuzden bu bir gelen kutusudur, bir
    | ayar ekrani degil. Tekil ve toplu karar AYNI ucu kullanir: tekil karar,
    | tek elemanli bir toplu karardir.
    */
    Route::prefix('matches')->name('matches.')->group(function (): void {
        Route::get('/', [MatchInboxController::class, 'index'])->name('index');
        Route::post('{connection}/approve', [MatchInboxController::class, 'approve'])->name('approve');
        Route::post('{connection}/reject', [MatchInboxController::class, 'reject'])->name('reject');
    });
});
