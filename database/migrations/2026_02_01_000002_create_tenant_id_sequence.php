<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tenant kimlikleri URL'in ilk path segmentidir (`/1001/dashboard`).
 *
 * Firma adindan turetilen slug yerine SIRA NUMARASI kullaniyoruz:
 * - kisa ve okunur, panel URL'i tahmin edilebilir bir uzunlukta kalir
 * - firma adi degisince kimlik degismez (slug'da bu bir goc problemiydi)
 * - es zamanli iki kayitta yaris yok; benzersizligi veritabani garanti eder
 * - rezerve kelime carpismasi imkansiz: `login`, `register` gibi tek segmentli
 *   central route'lar sayisal bir segmentle asla cakismaz
 *
 * 1001'den basliyor: dort haneli kimlikler ilk gunden itibaren tutarli
 * uzunlukta ve "1" gibi bir kimligin verdigi oyuncak izlenimini vermiyor.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('central')->statement('CREATE SEQUENCE IF NOT EXISTS tenant_ids START WITH 1001 INCREMENT BY 1');
    }

    public function down(): void
    {
        DB::connection('central')->statement('DROP SEQUENCE IF EXISTS tenant_ids');
    }
};
