<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\Sync\QueueVariantSync;
use App\Models\ChannelListing;

/**
 * A listing is the moment a variant starts existing for one marketplace, and
 * until something pushes it that marketplace holds whatever it happened to have
 * - usually zero. Only `created` fires: the later columns on this row
 * (`last_pushed_at`, `sync_state`) are written *by* the push and echoing them
 * back into the outbox would be a loop.
 */
final class ChannelListingObserver
{
    public function __construct(private readonly QueueVariantSync $sync) {}

    public function created(ChannelListing $listing): void
    {
        $this->sync->all($listing->variant, $listing->connection);
    }
}
