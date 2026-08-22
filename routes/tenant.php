<?php

declare(strict_types=1);

use App\Http\Controllers\Catalog\BrandController;
use App\Http\Controllers\Catalog\CategoryController;
use App\Http\Controllers\Catalog\ProductController;
use App\Http\Controllers\Catalog\VariantController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Listeners\ConfigureTenantHost;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Tenant paneli. Domain kisiti ve tenancy middleware'i bootstrap/app.php
| icinde uygulanir; Fortify'in auth route'lari da ayni domain'e baglanir
| (config/fortify.php). Central domain'de kullanici yoktur — §4.1.
|
*/

// `{tenant}` URL varsayilani — bkz. ConfigureTenantHost.
Route::middleware(ConfigureTenantHost::class)->group(function (): void {
    Route::redirect('/', '/dashboard');

    Route::middleware(['auth', 'verified'])->group(function (): void {
        // Alan bazli route dosyalari. Paralel calisan is kumelerinin ayni
        // dosyada carpismamasi icin bolundu; middleware yigini burada tanimli.
        require __DIR__.'/tenant/channels.php';
        require __DIR__.'/tenant/orders.php';
        require __DIR__.'/tenant/sync.php';
        require __DIR__.'/tenant/inventory.php';
        require __DIR__.'/tenant/catalog.php';
        require __DIR__.'/tenant/claims.php';

        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::prefix('catalog')->group(function (): void {
            Route::get('products', [ProductController::class, 'index'])->name('products.index');
            Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');
            Route::patch('products/{product}', [ProductController::class, 'update'])->name('products.update');

            // Satir ici duzenleme uclari: istemci `optimistic` visit ile cagirir.
            Route::patch('variants/{variant}/stock', [VariantController::class, 'stock'])->name('variants.stock');
            Route::patch('variants/{variant}/price', [VariantController::class, 'price'])->name('variants.price');

            Route::get('brands', [BrandController::class, 'index'])->name('brands.index');
            Route::post('brands', [BrandController::class, 'store'])->name('brands.store');
            Route::patch('brands/{brand}', [BrandController::class, 'update'])->name('brands.update');
            Route::delete('brands/{brand}', [BrandController::class, 'destroy'])->name('brands.destroy');

            Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
            Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
            Route::patch('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
            Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
        });
    });

    Route::middleware(['auth'])->group(function (): void {
        require __DIR__.'/tenant/settings.php';

        Route::redirect('settings', '/settings/profile');

        Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    });

    Route::middleware(['auth', 'verified'])->group(function (): void {
        Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

        Route::get('settings/security', [SecurityController::class, 'edit'])
            ->middleware(RequirePassword::class)
            ->name('security.edit');

        Route::put('settings/password', [SecurityController::class, 'update'])
            ->middleware('throttle:6,1')
            ->name('user-password.update');

        Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');
    });

    // Passkey RP ID tenant subdomain'idir (§4.2), bu yuzden bu belge tenant
    // domain'inden sunulmak zorunda.
    Route::get('.well-known/passkey-endpoints', function () {
        return response()->json([
            'enroll' => route('security.edit'),
            'manage' => route('security.edit'),
        ]);
    })->name('well-known.passkeys');
});
