<?php

namespace App\Marketplaces\Data;

/**
 * A sellable variant. Monetary values are decimal strings so that no rounding
 * happens between the database and the marketplace payload.
 */
final readonly class VariantData
{
    /**
     * @param  list<AttributeValueData>  $attributes
     * @param  array{length?: float, width?: float, height?: float}  $dimensions
     * @param  list<string>  $images
     */
    public function __construct(
        public string $reference,
        public string $sku,
        public ?string $barcode = null,
        public array $attributes = [],
        public ?float $weight = null,
        public array $dimensions = [],
        public ?string $vatRate = null,
        public ?string $hsCode = null,
        public array $images = [],
        public ?string $remoteId = null,
    ) {}
}
