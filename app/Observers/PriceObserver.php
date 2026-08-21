<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\Sync\QueueVariantSync;
use App\Models\Price;

/**
 * A price row is only worth a push when a number the marketplace can see moved,
 * or when the row that was winning the validity window disappeared.
 */
final class PriceObserver
{
    public function __construct(private readonly QueueVariantSync $sync) {}

    public function saved(Price $price): void
    {
        if ($price->wasRecentlyCreated
            || $price->wasChanged(['list_price', 'sale_price', 'currency', 'valid_from', 'valid_to'])) {
            $this->sync->price($price->variant);
        }
    }

    public function deleted(Price $price): void
    {
        $this->sync->price($price->variant);
    }
}
