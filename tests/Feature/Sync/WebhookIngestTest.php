<?php

declare(strict_types=1);

use App\Models\ChannelConnection;
use App\Models\WebhookEvent;
use App\Support\Sync\WebhookIngest;

beforeEach(function (): void {
    $this->connection = ChannelConnection::factory()->create();
    $this->ingest = app(WebhookIngest::class);
});

it('records a delivery once and refuses the retry of the same payload', function (): void {
    $payload = ['content' => [['shipmentPackageId' => 12, 'status' => 'Created']]];

    $first = $this->ingest->record($this->connection, $payload, ['x-api-key' => 'secret'], 'TY-12');
    $second = $this->ingest->record($this->connection, $payload, ['x-api-key' => 'secret'], 'TY-12');

    expect($first)->toBeInstanceOf(WebhookEvent::class)
        ->and($first->payload_hash)->toBe(hash('sha256', (string) json_encode($payload)))
        ->and($first->external_ref)->toBe('TY-12')
        // Trendyol retries every five minutes until it gets a 2xx: the second
        // delivery is acknowledged, never processed again.
        ->and($second)->toBeNull()
        ->and(WebhookEvent::query()->count())->toBe(1);
});

it('treats a changed payload as a new delivery', function (): void {
    $this->ingest->record($this->connection, ['status' => 'Created']);
    $this->ingest->record($this->connection, ['status' => 'Shipped']);

    expect(WebhookEvent::query()->count())->toBe(2);
});

it('scopes the dedup to the connection', function (): void {
    $other = ChannelConnection::factory()->create();

    $this->ingest->record($this->connection, ['status' => 'Created']);
    $this->ingest->record($other, ['status' => 'Created']);

    expect(WebhookEvent::query()->count())->toBe(2);
});
