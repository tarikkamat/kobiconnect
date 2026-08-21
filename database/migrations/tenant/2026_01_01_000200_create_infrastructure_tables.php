<?php

declare(strict_types=1);

use App\Enums\ProcessingStatus;
use App\Marketplaces\Data\Enums\SyncState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sync_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('connection_id')->constrained('channel_connections')->cascadeOnDelete();
            $table->string('resource');
            $table->string('direction');
            $table->string('cursor_from')->nullable();
            $table->string('cursor_to')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->jsonb('stats')->default('{}');
            $table->string('status')->default(ProcessingStatus::Running->value);
            $table->jsonb('error')->nullable();
            $table->index('started_at', 'sync_runs_started_at_brin', 'brin');
        });

        Schema::create('sync_cursors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('connection_id')->constrained('channel_connections')->cascadeOnDelete();
            $table->string('resource');
            $table->timestamp('watermark')->nullable();
            $table->string('cursor')->nullable();
            $table->timestamps();
            $table->unique(['connection_id', 'resource']);
        });

        Schema::create('channel_operations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('connection_id')->constrained('channel_connections')->cascadeOnDelete();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->string('operation');
            $table->jsonb('desired_state');
            $table->jsonb('payload')->nullable();
            $table->string('payload_hash');
            $table->string('idempotency_key');
            $table->string('status')->default(SyncState::Pending->value);
            $table->unsignedInteger('attempts')->default(0);
            $table->string('remote_batch_id')->nullable();
            $table->jsonb('remote_result')->nullable();
            $table->timestamp('scheduled_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->jsonb('error')->nullable();
            $table->timestamps();
        });

        // Partial indexes are not expressible through the Blueprint.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX channel_ops_pending_uniq
                ON channel_operations (connection_id, idempotency_key)
                WHERE status IN ('pending', 'in_flight')
        SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX channel_ops_drain
                ON channel_operations (connection_id, status, scheduled_at)
                WHERE status = 'pending'
        SQL);

        Schema::create('webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('connection_id')->constrained('channel_connections')->cascadeOnDelete();
            $table->string('marketplace');
            $table->string('external_ref')->nullable();
            $table->jsonb('headers');
            $table->jsonb('payload');
            $table->string('payload_hash');
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->string('status')->default(ProcessingStatus::Pending->value);
            $table->jsonb('error')->nullable();
            $table->unique(['connection_id', 'payload_hash'], 'webhook_events_dedup');
            $table->index('received_at', 'webhook_events_received_at_brin', 'brin');
        });

        Schema::create('idempotency_keys', function (Blueprint $table): void {
            $table->string('key')->primary();
            // No FK: users lives in the tenant schema but is owned by the auth migrations.
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('endpoint');
            $table->string('request_hash');
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->jsonb('response_body')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('expires_at')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('channel_operations');
        Schema::dropIfExists('sync_cursors');
        Schema::dropIfExists('sync_runs');
    }
};
