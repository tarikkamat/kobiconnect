<?php

declare(strict_types=1);

use App\Enums\ProcessingStatus;
use App\Jobs\Sync\DrainChannelOperations;
use App\Marketplaces\Data\Enums\OperationType;
use App\Marketplaces\Data\Enums\SyncState;
use App\Models\ChannelConnection;
use App\Models\ChannelOperation;
use App\Models\SyncCursor;
use App\Models\SyncRun;
use App\Models\User;
use Database\Seeders\TenantRoleSeeder;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    Queue::fake();

    $this->seed(TenantRoleSeeder::class);

    $this->manager = User::factory()->create()->assignRole('Yönetici');
    $this->warehouseman = User::factory()->create()->assignRole('Depo');
    $this->connection = ChannelConnection::factory()->create(['name' => 'Trendyol Ana']);
});

it('shows the latest run per channel and resource with its cursor', function (): void {
    SyncRun::factory()->create([
        'connection_id' => $this->connection->getKey(),
        'resource' => 'orders',
        'status' => ProcessingStatus::Completed,
        'started_at' => now()->subMinutes(10),
        'finished_at' => now()->subMinutes(9),
        'stats' => ['pages' => 2, 'items' => 40],
    ]);

    SyncRun::factory()->create([
        'connection_id' => $this->connection->getKey(),
        'resource' => 'orders',
        'status' => ProcessingStatus::Failed,
        'started_at' => now()->subMinutes(2),
        'error' => ['message' => 'Trendyol 429'],
    ]);

    SyncCursor::factory()->create([
        'connection_id' => $this->connection->getKey(),
        'resource' => 'orders',
        'watermark' => now()->subMinutes(9),
    ]);

    ChannelOperation::factory()->count(2)->create([
        'connection_id' => $this->connection->getKey(),
        'status' => SyncState::Failed,
    ]);

    $this->actingAs($this->manager)
        ->get(route('sync.monitor'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('sync/monitor')
            ->count('runs', 1)
            ->where('runs.0.connection', 'Trendyol Ana')
            ->where('runs.0.statusLabel', 'Başarısız')
            ->where('runs.0.error', 'Trendyol 429')
            ->where('ledger.failed', 2)
            ->count('failedRuns', 1)
        );
});

it('lists the ledger and filters it down to failures', function (): void {
    ChannelOperation::factory()->create([
        'connection_id' => $this->connection->getKey(),
        'operation' => OperationType::StockUpdate->value,
        'status' => SyncState::Completed,
    ]);

    ChannelOperation::factory()->create([
        'connection_id' => $this->connection->getKey(),
        'operation' => OperationType::StockUpdate->value,
        'status' => SyncState::Failed,
        'error' => ['code' => 'PIM-1001', 'message' => 'Barkod bulunamadı'],
    ]);

    $this->actingAs($this->manager)
        ->get(route('sync.operations.index', ['status' => 'failed']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('sync/operations')
            ->count('operations.data', 1)
            ->where('operations.data.0.statusLabel', 'Başarısız')
            ->where('operations.data.0.operationLabel', 'Stok gönder')
            ->where('operations.data.0.message', 'Barkod bulunamadı')
        );
});

it('re-opens a failed operation and queues its drain', function (): void {
    $operation = ChannelOperation::factory()->create([
        'connection_id' => $this->connection->getKey(),
        'operation' => OperationType::StockUpdate->value,
        'status' => SyncState::Failed,
        'remote_batch_id' => 'batch-77',
        'error' => ['message' => 'Barkod bulunamadı'],
        'attempts' => 2,
    ]);

    $this->actingAs($this->manager)
        ->post(route('sync.operations.retry'), ['ids' => [$operation->getKey()]])
        ->assertRedirect();

    $operation->refresh();

    expect($operation->status)->toBe(SyncState::Pending)
        ->and($operation->error)->toBeNull()
        ->and($operation->remote_batch_id)->toBeNull()
        // The desired state survives: a retry recomputes, it never replays.
        ->and($operation->desired_state)->not->toBeEmpty();

    Queue::assertPushed(DrainChannelOperations::class);
});

it('refuses a retry without the channel permission', function (): void {
    $operation = ChannelOperation::factory()->create([
        'connection_id' => $this->connection->getKey(),
        'status' => SyncState::Failed,
    ]);

    $this->actingAs($this->warehouseman)
        ->post(route('sync.operations.retry'), ['ids' => [$operation->getKey()]])
        ->assertForbidden();

    expect($operation->refresh()->status)->toBe(SyncState::Failed);
});

it('replays a repeated retry instead of running it twice', function (): void {
    $operation = ChannelOperation::factory()->create([
        'connection_id' => $this->connection->getKey(),
        'operation' => OperationType::StockUpdate->value,
        'status' => SyncState::Failed,
    ]);

    $payload = ['ids' => [$operation->getKey()]];
    $headers = ['Idempotency-Key' => 'retry-42'];

    $this->actingAs($this->manager)->post(route('sync.operations.retry'), $payload, $headers)
        ->assertRedirect();

    // The first call re-opened it; put it back so a second run would show.
    ChannelOperation::query()->whereKey($operation->getKey())->update(['status' => SyncState::Failed->value]);

    $this->actingAs($this->manager)->post(route('sync.operations.retry'), $payload, $headers)
        ->assertRedirect();

    // The second call replayed the recorded outcome, so the row stayed failed.
    expect($operation->refresh()->status)->toBe(SyncState::Failed);
});
