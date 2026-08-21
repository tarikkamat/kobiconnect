<?php

namespace App\Marketplaces\Data\Enums;

use App\Marketplaces\Support\Capability;

/**
 * The kind of outbound mutation recorded in the channel operations ledger.
 */
enum OperationType: string
{
    case ProductCreate = 'product_create';

    case ProductUpdate = 'product_update';

    case PriceUpdate = 'price_update';

    case StockUpdate = 'stock_update';

    case ShipmentStatus = 'shipment_status';

    case TrackingNumber = 'tracking_number';

    case ClaimApprove = 'claim_approve';

    case ClaimReject = 'claim_reject';

    case QuestionAnswer = 'question_answer';

    /**
     * The driver capability required to drain an operation of this type.
     */
    public function capability(): Capability
    {
        return match ($this) {
            self::ProductCreate, self::ProductUpdate => Capability::ProductSync,
            self::PriceUpdate => Capability::PriceSync,
            self::StockUpdate => Capability::InventorySync,
            self::ShipmentStatus, self::TrackingNumber => Capability::ShipmentUpdates,
            self::ClaimApprove, self::ClaimReject => Capability::Claims,
            self::QuestionAnswer => Capability::Questions,
        };
    }
}
