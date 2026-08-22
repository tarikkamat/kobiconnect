<?php

declare(strict_types=1);

use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Settings\NotificationPreferenceController;
use App\Http\Controllers\Team\TeamController;
use Illuminate\Support\Facades\Route;

/*
| Ek ayar ekranlari: ekip & roller, bildirim tercihleri.
|
| `routes/tenant.php` icinden ['auth','verified'] grubunda yuklenir.
*/

Route::prefix('settings')->group(function (): void {
    Route::prefix('team')->group(function (): void {
        Route::get('/', [TeamController::class, 'index'])->name('team.index');
        Route::post('/', [TeamController::class, 'store'])->name('team.store');
        Route::patch('{user}', [TeamController::class, 'update'])->name('team.update');
        Route::delete('{user}', [TeamController::class, 'destroy'])->name('team.destroy');
    });

    // Bildirim tercihleri (olay x kanal matrisi, §11.3).
    Route::get('notifications', [NotificationPreferenceController::class, 'edit'])
        ->name('notification-preferences.edit');
    Route::put('notifications', [NotificationPreferenceController::class, 'update'])
        ->name('notification-preferences.update');
});

/*
| Bildirim merkezi. Ayar ekrani degil ama ayni middleware yigininda yasiyor.
*/
Route::prefix('notifications')->group(function (): void {
    Route::get('/', [NotificationController::class, 'index'])->name('notifications.index');

    // Panel zilini besleyen JSON ucu (useHttp) — Inertia gezinmesi yok.
    Route::get('feed', [NotificationController::class, 'feed'])->name('notifications.feed');

    Route::post('read', [NotificationController::class, 'read'])->name('notifications.read');
});
