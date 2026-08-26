<?php

namespace App\Marketplaces\Data\Enums;

use App\Concerns\HasLabels;

/**
 * Marketplace-independent order and shipment package status.
 *
 * The raw remote status is always stored alongside this value; an unknown
 * remote status is never folded into a default case.
 */
enum CanonicalOrderStatus: string
{
    use HasLabels;

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
     * Arayuz metinleri Turkce, kanonik enum degerleri degil — FRONTEND-PLAN §7.
     */
    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Ödeme bekleniyor',
            self::Created => 'Gönderime hazır',
            self::Picking => 'Hazırlanıyor',
            self::Invoiced => 'Faturalandı',
            self::Shipped => 'Kargoda',
            self::AtCollectionPoint => 'Teslimat noktasında',
            self::Delivered => 'Teslim edildi',
            self::Undelivered => 'Teslim edilemedi',
            self::Unpacked => 'Paket bölündü',
            self::Unsupplied => 'Tedarik edilemedi',
            self::Cancelled => 'İptal edildi',
            self::Returned => 'İade edildi',
            self::Unknown => 'Bilinmeyen durum',
        };
    }

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
