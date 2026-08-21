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
    $this->grantActiveLicense();

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

        $screens[$route->getName() ?? $path] = $path;
    }

    expect($screens)->not->toBeEmpty('Tenant panelinde hic ekran bulunamadi.');

    $broken = [];

    foreach ($screens as $name => $path) {
        $status = $this->actingAs($owner)->get("/test/{$path}")->status();

        // 200 render, 302 kimlik/onay yonlendirmesi (login, sifre onayi) —
        // ikisi de saglikli. 4xx/5xx bir ekranin olu oldugunu soyler.
        if ($status >= 400) {
            $broken[] = "{$name} ({$path}) -> {$status}";
        }
    }

    expect($broken)->toBe([], 'Olu ekran(lar): '.implode(', ', $broken));
});
