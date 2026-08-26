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
        Schema::create('price_lists', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('type'); // manual, currency, dynamic
            $table->char('source_currency', 3)->default('TRY');
            $table->char('target_currency', 3)->default('TRY');
            $table->decimal('exchange_rate', 12, 6)->nullable();
            $table->string('rounding_method')->default('none');
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('price_list_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('price_list_id')->constrained('price_lists')->cascadeOnDelete();
            $table->string('field'); // all, category, brand, tag, product
            $table->jsonb('condition_value')->nullable();
            $table->string('adjustment_type'); // percentage, fixed
            $table->decimal('adjustment_value', 12, 2);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('price_list_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('price_list_id')->constrained('price_lists')->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->decimal('list_price', 12, 2);
            $table->decimal('sale_price', 12, 2)->nullable();
            $table->char('currency', 3)->default('TRY');
            $table->timestamps();

            $table->unique(['price_list_id', 'variant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_list_items');
        Schema::dropIfExists('price_list_rules');
        Schema::dropIfExists('price_lists');
    }
};
