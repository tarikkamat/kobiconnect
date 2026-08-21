<?php

declare(strict_types=1);

namespace App\Support\Sync;

use App\Models\ChannelConnection;
use App\Models\WebhookEvent;
use Illuminate\Support\Carbon;

/**
 * Dedup layer 2 (BACKEND-PLAN 8.2): the inbound webhook.
 *
 * Trendyol retries every five minutes until it gets a 2xx and gives no
 * ordering guarantee, so duplicates are certain (TRENDYOL.md K5). The
 * `webhook_events_dedup` unique index on (connection_id, payload_hash) is the
 * gate: the delivery is processed only when the insert really added a row, and
 * a replay is acknowledged without doing the work twice.
 *
 * Ordering is resolved from the timestamps inside the payload, never from the
 * order of arrival - that belongs to whoever maps the payload.
 */
final class WebhookIngest
{
    /**
     * Null means this exact delivery was already recorded: acknowledge, do not
     * process it again.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $headers
     */
    public function record(
        ChannelConnection $connection,
        array $payload,
        array $headers = [],
        ?string $externalRef = null,
    ): ?WebhookEvent {
        $encoded = (string) json_encode($payload);

        $inserted = WebhookEvent::query()->toBase()->insertOrIgnoreReturning([
            'connection_id' => $connection->getKey(),
            'marketplace' => $connection->marketplace,
            'external_ref' => $externalRef,
            'headers' => (string) json_encode($headers),
            'payload' => $encoded,
            'payload_hash' => hash('sha256', $encoded),
            'received_at' => Carbon::now(),
            'processed_at' => null,
            'error' => null,
        ]);

        $row = $inserted->first();

        return $row === null ? null : (new WebhookEvent)->newFromBuilder((array) $row);
    }
}
