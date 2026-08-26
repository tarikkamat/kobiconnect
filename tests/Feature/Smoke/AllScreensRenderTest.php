<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\TenantRoleSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;

/**
 * Her panel ekrani gercekten render oluyor mu?
 *
 * Bir ekranin DOSYASI olmasi calistigi anlamina gelmez: route baglanmamis,
 * controller patliyor, Vite manifest'inde yok ya da prop sekli tutmuyor
 * olabilir. Bu test tek tek acmadan hepsini yoklar.
 *
 * Kapsam: tenant panelindeki PARAMETRESIZ GET ekranlari. Parametre alanlar
 * (urun detayi, siparis detayi) kendi feature testlerinde kapsanir.
 */
it('her panel ekrani sahip rolu icin render oluyor', function (): void {
    // Ekran render'i disariya cikmamali; outbox gozlemcisi de push denememeli.
    Http::fake();
    Queue::fake();

    $this->seed(TenantRoleSeeder::class);

    $owner = User::factory()->create();
    $owner->assignRole('Sahip');

    $screens = [];

    foreach (Route::getRoutes() as $route) {
        $uri = $route->uri();

        if (! in_array('GET', $route->methods(), true) || ! str_starts_with($uri, '{tenant}/')) {
            continue;
        }

        $path = substr($uri, strlen('{tenant}/'));

        if (str_contains($path, '{')) {
            continue;
        }

        // Panel ekranlari route adiyla tanimlidir; adsiz tenant GET'leri
        // ekran degil protokol ucudur (or. MCP sunucusunun 405 stub'i).
        $name = $route->getName();

        if ($name === null) {
            continue;
        }

        // OAuth/MCP uclari da ekran degil: Inertia render etmezler, makineyle
        // konusurlar ve kendi testleri var (tests/Feature/Mcp).
        if (str_starts_with($name, 'passport.') || str_starts_with($name, 'mcp.')) {
            continue;
        }

        $screens[$name] = $path;
    }

    expect($screens)->not->toBeEmpty('Tenant panelinde hic ekran bulunamadi.');

    $broken = [];

    foreach ($screens as $name => $path) {
        // getStatusCode(), status() degil: ikincisi yalnizca Illuminate
        // yanitlarinda var, cikip gelen bir Symfony yaniti dongusu komple
        // dusururdu.
        $status = $this->actingAs($owner)->get("/test/{$path}")->getStatusCode();

        // 200 render, 302 kimlik/onay yonlendirmesi (login, sifre onayi) —
        // ikisi de saglikli. 4xx/5xx bir ekranin olu oldugunu soyler.
        if ($status >= 400) {
            $broken[] = "{$name} ({$path}) -> {$status}";
        }
    }

    expect($broken)->toBe([], 'Olu ekran(lar): '.implode(', ', $broken));
});
