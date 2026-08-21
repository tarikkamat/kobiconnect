<?php

declare(strict_types=1);

use App\Http\Controllers\Onboarding\LoginController;
use App\Http\Controllers\Onboarding\PasswordResetController;
use App\Http\Controllers\Onboarding\RegisterTenantController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Central Routes
|--------------------------------------------------------------------------
|
| Uygulama tek host'ta yasar; tenant path'in ilk segmentidir. Buradaki
| route'lar tenant prefix'i DISINDA kalan, kullanici oturumu gerektirmeyen
| yollardir: kayit ve giris.
|
| Bu route'lar `routes/tenant.php`'den ONCE kaydedilir, yani `/register` ve
| `/login` bir tenant slug'i tarafindan golgelenemez. Ters yonu de kapatmak
| icin bu yollarin adlari RegisterTenantRequest::RESERVED_SLUGS icinde
| rezervedir — aksi halde "login" slug'ini alan musteri kendi paneline
| ulasamazdi.
|
*/

Route::inertia('/', 'welcome')->name('home');

Route::get('register', [RegisterTenantController::class, 'create'])->name('onboarding.register');
Route::post('register', [RegisterTenantController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('onboarding.register.store');

// Ortak giris: musteri panel adresini bilmek zorunda degil, tenant e-postadan
// cozulur. Route adi `central.login` — `login` adi Fortify'in tenant
// route'unundur ve ezilirse route('login') tenant paneline gitmez.
// `throttle:login` FortifyServiceProvider'daki e-posta|IP limitini paylasir.
Route::get('login', [LoginController::class, 'create'])->name('central.login');
Route::post('login', [LoginController::class, 'store'])
    ->middleware('throttle:login')
    ->name('central.login.store');

// Parola sifirlama da ortak; token tenant semasinda dogrulanir. Fortify'in
// /{tenant}/... esdegerleri yerinde kalir ama uretilen baglanti buraya gelir
// (FortifyServiceProvider: ResetPassword::createUrlUsing).
Route::get('forgot-password', [PasswordResetController::class, 'create'])->name('central.password.request');
Route::post('forgot-password', [PasswordResetController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('central.password.email');

Route::get('reset-password/{token}', [PasswordResetController::class, 'edit'])->name('central.password.reset');
Route::post('reset-password', [PasswordResetController::class, 'update'])
    ->middleware('throttle:6,1')
    ->name('central.password.update');
