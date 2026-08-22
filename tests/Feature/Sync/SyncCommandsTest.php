<?php

declare(strict_types=1);

use App\Enums\ConnectionStatus;
use App\Enums\ProcessingStatus;
use App\Jobs\Sync\DrainChannelOperations;
use App\Jobs\Sync\PullOrders;
use App\Marketplaces\Data\Enums\OperationType;
use App\Marketplaces\Data\Enums\SyncDirection;
use App\Marketplaces\Data\Enums\SyncState;
use App\Marketplaces\Trendyol\Exceptions\TrendyolApiException;
use App\Models\ChannelConnection;
use App\Models\ChannelOperation;
use App\Models\SyncCursor;
use App\Models\SyncRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;
use Tests\Fixtures\Trendyol\Fixture;

beforeEach(function (): void {
    Queue::fake();

    $this->connection = ChannelConnection::factory()->create();
});

function pulledAt(ChannelConnection $connection, string $ago): void
{
    SyncCursor::factory()->create([
        'connection_id' => $connection->getKey(),
        'resource' => 'orders',
    ]);

    // Straight to the column: saving through the model would stamp now().
    DB::table('sync_cursors')
        ->where('connection_id', $connection->getKey())
        ->update(['updated_at' => now()->sub($ago)]);
}

it('queues an order pull for a connection that has never been pulled', function (): void {
    $this->artisan('sync:pull')->assertSuccessful();

    Queue::assertPushed(
        PullOrders::class,
        fn (PullOrders $job): bool => $job->connectionId === $this->connection->getKey(),
    );
});

it('waits out the sync cadence', function (): void {
    // SyncCommand::DEFAULT_INTERVAL_MINUTES = 15.
    pulledAt($this->connection, '5 minutes');

    $this->artisan('sync:pull')->assertSuccessful();

    Queue::assertNotPushed(PullOrders::class);

    // Varsayilan aralik dolunca is kuyruga girer.
    DB::table('sync_cursors')
        ->where('connection_id', $this->connection->getKey())
        ->update(['updated_at' => now()->subMinutes(30)]);

    $this->artisan('sync:pull')->assertSuccessful();

    Queue::assertPushed(PullOrders::class);
});

it('lets an operator override the cadence by hand', function (): void {
    pulledAt($this->connection, '1 minute');

    $this->artisan('sync:pull', ['--force' => true])->assertSuccessful();

    Queue::assertPushed(PullOrders::class);
});

it('drains outbox rows nobody else picked up', function (): void {
    ChannelOperation::factory()->create([
        'connection_id' => $this->connection->getKey(),
        'operation' => OperationType::StockUpdate->value,
        'status' => SyncState::Pending,
        // A row that was returned to pending after a failed send.
        'scheduled_at' => now()->subMinute(),
    ]);

    $this->artisan('sync:drain')->assertSuccessful();

    Queue::assertPushed(
        DrainChannelOperations::class,
        fn (DrainChannelOperations $job): bool => $job->connectionId === $this->connection->getKey()
            && $job->operation === OperationType::StockUpdate,
    );
});

it('leaves a row alone until its penalty has run out', function (): void {
    ChannelOperation::factory()->create([
        'connection_id' => $this->connection->getKey(),
        'operation' => OperationType::StockUpdate->value,
        'status' => SyncState::Pending,
        'scheduled_at' => now()->addMinutes(5),
    ]);

    $this->artisan('sync:drain')->assertSuccessful();

    Queue::assertNotPushed(DrainChannelOperations::class);
});

it('ignores connections that are not active', function (): void {
    $this->connection->update(['status' => ConnectionStatus::Paused]);

    ChannelOperation::factory()->create([
        'connection_id' => $this->connection->getKey(),
        'operation' => OperationType::StockUpdate->value,
        'status' => SyncState::Pending,
        'scheduled_at' => now()->subMinute(),
    ]);

    $this->artisan('sync:pull')->assertSuccessful();
    $this->artisan('sync:drain')->assertSuccessful();

    Queue::assertNothingPushed();
});

it('leaves the operator a record of what a pull did', function (): void {
    Sleep::fake();

    Http::fake([
        '*nextCursor=*' => Http::response(Fixture::json('order-stream-page-2')),
        '*orders/stream*' => Http::response(Fixture::json('order-stream-page-1')),
    ]);

    $connection = ChannelConnection::factory()->create([
        'credentials' => ['seller_id' => '4321', 'api_key' => 'key', 'api_secret' => 'secret'],
    ]);

    app()->call([new PullOrders((int) $connection->getKey()), 'handle']);

    // sync_runs is the operator facing truth: Nightwatch answers to developers,
    // this row answers "why is that order missing" (BACKEND-PLAN 10.2).
    $run = SyncRun::query()->where('connection_id', $connection->getKey())->sole();

    expect($run->status)->toBe(ProcessingStatus::Completed)
        ->and($run->resource)->toBe('orders')
        ->and($run->direction)->toBe(SyncDirection::Pull)
        ->and($run->stats['orders'])->toBe(3)
        ->and($run->finished_at)->not->toBeNull()
        ->and(DB::table('orders')->count())->toBe(3);
});

it('marks the run failed and rethrows when the marketplace refuses', function (): void {
    Sleep::fake();

    Http::fake(['*orders/stream*' => Http::response(Fixture::json('error-401'), 401)]);

    $connection = ChannelConnection::factory()->create([
        'credentials' => ['seller_id' => '4321', 'api_key' => 'key', 'api_secret' => 'secret'],
    ]);

    expect(fn () => app()->call([new PullOrders((int) $connection->getKey()), 'handle']))
        ->toThrow(TrendyolApiException::class);

    expect(SyncRun::query()->sole()->status)->toBe(ProcessingStatus::Failed);
});
