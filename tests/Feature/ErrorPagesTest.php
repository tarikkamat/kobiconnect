<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Bu dosyadaki ad-hoc route'lar BILEREK iki segmentlidir.
 *
 * bootstrap/app.php tenant route'larini `Route::prefix('{tenant}')` altinda
 * kaydeder ve routes/tenant.php'nin ilk satiri `Route::redirect('/', ...)`
 * — yani `/{tenant}` TEK segmentli her yolu yakalar. Test icinde kaydedilen
 * route'lar bu gruptan SONRA geldigi icin tek segmentli bir yol once tenant
 * olarak cozulmeye calisilir, bulunamaz ve 404 doner.
 */

test('404 hata sayfasi inertia error bileseni ve 404 statusu ile render edilir', function (): void {
    $response = $this->get('/tamamen-gecersiz-ve-olmayan-bir-sayfa-adresi');

    $response->assertNotFound();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('error')
        ->where('status', 404)
    );
});

test('403 hata durumunda inertia error bileseni render edilir', function (): void {
    Route::middleware(['web', HandleInertiaRequests::class])->get('/errors/forbidden-test', function () {
        abort(403, 'Erişim engellendi.');
    });

    $response = $this->get('/errors/forbidden-test');

    $response->assertForbidden();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('error')
        ->where('status', 403)
    );
});

test('503 servis kullanilamiyor durumunda inertia error bileseni render edilir', function (): void {
    // bootstrap/app.php debug ACIKKEN 5xx'te ham yaniti dondurur; uretimde
    // debug kapali oldugu icin asil davranis Inertia error sayfasidir.
    Config::set('app.debug', false);

    Route::middleware(['web', HandleInertiaRequests::class])->get('/errors/maintenance-test', function () {
        abort(503, 'Bakım modu.');
    });

    $response = $this->get('/errors/maintenance-test');

    $response->assertStatus(503);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('error')
        ->where('status', 503)
    );
});

test('500 sunucu hatasinda debug kapaliyken inertia error bileseni render edilir', function (): void {
    Config::set('app.debug', false);

    Route::middleware(['web', HandleInertiaRequests::class])->get('/errors/server-error-test', function () {
        abort(500, 'Sunucu hatası.');
    });

    $response = $this->get('/errors/server-error-test');

    $response->assertStatus(500);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('error')
        ->where('status', 500)
    );
});

test('api veya json isteklerinde inertia yerine json hata yaniti doner', function (): void {
    $response = $this->getJson('/tamamen-gecersiz-ve-olmayan-bir-api-adresi');

    $response->assertNotFound();
    $response->assertHeader('content-type', 'application/json');
});
