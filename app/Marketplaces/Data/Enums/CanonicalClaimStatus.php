<?php

namespace App\Marketplaces\Data\Enums;

use App\Concerns\HasLabels;

/**
 * Marketplace-independent status of a single claim (return) item.
 *
 * Claims carry no header status; the displayed status is derived from items.
 */
enum CanonicalClaimStatus: string
{
    use HasLabels;

    case Created = 'created';

    case WaitingAction = 'waiting_action';

    case UnderReview = 'under_review';

    case Accepted = 'accepted';

    case Rejected = 'rejected';

    case Cancelled = 'cancelled';

    case Unresolved = 'unresolved';

    /**
     * Arayuz metinleri Turkce, kanonik enum degerleri degil — FRONTEND-PLAN §7.
     */
    public function label(): string
    {
        return match ($this) {
            self::Created => 'Açıldı',
            self::WaitingAction => 'Aksiyon bekliyor',
            self::UnderReview => 'İnceleniyor',
            self::Accepted => 'Kabul edildi',
            self::Rejected => 'Reddedildi',
            self::Cancelled => 'İptal edildi',
            self::Unresolved => 'Çözülmedi',
        };
    }

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
