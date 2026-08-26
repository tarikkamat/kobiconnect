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
        Schema::create('dynamic_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('match_type')->default('all');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('dynamic_category_conditions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dynamic_category_id')->constrained('dynamic_categories')->cascadeOnDelete();
            $table->string('field');
            $table->string('operator');
            $table->jsonb('value')->nullable();
            $table->timestamps();
        });

        Schema::create('dynamic_category_products', function (Blueprint $table): void {
            $table->foreignId('dynamic_category_id')->constrained('dynamic_categories')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['dynamic_category_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dynamic_category_products');
        Schema::dropIfExists('dynamic_category_conditions');
        Schema::dropIfExists('dynamic_categories');
    }
};
