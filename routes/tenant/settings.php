<?php

declare(strict_types=1);

use App\Http\Controllers\Billing\LicenseController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Settings\NotificationPreferenceController;
use App\Http\Controllers\Team\TeamController;
use Illuminate\Support\Facades\Route;

/*
| Ek ayar ekranlari: ekip & roller, lisans & kullanim, bildirim tercihleri.
|
| `routes/tenant.php` icinden ['auth','verified'] grubunda yuklenir — `license`
| BILEREK yok: suresi dolmus musteri lisans ekranini gorebilmeli, yoksa odeme
| yapamaz. Lisans korumasi gereken route'a TEK TEK ->middleware('license') eklenir.
*/

Route::prefix('settings')->group(function (): void {
    // Ekip yonetimi lisansa baglidir: suresi dolmus tenant yeni koltuk acamaz,
    // grace period'da okuyabilir ama yazamaz (EnsureLicenseIsActive).
    Route::prefix('team')->middleware('license')->group(function (): void {
        Route::get('/', [TeamController::class, 'index'])->name('team.index');
        Route::post('/', [TeamController::class, 'store'])->name('team.store');
        Route::patch('{user}', [TeamController::class, 'update'])->name('team.update');
        Route::delete('{user}', [TeamController::class, 'destroy'])->name('team.destroy');
    });

    // Lisans & kullanim: `license` middleware'i BILEREK YOK. Suresi dolmus veya
    // hic lisansi olmayan musteri de bu ekrani gorebilmeli — goremezse plan
    // yenileme yolunu bulamaz ve panelden tamamen disari duser.
    Route::get('license', [LicenseController::class, 'index'])->name('license.index');

    // Bildirim tercihleri (olay x kanal matrisi, §11.3). `license` middleware'i
    // YOK: bildirim gurultusunu kesmek suresi dolmus musterinin de hakki.
    Route::get('notifications', [NotificationPreferenceController::class, 'edit'])
        ->name('notification-preferences.edit');
    Route::put('notifications', [NotificationPreferenceController::class, 'update'])
        ->name('notification-preferences.update');
});

/*
| Bildirim merkezi. Ayar ekrani degil ama ayni middleware yigininda yasiyor:
| `license` YOK — "lisansiniz doluyor" bildirimini gormek icin lisansin gecerli
| olmasini sart kosmak, kapiyi disaridan kilitlemektir.
*/
Route::prefix('notifications')->group(function (): void {
    Route::get('/', [NotificationController::class, 'index'])->name('notifications.index');

    // Panel zilini besleyen JSON ucu (useHttp) — Inertia gezinmesi yok.
    Route::get('feed', [NotificationController::class, 'feed'])->name('notifications.feed');

    Route::post('read', [NotificationController::class, 'read'])->name('notifications.read');
});
