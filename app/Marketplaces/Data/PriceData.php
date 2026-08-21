<?php

namespace App\Marketplaces\Data;

/**
 * The price of a variant on a channel. Amounts are decimal strings.
 */
final readonly class PriceData
{
    public function __construct(
        public string $reference,
        public string $listPrice,
        public string $salePrice,
        public string $currency = 'TRY',
        public ?string $sku = null,
        public ?string $barcode = null,
    ) {}
}
