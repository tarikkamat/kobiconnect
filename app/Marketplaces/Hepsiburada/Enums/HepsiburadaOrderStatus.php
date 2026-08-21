<?php

declare(strict_types=1);

namespace App\Marketplaces\Hepsiburada\Enums;

use App\Marketplaces\Data\Enums\CanonicalOrderStatus;

/**
 * Order and package statuses (HEPSIBURADA.md §5.8).
 *
 * ⚠️ Unmeasured: the SIT merchant has zero orders, so `items[]` never came back
 * with a status field (§11.2, Ek A #6). The value set below is the archived
 * documentation's, matched case insensitively, and anything outside it maps to
 * `CanonicalOrderStatus::Unknown` - never folded into a neighbour, because a
 * mis-folded status silently authorises fulfilment.
 */
enum HepsiburadaOrderStatus: string
{
    case PaymentAwaiting = 'paymentawaiting';

    case Open = 'open';

    case Packaged = 'packaged';

    case Shipped = 'shipped';

    case Delivered = 'delivered';

    case Undelivered = 'undelivered';

    case Unpacked = 'unpacked';

    case Cancelled = 'cancelled';

    public static function tryFromRemote(?string $value): ?self
    {
        return $value === null ? null : self::tryFrom(mb_strtolower(trim($value), 'UTF-8'));
    }

    public function toCanonical(): CanonicalOrderStatus
    {
        return match ($this) {
            // Hepsiburada accepts no responsibility for a package shipped while
            // payment is still pending: nothing but stock reservation may fire.
            self::PaymentAwaiting => CanonicalOrderStatus::PendingPayment,
            self::Open => CanonicalOrderStatus::Created,
            self::Packaged => CanonicalOrderStatus::Picking,
            self::Shipped => CanonicalOrderStatus::Shipped,
            self::Delivered => CanonicalOrderStatus::Delivered,
            self::Undelivered => CanonicalOrderStatus::Undelivered,
            self::Unpacked => CanonicalOrderStatus::Unpacked,
            self::Cancelled => CanonicalOrderStatus::Cancelled,
        };
    }
}
