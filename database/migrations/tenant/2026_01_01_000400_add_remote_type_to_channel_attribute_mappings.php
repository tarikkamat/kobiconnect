<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pazaryerinin HAM attribute tipi (`string`|`integer`|`enum`|`media`|`video`).
 *
 * Kanonik bayraklar (is_required, allow_custom, ...) turetilmis bilgidir; yerel
 * on-dogrulama (BACKEND-PLAN.md §7.5) ham tipi bilmeden calisamaz. Ornegin
 * `media` tipinde zorunlu bir alani serbest metin sanip gecirir ve kullanici
 * hatayi ancak saatler sonra moderasyon reddi olarak gorur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channel_attribute_mappings', function (Blueprint $table): void {
            $table->string('remote_type')->nullable()->after('remote_attribute_id');
        });
    }

    public function down(): void
    {
        Schema::table('channel_attribute_mappings', function (Blueprint $table): void {
            $table->dropColumn('remote_type');
        });
    }
};
