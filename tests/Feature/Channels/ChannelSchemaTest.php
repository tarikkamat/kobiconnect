<?php

declare(strict_types=1);

use App\Marketplaces\Data\Enums\SyncState;
use App\Models\ChannelConnection;
use App\Models\ChannelListing;
use App\Models\ChannelOperation;
use App\Models\SyncRun;
use App\Models\Tenant;
use App\Models\WebhookEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function (): void {
    // Bu bir SEMA testi ama ChannelListing yaratmak outbox gozlemcisini
    // tetikliyor ve kuyruk testlerde `sync` oldugu icin drenaj SATIR ICINDE
    // kosup pazaryerine gercek istek atiyor. Sema iddialarinin push'a
    // ihtiyaci yok; hepsini fake'liyoruz.
    Http::fake();

    $this->tenant = Tenant::create(['id' => 'test'.Str::lower(Str::random(10))]);
    tenancy()->initialize($this->tenant);
});

afterEach(function (): void {
    tenancy()->end();
    $this->tenant->delete();
});

it('stores connection credentials encrypted', function (): void {
    $connection = ChannelConnection::factory()->create([
        'credentials' => ['api_key' => 'plain-key'],
    ]);

    $raw = DB::table('channel_connections')->where('id', $connection->id)->value('credentials');

    expect($raw)->not->toContain('plain-key')
        ->and($connection->fresh()->credentials['api_key'])->toBe('plain-key');
});

it('lists a variant at most once per connection', function (): void {
    $listing = ChannelListing::factory()->create();

    expect(fn () => ChannelListing::factory()->create([
        'connection_id' => $listing->connection_id,
        'variant_id' => $listing->variant_id,
    ]))->toThrow(QueryException::class);
});

it('rejects a duplicate remote id on the same connection', function (): void {
    $listing = ChannelListing::factory()->synced()->create();

    expect(fn () => ChannelListing::factory()->create([
        'connection_id' => $listing->connection_id,
        'remote_id' => $listing->remote_id,
    ]))->toThrow(QueryException::class);
});

it('allows many listings without a remote id yet', function (): void {
    $connection = ChannelConnection::factory()->create();
    ChannelListing::factory()->count(3)->create(['connection_id' => $connection->id]);

    expect(ChannelListing::whereNull('remote_id')->count())->toBe(3);
});

it('deduplicates webhook deliveries by payload hash', function (): void {
    $event = WebhookEvent::factory()->create();

    expect(fn () => WebhookEvent::factory()->create([
        'connection_id' => $event->connection_id,
        'payload_hash' => $event->payload_hash,
    ]))->toThrow(QueryException::class);
});

it('keeps a single open operation per idempotency key', function (): void {
    $operation = ChannelOperation::factory()->create();

    expect(fn () => ChannelOperation::factory()->create([
        'connection_id' => $operation->connection_id,
        'idempotency_key' => $operation->idempotency_key,
    ]))->toThrow(QueryException::class);
});

it('allows the same idempotency key again once the operation closed', function (): void {
    $operation = ChannelOperation::factory()->create();
    $operation->update(['status' => SyncState::Completed, 'completed_at' => now()]);

    $retry = ChannelOperation::factory()->create([
        'connection_id' => $operation->connection_id,
        'idempotency_key' => $operation->idempotency_key,
    ]);

    expect($retry->status)->toBe(SyncState::Pending)
        ->and(ChannelOperation::count())->toBe(2);
});

it('prunes webhook events and sync runs past their retention window', function (): void {
    $connection = ChannelConnection::factory()->create();

    WebhookEvent::factory()->create(['connection_id' => $connection->id, 'received_at' => now()->subDays(45)]);
    WebhookEvent::factory()->create(['connection_id' => $connection->id, 'received_at' => now()]);
    SyncRun::factory()->create(['connection_id' => $connection->id, 'started_at' => now()->subDays(45)]);
    SyncRun::factory()->create(['connection_id' => $connection->id, 'started_at' => now()]);

    (new WebhookEvent)->pruneAll();
    (new SyncRun)->pruneAll();

    expect(WebhookEvent::count())->toBe(1)
        ->and(SyncRun::count())->toBe(1);
});
