<?php

declare(strict_types=1);

use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Settings\NotificationPreferenceController;
use App\Http\Controllers\Settings\TableColumnController;
use App\Http\Controllers\Team\TeamController;
use App\Mcp\ActionCatalog;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

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

    // Tablo kolon gorunurlugu — ekransiz, kolon secicisinden sessiz kayit.
    Route::patch('table-columns', [TableColumnController::class, 'update'])
        ->name('table-columns.update');

    // MCP baglanti ekrani. Controller yok: ekranin tek isi kullanicinin ELLE
    // YAZAMAYACAGI adresi (tenant kimligi iceren /{tenant}/mcp) kopyalanabilir
    // gostermek. Action sayisi katalogdan okunur ki ekran bayatlamasin.
    Route::get('mcp', fn (): Response => Inertia::render('settings/mcp', [
        'endpoint' => url(tenant('id').'/mcp'),
        'actionCount' => count(ActionCatalog::all()),
    ]))->name('mcp.setup');

    // Lisans — dummy vitrin, controller'siz. Lisans mimarisi kararlastirilinca
    // gercek modele baglanacak; simdilik ekran tasarimi icin statik veri.
    Route::inertia('license', 'settings/license')->name('license.edit');
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
