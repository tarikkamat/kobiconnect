<?php

declare(strict_types=1);

use App\Models\Tenant;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * spatie'nin PermissionRegistrar'i bir singleton'dir ve cozumlenmis izinleri
 * `$this->permissions` icinde BELLEKTE tutar; `loadPermissions()` doluysa erken
 * doner. `CacheTenancyBootstrapper` yalnizca cache *store*'unu tenant'a etiketler
 * — bu statik kopyaya dokunmaz. Sonuc: tenancy gecisinden sonra onceki tenant'in
 * izinleri okunmaya devam eder. Octane altinda worker omru boyunca surer.
 * BACKEND-PLAN.md §2.3'teki sizinti sinifinin aynisi.
 *
 * Duzeltme: FlushPermissionCache, TenancyInitialized ve TenancyEnded uzerine.
 *
 * DIKKAT: diger tenant ONCE yaratilmali. `Tenant::create` provisioning
 * pipeline'ini (dolayisiyla TenantRoleSeeder'i) calistirir, o da onbellegi
 * dusurur ve sizintiyi maskeler.
 */
beforeEach(function (): void {
    $this->other = Tenant::firstOrCreate(['id' => 'izolasyon']);
});

afterEach(function (): void {
    tenancy()->initialize($this->tenant);
    $this->other->delete();
});

it('bir tenant izinlerini digerine sizdirmaz', function (): void {
    Permission::findOrCreate('yalnizca.a', 'web');

    // Registrar'i bilerek isit: sizinti tam da bu bellek kopyasindan olusuyor.
    expect(app(PermissionRegistrar::class)->getPermissions()->pluck('name'))
        ->toContain('yalnizca.a');

    tenancy()->initialize($this->other);

    expect(Permission::query()->pluck('name')->all())
        ->not->toContain('yalnizca.a');

    expect(app(PermissionRegistrar::class)->getPermissions()->pluck('name')->all())
        ->not->toContain('yalnizca.a');
});

it('geri donuldugunde ilk tenant in izinlerini yeniden cozer', function (): void {
    Permission::findOrCreate('yalnizca.a', 'web');
    app(PermissionRegistrar::class)->getPermissions();

    tenancy()->initialize($this->other);
    tenancy()->initialize($this->tenant);

    expect(app(PermissionRegistrar::class)->getPermissions()->pluck('name')->all())
        ->toContain('yalnizca.a');
});
