<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Kisiye ozel tablo gorunumu: {"orders.index": {"hidden": ["customer"]}}.
            // Yalnizca GIZLENEN kolonlar saklanir; varsayilan "hepsi gorunur".
            $table->jsonb('table_preferences')->default('{}');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('table_preferences');
        });
    }
};
