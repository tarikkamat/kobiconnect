<?php

namespace App\Marketplaces\Data\Enums;

/**
 * Marketplace-independent status of a listing on a channel.
 */
enum CanonicalListingStatus: string
{
    case PendingApproval = 'pending_approval';

    /**
     * Pazaryeri bir katalog eslesmesi onerdi; satici onaylayana ya da
     * reddedene kadar urun satisa ACILMAZ (HEPSIBURADA.md §10 K1).
     */
    case AwaitingMatchDecision = 'awaiting_match_decision';

    case Rejected = 'rejected';

    case OnSale = 'on_sale';

    case NotOnSale = 'not_on_sale';

    case Archived = 'archived';

    case Blacklisted = 'blacklisted';

    case Locked = 'locked';

    /**
     * Whether the marketplace has approved the listing content.
     */
    public function isApproved(): bool
    {
        return match ($this) {
            self::PendingApproval, self::Rejected => false,
            default => true,
        };
    }

    /**
     * Whether the listing may be updated by the seller.
     */
    public function isEditable(): bool
    {
        return match ($this) {
            self::Blacklisted, self::Locked => false,
            default => true,
        };
    }
}
