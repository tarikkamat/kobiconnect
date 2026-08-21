<?php

declare(strict_types=1);

namespace App\Support\Sync;

use App\Marketplaces\Data\PushResult;

/**
 * A driver that can read back the item level result of a batch it accepted.
 *
 * This is deliberately not a Capability: it is not something a seller buys or
 * a screen offers, it is plumbing the poller needs. Trendyol answers stock and
 * price batches from the same `products/batch-requests/{id}` endpoint it uses
 * for products (TRENDYOL.md 6.2), so the reader has to exist without the
 * driver claiming `product_sync` - which stays out of reach until the P0
 * attribute contradiction of TRENDYOL.md Ek A #1 is settled on stage.
 *
 * ponytail: declared here rather than in app/Marketplaces/Contracts for the
 * same reason BindsCredentials is - the sync engine is its only consumer.
 * Move it next to the Supports* contracts when a second driver implements it.
 */
interface ReadsBatchResults
{
    /**
     * An empty item result set means "still running", never "nothing failed".
     */
    public function batchResult(string $remoteBatchId): PushResult;
}
