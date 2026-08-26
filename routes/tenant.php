<?php

declare(strict_types=1);

use App\Http\Controllers\Catalog\AttributeController;
use App\Http\Controllers\Catalog\BrandController;
use App\Http\Controllers\Catalog\CategoryController;
use App\Http\Controllers\Catalog\DefinitionController;
use App\Http\Controllers\Catalog\DynamicCategoryController;
use App\Http\Controllers\Catalog\PriceListController;
use App\Http\Controllers\Catalog\ProductController;
use App\Http\Controllers\Catalog\ProductGroupController;
use App\Http\Controllers\Catalog\TagController;
use App\Http\Controllers\Catalog\UnitController;
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
        require __DIR__.'/tenant/inventory.php';
        require __DIR__.'/tenant/catalog.php';
        require __DIR__.'/tenant/claims.php';
        require __DIR__.'/tenant/reports.php';
        require __DIR__.'/tenant/ai.php';

        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::prefix('catalog')->group(function (): void {
            Route::get('definitions', [DefinitionController::class, 'index'])->name('definitions.index');
            Route::get('products', [ProductController::class, 'index'])->name('products.index');
            Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');
            Route::patch('products/{product}', [ProductController::class, 'update'])->name('products.update');

            // Satir ici duzenleme uclari: istemci `optimistic` visit ile cagirir.
            Route::patch('variants/{variant}/stock', [VariantController::class, 'stock'])->name('variants.stock');
            Route::patch('variants/{variant}/price', [VariantController::class, 'price'])->name('variants.price');

            Route::get('brands', [BrandController::class, 'index'])->name('brands.index');
            Route::get('brands/create', [BrandController::class, 'create'])->name('brands.create');
            Route::post('brands', [BrandController::class, 'store'])->name('brands.store');
            Route::get('brands/{brand}/edit', [BrandController::class, 'edit'])->name('brands.edit');
            Route::patch('brands/{brand}', [BrandController::class, 'update'])->name('brands.update');
            Route::delete('brands/{brand}', [BrandController::class, 'destroy'])->name('brands.destroy');

            Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
            Route::get('categories/create', [CategoryController::class, 'create'])->name('categories.create');
            Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
            Route::get('categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit');
            Route::patch('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
            Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

            Route::get('attributes', [AttributeController::class, 'index'])->name('attributes.index');
            Route::get('attributes/create', [AttributeController::class, 'create'])->name('attributes.create');
            Route::post('attributes', [AttributeController::class, 'store'])->name('attributes.store');
            Route::get('attributes/{attribute}/edit', [AttributeController::class, 'edit'])->name('attributes.edit');
            Route::patch('attributes/{attribute}', [AttributeController::class, 'update'])->name('attributes.update');
            Route::delete('attributes/{attribute}', [AttributeController::class, 'destroy'])->name('attributes.destroy');

            Route::get('tags', [TagController::class, 'index'])->name('tags.index');
            Route::get('tags/create', [TagController::class, 'create'])->name('tags.create');
            Route::post('tags', [TagController::class, 'store'])->name('tags.store');
            Route::get('tags/{tag}/edit', [TagController::class, 'edit'])->name('tags.edit');
            Route::patch('tags/{tag}', [TagController::class, 'update'])->name('tags.update');
            Route::delete('tags/{tag}', [TagController::class, 'destroy'])->name('tags.destroy');

            Route::get('units', [UnitController::class, 'index'])->name('units.index');
            Route::get('units/create', [UnitController::class, 'create'])->name('units.create');
            Route::post('units', [UnitController::class, 'store'])->name('units.store');
            Route::get('units/{unit}/edit', [UnitController::class, 'edit'])->name('units.edit');
            Route::patch('units/{unit}', [UnitController::class, 'update'])->name('units.update');
            Route::delete('units/{unit}', [UnitController::class, 'destroy'])->name('units.destroy');

            Route::get('product-groups', [ProductGroupController::class, 'index'])->name('product-groups.index');
            Route::get('product-groups/create', [ProductGroupController::class, 'create'])->name('product-groups.create');
            Route::post('product-groups', [ProductGroupController::class, 'store'])->name('product-groups.store');
            Route::get('product-groups/{productGroup}', [ProductGroupController::class, 'show'])->name('product-groups.show');
            Route::get('product-groups/{productGroup}/edit', [ProductGroupController::class, 'edit'])->name('product-groups.edit');
            Route::patch('product-groups/{productGroup}', [ProductGroupController::class, 'update'])->name('product-groups.update');
            Route::delete('product-groups/{productGroup}', [ProductGroupController::class, 'destroy'])->name('product-groups.destroy');

            Route::get('dynamic-categories', [DynamicCategoryController::class, 'index'])->name('dynamic-categories.index');
            Route::get('dynamic-categories/create', [DynamicCategoryController::class, 'create'])->name('dynamic-categories.create');
            Route::post('dynamic-categories', [DynamicCategoryController::class, 'store'])->name('dynamic-categories.store');
            Route::get('dynamic-categories/{dynamicCategory}', [DynamicCategoryController::class, 'show'])->name('dynamic-categories.show');
            Route::get('dynamic-categories/{dynamicCategory}/edit', [DynamicCategoryController::class, 'edit'])->name('dynamic-categories.edit');
            Route::patch('dynamic-categories/{dynamicCategory}', [DynamicCategoryController::class, 'update'])->name('dynamic-categories.update');
            Route::delete('dynamic-categories/{dynamicCategory}', [DynamicCategoryController::class, 'destroy'])->name('dynamic-categories.destroy');

            Route::get('price-lists', [PriceListController::class, 'index'])->name('price-lists.index');
            Route::post('price-lists', [PriceListController::class, 'store'])->name('price-lists.store');
            Route::get('price-lists/{priceList}', [PriceListController::class, 'show'])->name('price-lists.show');
            Route::patch('price-lists/{priceList}', [PriceListController::class, 'update'])->name('price-lists.update');
            Route::delete('price-lists/{priceList}', [PriceListController::class, 'destroy'])->name('price-lists.destroy');
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
