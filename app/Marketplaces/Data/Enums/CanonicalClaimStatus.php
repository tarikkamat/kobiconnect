<?php

namespace App\Marketplaces\Data\Enums;

/**
 * Marketplace-independent status of a single claim (return) item.
 *
 * Claims carry no header status; the displayed status is derived from items.
 */
enum CanonicalClaimStatus: string
{
    case Created = 'created';

    case WaitingAction = 'waiting_action';

    case UnderReview = 'under_review';

    case Accepted = 'accepted';

    case Rejected = 'rejected';

    case Cancelled = 'cancelled';

    case Unresolved = 'unresolved';

    /**
     * Whether the seller may still approve or reject the item.
     */
    public function isActionable(): bool
    {
        return $this === self::WaitingAction;
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Accepted, self::Rejected, self::Cancelled => true,
            default => false,
        };
    }
}
