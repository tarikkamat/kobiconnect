<?php

namespace App\Marketplaces\Data;

/**
 * The quantity allocated to a channel for a variant.
 */
final readonly class StockData
{
    public function __construct(
        public string $reference,
        public int $quantity,
        public ?string $sku = null,
        public ?string $barcode = null,
        public ?string $remoteWarehouseId = null,
    ) {}
}
