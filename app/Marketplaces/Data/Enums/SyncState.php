<?php

namespace App\Marketplaces\Data\Enums;

/**
 * State of a channel operation or listing synchronisation.
 *
 * A remote acknowledgement (HTTP 200) only reaches InFlight; Completed
 * requires the item level result to have been read back.
 */
enum SyncState: string
{
    case Pending = 'pending';

    case InFlight = 'in_flight';

    case Completed = 'completed';

    case Failed = 'failed';

    /**
     * Whether the operation still occupies the pending uniqueness window.
     */
    public function isOpen(): bool
    {
        return match ($this) {
            self::Pending, self::InFlight => true,
            default => false,
        };
    }
}
