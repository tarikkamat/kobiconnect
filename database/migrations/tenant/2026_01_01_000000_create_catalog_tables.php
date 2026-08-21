<?php

declare(strict_types=1);

use App\Enums\ProductStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Weighted Turkish full text expression for products.search_vector.
     *
     * Uses f_unaccent() (created below) rather than unaccent() directly:
     * unaccent() is STABLE, and PostgreSQL only accepts IMMUTABLE
     * expressions in a generated column.
     */
    private const SEARCH_VECTOR = "setweight(to_tsvector('turkish', f_unaccent(coalesce(name, ''))), 'A')"
        ." || setweight(to_tsvector('turkish', f_unaccent(coalesce(description, ''))), 'B')";

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // The tenant search_path holds only the tenant schema, so the extensions
        // installed in "public" have to be referenced with their schema.
        DB::statement(<<<'SQL'
            CREATE FUNCTION f_unaccent(text) RETURNS text
            LANGUAGE sql IMMUTABLE PARALLEL SAFE STRICT
            AS $$ SELECT public.unaccent('public.unaccent'::regdictionary, $1) $$
        SQL);

        Schema::create('brands', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->cascadeOnDelete();
            $table->string('name');
            $table->string('path')->index();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('attributes', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type');
            $table->boolean('is_variant_defining')->default(false);
            $table->timestamps();
        });

        Schema::create('attribute_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->string('value');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['attribute_id', 'value']);
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default(ProductStatus::Draft->value);
            $table->jsonb('attributes')->default('{}');
            $table->tsvector('search_vector')->storedAs(self::SEARCH_VECTOR);
            $table->timestamps();
            $table->index('search_vector', 'products_search_gin', 'gin');
        });

        Schema::create('product_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku')->unique();
            $table->string('barcode')->nullable()->unique();
            $table->jsonb('attributes')->default('{}');
            $table->decimal('weight', 8, 3)->nullable();
            $table->jsonb('dimensions')->nullable();
            $table->decimal('vat_rate', 5, 2)->default(0);
            $table->string('hs_code')->nullable();
            $table->timestamps();
        });

        // Operator classes are not expressible through the Blueprint.
        DB::statement('CREATE INDEX product_variants_sku_trgm ON product_variants USING gin (sku public.gin_trgm_ops)');

        Schema::create('product_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->string('url');
            $table->string('checksum')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('warehouses', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->boolean('is_default')->default(false);
            $table->jsonb('address')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->integer('on_hand')->default(0);
            $table->integer('reserved')->default(0);
            $table->integer('available')->storedAs('on_hand - reserved');
            $table->integer('safety_stock')->default(0);
            $table->timestamps();
            $table->unique(['variant_id', 'warehouse_id']);
        });

        Schema::create('prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->char('currency', 3)->default('TRY');
            $table->decimal('list_price', 12, 2);
            $table->decimal('sale_price', 12, 2)->nullable();
            $table->decimal('cost', 12, 2)->nullable();
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_to')->nullable();
            $table->timestamps();
            $table->index(['variant_id', 'currency']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prices');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('attribute_values');
        Schema::dropIfExists('attributes');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('brands');

        DB::statement('DROP FUNCTION IF EXISTS f_unaccent(text)');
    }
};
