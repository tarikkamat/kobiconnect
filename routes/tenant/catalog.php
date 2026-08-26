<?php

declare(strict_types=1);

use App\Http\Controllers\Catalog\DynamicCategoryController;
use App\Http\Controllers\Catalog\PriceListController;
use App\Http\Controllers\Catalog\ProductController;
use Illuminate\Support\Facades\Route;

/*
| catalog route'lari.
| `routes/tenant.php` icinden ['auth','verified'] grubunda yuklenir;
| burada middleware TEKRAR TANIMLANMAZ.
|
| Bu dosya `routes/tenant.php` icindeki `catalog` blogundan ONCE yuklenir, bu
| yuzden `products/create` sabit segmenti `products/{product}` ile catismaz.
|
| CSV/XLSX ice aktarma BILEREK yok: toplu fiyat/stok islemi ayni ihtiyaci
| dosya yukleme, sutun eslemesi ve hata raporu makinesi olmadan karsiliyor.
*/
Route::prefix('catalog')->group(function (): void {
    Route::get('products/create', [ProductController::class, 'create'])->name('products.create');
    Route::post('products', [ProductController::class, 'store'])->name('products.store');
    Route::post('products/images/upload', [ProductController::class, 'uploadImage'])->name('products.images.upload');
    Route::post('products/pull', [ProductController::class, 'pull'])->name('products.pull');

    // Toplu islem uclari `{product}` route'undan once gelmeli.
    // `preview` sayfa gezinmesi olmadan cagrilir (useHttp) ve JSON doner.
    Route::post('products/bulk/preview', [ProductController::class, 'bulkPreview'])->name('products.bulk-preview');
    Route::post('products/bulk', [ProductController::class, 'bulkUpdate'])->name('products.bulk-update');

    Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

    // Fiyat Listeleri
    Route::get('price-lists/create', [PriceListController::class, 'create'])->name('price-lists.create');
    Route::post('price-lists', [PriceListController::class, 'store'])->name('price-lists.store');
    Route::post('price-lists/{priceList}/regenerate', [PriceListController::class, 'regenerate'])->name('price-lists.regenerate');
    Route::patch('price-lists/{priceList}/items/{priceListItem}', [PriceListController::class, 'updateItem'])->name('price-lists.update-item');

    // Dinamik Kategoriler
    Route::post('dynamic-categories/{dynamicCategory}/evaluate', [DynamicCategoryController::class, 'evaluate'])->name('dynamic-categories.evaluate');
});
