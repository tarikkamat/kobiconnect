<?php

declare(strict_types=1);

use App\Enums\ConnectionStatus;
use App\Enums\ListingSyncState;
use App\Enums\RuleScope;
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
        Schema::create('channel_connections', function (Blueprint $table): void {
            $table->id();
            $table->string('marketplace');
            $table->string('name');
            // AsEncryptedArrayObject stores ciphertext, not JSON: this cannot be jsonb.
            $table->text('credentials');
            $table->string('external_seller_id')->nullable();
            $table->string('status')->default(ConnectionStatus::Active->value);
            $table->jsonb('settings')->default('{}');
            $table->jsonb('field_overrides')->default('{}');
            $table->string('webhook_token')->unique();
            $table->timestamp('last_health_check_at')->nullable();
            $table->jsonb('capabilities')->default('{}');
            $table->timestamps();
        });

        Schema::create('channel_listings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('connection_id')->constrained('channel_connections')->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->string('remote_id')->nullable();
            $table->string('remote_status')->nullable();
            $table->string('remote_payload_hash')->nullable();
            $table->string('sync_state')->default(ListingSyncState::Pending->value);
            $table->timestamp('last_pushed_at')->nullable();
            $table->timestamp('last_pulled_at')->nullable();
            $table->jsonb('error')->nullable();
            $table->timestamps();
            $table->unique(['connection_id', 'variant_id']);
            $table->unique(['connection_id', 'remote_id']);
        });

        Schema::create('channel_category_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('connection_id')->constrained('channel_connections')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('remote_category_id');
            $table->string('remote_path')->nullable();
            $table->timestamps();
            $table->unique(['connection_id', 'category_id'], 'channel_category_map_uniq');
        });

        Schema::create('channel_attribute_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('connection_id')->constrained('channel_connections')->cascadeOnDelete();
            $table->string('remote_category_id');
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->string('remote_attribute_id');
            $table->boolean('is_required')->default(false);
            $table->boolean('allow_custom')->default(false);
            $table->boolean('allow_multiple')->default(false);
            $table->boolean('is_varianter')->default(false);
            $table->boolean('is_slicer')->default(false);
            $table->timestamps();
            $table->unique(['connection_id', 'remote_category_id', 'attribute_id'], 'channel_attribute_map_uniq');
        });

        Schema::create('channel_attribute_value_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mapping_id')->constrained('channel_attribute_mappings')->cascadeOnDelete();
            $table->foreignId('attribute_value_id')->constrained()->cascadeOnDelete();
            $table->string('remote_value_id');
            $table->timestamps();
            $table->unique(['mapping_id', 'attribute_value_id'], 'channel_attribute_value_map_uniq');
        });

        Schema::create('channel_brand_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('connection_id')->constrained('channel_connections')->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('remote_brand_id');
            $table->timestamps();
            $table->unique(['connection_id', 'brand_id'], 'channel_brand_map_uniq');
        });

        Schema::create('channel_price_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('connection_id')->constrained('channel_connections')->cascadeOnDelete();
            $table->string('scope_type')->default(RuleScope::Connection->value);
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->string('markup_type');
            $table->decimal('markup_value', 12, 4);
            $table->decimal('round_to', 8, 2)->nullable();
            $table->timestamps();
            $table->index(['connection_id', 'scope_type', 'scope_id'], 'channel_price_rules_scope_idx');
        });

        Schema::create('channel_stock_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('connection_id')->constrained('channel_connections')->cascadeOnDelete();
            $table->string('scope_type')->default(RuleScope::Connection->value);
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->string('allocation_type');
            $table->decimal('allocation_value', 12, 4)->nullable();
            $table->integer('buffer')->default(0);
            $table->timestamps();
            $table->index(['connection_id', 'scope_type', 'scope_id'], 'channel_stock_rules_scope_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channel_stock_rules');
        Schema::dropIfExists('channel_price_rules');
        Schema::dropIfExists('channel_brand_mappings');
        Schema::dropIfExists('channel_attribute_value_mappings');
        Schema::dropIfExists('channel_attribute_mappings');
        Schema::dropIfExists('channel_category_mappings');
        Schema::dropIfExists('channel_listings');
        Schema::dropIfExists('channel_connections');
    }
};
