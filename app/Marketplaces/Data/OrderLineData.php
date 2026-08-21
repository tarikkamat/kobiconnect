<?php

namespace App\Marketplaces\Data;

use App\Marketplaces\Data\Enums\CanonicalOrderStatus;

/**
 * A single line of a marketplace order. Line status may diverge from the
 * package status on partial cancellation or partial non-supply.
 */
final readonly class OrderLineData
{
    public function __construct(
        public string $remoteId,
        public string $sku,
        public int $quantity,
        public string $unitPrice,
        public CanonicalOrderStatus $status,
        public string $externalStatus,
        public ?string $barcode = null,
        public string $discount = '0',
        public ?string $commission = null,
        public ?string $vatRate = null,
    ) {}
}
