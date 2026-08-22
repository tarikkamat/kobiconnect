<?php

use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\TenantRoleSeeder;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(TenantRoleSeeder::class);
});

test('it seeds the five starting roles into the tenant schema', function () {
    expect(Role::pluck('name')->all())
        ->toEqualCanonicalizing(['Sahip', 'Yönetici', 'Depo', 'Muhasebe', 'Müşteri Temsilcisi']);
});

test('tenant deletion belongs to the owner alone', function () {
    $owner = User::factory()->create()->assignRole('Sahip');
    $admin = User::factory()->create()->assignRole('Yönetici');

    expect($owner->can('tenant.delete'))->toBeTrue()
        ->and($admin->can('tenant.delete'))->toBeFalse()
        ->and($admin->can('catalog.manage'))->toBeTrue();
});

test('warehouse and support roles stay inside their lane', function () {
    $warehouse = User::factory()->create()->assignRole('Depo');
    $support = User::factory()->create()->assignRole('Müşteri Temsilcisi');

    expect($warehouse->can('orders.fulfill'))->toBeTrue()
        ->and($warehouse->can('invoices.manage'))->toBeFalse()
        ->and($support->can('returns.manage'))->toBeTrue()
        ->and($support->can('catalog.manage'))->toBeFalse();
});

/**
 * `DatabaseSeeder` tenant kok seeder'idir ve HER provisioning'de calisir.
 * Icine demo kullanici konursa her gercek musteri semasinda tanidik bir
 * e-posta ve bilinen bir sifre olusur.
 */
test('provisioning seeds roles but creates no users', function () {
    $tenant = Tenant::firstOrCreate(['id' => 'provkontrol']);

    try {
        tenancy()->initialize($tenant);

        expect(Role::count())->toBeGreaterThan(0)
            ->and(User::count())->toBe(0);
    } finally {
        tenancy()->initialize($this->tenant);
        $tenant->delete();
    }
});

/**
 * Deposuz bir tenant calismaz: envanter matrisi, katalogtaki satir ici stok
 * duzenlemesi ve stok gonderimi hepsi bir depo satirina baglidir, ve
 * WarehouseController zaten "en az bir depo kalir" kuralini zorluyor. Yeni
 * musteri kendi ilk deposunu acmak zorunda kalmamali.
 */
test('provisioning opens a default warehouse', function () {
    $tenant = Tenant::firstOrCreate(['id' => 'depokontrol']);

    try {
        tenancy()->initialize($tenant);

        $warehouse = Warehouse::query()->sole();

        expect($warehouse->is_default)->toBeTrue()
            ->and($warehouse->code)->toBe('ANA');
    } finally {
        tenancy()->initialize($this->tenant);
        $tenant->delete();
    }
});
