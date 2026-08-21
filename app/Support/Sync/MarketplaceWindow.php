<?php

declare(strict_types=1);

namespace App\Support\Sync;

use App\Models\ChannelOperation;
use Illuminate\Support\Facades\Cache;

/**
 * Dedup layer 4 (BACKEND-PLAN 8.2): the marketplace's own suppression window.
 *
 * Trendyol drops an updatePriceAndInventory call that repeats the same
 * (barcode, values) within 15 minutes - silently, with an error message that
 * looks like a failure (TRENDYOL.md K3). Replaying the same body is therefore
 * never a retry: it is a no-op we would misread as a send. So the last hash
 * that actually went out is remembered per (connection, operation, reference)
 * and an identical one inside the window is dropped before it is sent.
 *
 * The window length is per marketplace configuration, never a constant: it is
 * a number Trendyol changes by announcement.
 */
final class MarketplaceWindow
{
    /**
     * Whether this operation would be swallowed by the marketplace anyway.
     */
    public function suppresses(ChannelOperation $operation, string $marketplace): bool
    {
        if ($this->seconds($marketplace) <= 0) {
            return false;
        }

        return Cache::get($this->key($operation)) === $operation->payload_hash;
    }

    /**
     * Record what actually went out, so the next identical push is dropped.
     */
    public function remember(ChannelOperation $operation, string $marketplace): void
    {
        $seconds = $this->seconds($marketplace);

        if ($seconds <= 0) {
            return;
        }

        Cache::put($this->key($operation), $operation->payload_hash, $seconds);
    }

    /**
     * The marketplace refused these values, so it is not holding them: a
     * corrected retry must not be mistaken for a repeat.
     */
    public function forget(ChannelOperation $operation): void
    {
        Cache::forget($this->key($operation));
    }

    /**
     * Zero disables the layer: no other marketplace punishes a repeat.
     */
    public function seconds(string $marketplace): int
    {
        $seconds = config("marketplaces.{$marketplace}.dedup_window_seconds", 0);

        return is_numeric($seconds) ? (int) $seconds : 0;
    }

    private function key(ChannelOperation $operation): string
    {
        return implode(':', [
            'sync-window',
            (string) $operation->connection_id,
            $operation->operation,
            $this->reference($operation),
        ]);
    }

    /**
     * The identity the marketplace deduplicates on - the barcode for Trendyol,
     * falling back to whatever the canonical DTO used to correlate its items.
     */
    private function reference(ChannelOperation $operation): string
    {
        foreach (['barcode', 'reference', 'sku'] as $key) {
            $value = $operation->desired_state[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return $operation->entity_type.'#'.$operation->entity_id;
    }
}
