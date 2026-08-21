<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\Sync\QueueVariantSync;
use App\Models\InventoryItem;

/**
 * Every stock movement is a marketplace push waiting to happen.
 *
 * This is an observer and not a domain event on purpose: `inventory_items` has
 * many writers (the panel, order reservation, imports, a console fix at 3am)
 * and only one of them is a place a `StockChanged` event could be dispatched
 * from. An observer sits on the table itself, so a writer cannot forget.
 *
 * `available` is a generated column, so the new value is read back from the
 * database rather than from the model in memory.
 */
final class InventoryItemObserver
{
    public function __construct(private readonly QueueVariantSync $sync) {}

    public function saved(InventoryItem $item): void
    {
        if ($item->wasRecentlyCreated || $item->wasChanged(['on_hand', 'reserved', 'safety_stock'])) {
            $this->sync->stock($item->variant);
        }
    }

    public function deleted(InventoryItem $item): void
    {
        $this->sync->stock($item->variant);
    }
}
