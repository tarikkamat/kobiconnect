<?php

declare(strict_types=1);

namespace App\Listeners;

use Spatie\Permission\PermissionRegistrar;

/**
 * spatie/laravel-permission'in `PermissionRegistrar`'i bir singleton'dir ve
 * cozumlenmis izin/rol koleksiyonunu BELLEKTE tutar. `CacheTenancyBootstrapper`
 * yalnizca cache *store*'unu tenant'a etiketler — registrar'in statik kopyasina
 * dokunmaz.
 *
 * Sonuc: tenancy gecisinden sonra bir onceki tenant'in izinleri okunmaya devam
 * eder. Octane/RoadRunner altinda bu, worker omru boyunca surer.
 * BACKEND-PLAN.md §2.3'teki sizinti sinifinin aynisi.
 */
final class FlushPermissionCache
{
    public function __construct(private readonly PermissionRegistrar $registrar) {}

    public function handle(object $event): void
    {
        $this->registrar->forgetCachedPermissions();
    }
}
