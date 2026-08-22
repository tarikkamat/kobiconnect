<?php

declare(strict_types=1);

use App\Http\Controllers\Claims\ClaimController;
use Illuminate\Support\Facades\Route;

/*
| claims route'lari.
| `routes/tenant.php` icinden ['auth','verified'] grubunda yuklenir;
| burada middleware TEKRAR TANIMLANMAZ.
|
| Bu faz yalnizca OKUMA: iade onay/red pazaryerine yazma demektir ve outbox
| motoruna baglidir — burada route'u YOKTUR.
*/
Route::prefix('claims')->group(function (): void {
    Route::get('/', [ClaimController::class, 'index'])->name('claims.index');
    Route::get('{claim}', [ClaimController::class, 'show'])
        ->whereNumber('claim')
        ->name('claims.show');
});
