<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Tenant semasindaki baslangic rolleri — BACKEND-PLAN.md §4.3.
 *
 * Izin listesi bilerek kaba: plandaki bes rolun ozet yetkilerini karsilar,
 * daha ince bir ayrim gerektiginde buyur.
 */
class TenantRoleSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    private const array PERMISSIONS = [
        'tenant.delete',
        'users.manage',
        'channels.manage',
        'catalog.view',
        'catalog.manage',
        'stock.manage',
        'orders.view',
        'orders.fulfill',
        'shipping.manage',
        'invoices.manage',
        'reports.view',
        'questions.manage',
        'returns.manage',
    ];

    /**
     * @var array<string, list<string>>
     */
    private const array ROLES = [
        'Depo' => ['catalog.view', 'stock.manage', 'orders.view', 'orders.fulfill', 'shipping.manage'],
        'Muhasebe' => ['catalog.view', 'orders.view', 'invoices.manage', 'reports.view'],
        'Müşteri Temsilcisi' => ['catalog.view', 'orders.view', 'questions.manage', 'returns.manage'],
    ];

    public function run(): void
    {
        $guard = config('auth.defaults.guard');

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, $guard);
        }

        // syncPermissions() izinleri registrar'in onbellekli koleksiyonundan
        // cozer; yeni yaratilanlari gormesi icin once onbellek dusurulmeli.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Sahip her seyi yapar; Yonetici tenant silme haric her seyi.
        Role::findOrCreate('Sahip', $guard)->syncPermissions(self::PERMISSIONS);
        Role::findOrCreate('Yönetici', $guard)->syncPermissions(
            array_diff(self::PERMISSIONS, ['tenant.delete']),
        );

        foreach (self::ROLES as $role => $permissions) {
            Role::findOrCreate($role, $guard)->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
