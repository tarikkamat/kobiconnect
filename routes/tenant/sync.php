<?php

declare(strict_types=1);

use App\Http\Controllers\Sync\OperationQueueController;
use App\Http\Controllers\Sync\SyncMonitorController;
use Illuminate\Support\Facades\Route;

/*
| Senkron monitoru ve islem kuyrugu (channel_operations defteri).
| `routes/tenant.php` icinden ['auth','verified','license'] grubunda yuklenir;
| burada middleware TEKRAR TANIMLANMAZ.
*/

Route::prefix('sync')->group(function (): void {
    Route::get('monitor', [SyncMonitorController::class, 'index'])->name('sync.monitor');

    Route::get('operations', [OperationQueueController::class, 'index'])->name('sync.operations.index');

    // Toplu yeniden deneme. Yazma islemi oldugu icin `channels.manage` ister ve
    // `Idempotency-Key` basligi verildiyse tekrar oynatilir — §8.2 katman 1.
    Route::post('operations/retry', [OperationQueueController::class, 'retry'])->name('sync.operations.retry');
});
