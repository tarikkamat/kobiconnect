<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Warehouse;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * TENANT kok seeder'idir (`config/tenancy.php` -> `seeder_parameters`) ve
 * `Jobs\SeedDatabase` uzerinden HER tenant provisioning'inde calisir.
 *
 * Bu yuzden buraya yalnizca her gercek musterinin sahip olmasi gereken seyler
 * girer. Demo/test kullanicisi BURAYA KONMAZ — aksi halde her yeni musteri
 * semasinda tanidik bir e-posta ve bilinen bir sifre olusur.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(TenantRoleSeeder::class);
        Warehouse::firstOrCreate(
            ['code' => 'ANA'],
            ['name' => 'Ana Depo', 'is_default' => true],
        );
    }
}
