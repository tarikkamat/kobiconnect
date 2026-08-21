<?php

namespace App\Marketplaces\Data\Enums;

/**
 * Marketplace-independent order and shipment package status.
 *
 * The raw remote status is always stored alongside this value; an unknown
 * remote status is never folded into a default case.
 */
enum CanonicalOrderStatus: string
{
    /**
     * Payment is not confirmed yet. Nothing but stock reservation may be
     * triggered by this status; the marketplace accepts no responsibility
     * for packages shipped while awaiting payment approval.
     */
    case PendingPayment = 'pending_payment';

    case Created = 'created';

    case Picking = 'picking';

    case Invoiced = 'invoiced';

    case Shipped = 'shipped';

    case AtCollectionPoint = 'at_collection_point';

    case Delivered = 'delivered';

    case Undelivered = 'undelivered';

    case Unpacked = 'unpacked';

    case Unsupplied = 'unsupplied';

    case Cancelled = 'cancelled';

    case Returned = 'returned';

    /**
     * Pazaryeri tanimadigimiz bir statu dondurdu. Ham deger `external_status`'ta
     * durur; bu durum hicbir sey tetiklemez ve satir incelemeye duser.
     *
     * Bilinmeyen bir degeri anlamli bir duruma katlamak (ornegin PendingPayment)
     * sessiz yanlis davranis uretir — ayri bir durum olmasi sart.
     */
    case Unknown = 'unknown';

    /**
     * Whether the status can no longer change.
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Delivered, self::Returned, self::Cancelled, self::Unsupplied => true,
            default => false,
        };
    }

    /**
     * Whether picking, invoicing or shipping may act on the order.
     */
    public function allowsFulfilment(): bool
    {
        return match ($this) {
            self::Created, self::Picking, self::Invoiced, self::Unpacked => true,
            default => false,
        };
    }

    /**
     * Whether the order holds stock reserved against the shared inventory.
     */
    public function reservesStock(): bool
    {
        return ! $this->isTerminal();
    }
}
