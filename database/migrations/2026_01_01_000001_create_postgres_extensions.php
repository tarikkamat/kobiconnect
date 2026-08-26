<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Katalog aramasinin dayandigi iki PostgreSQL eklentisi.
 *
 * Tenant migration'lari bunlari `public.unaccent(...)` ve
 * `public.gin_trgm_ops` olarak sema nitelemesiyle cagirir: tenant semasinda
 * calisirken search_path'te `public` YOKTUR (bkz. .ai/rules/tenant.md).
 * Bu yuzden eklentiler bir kez, merkezde, public semasina kurulur.
 *
 * Daha once hicbir yerde kurulmuyordu; gelistirme makinelerinde elle
 * kurulmus oldugu icin fark edilmiyordu. Taze bir veritabaninda ilk tenant
 * provisioning'i `text search dictionary "public.unaccent" does not exist`
 * ile duserdi.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('central')->statement('CREATE EXTENSION IF NOT EXISTS unaccent WITH SCHEMA public');
        DB::connection('central')->statement('CREATE EXTENSION IF NOT EXISTS pg_trgm WITH SCHEMA public');
    }

    public function down(): void
    {
        // Birakiliyor: baska tenant semalari ayni eklentilere bagli olabilir.
    }
};
