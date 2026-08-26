<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('short_name')->unique();
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('unit_id')->nullable()->after('category_id')->constrained('units')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropForeign(['unit_id']);
            $table->dropColumn('unit_id');
        });

        Schema::dropIfExists('units');
    }
};
