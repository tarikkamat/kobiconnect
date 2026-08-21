<?php

namespace App\Marketplaces\Data;

use App\Marketplaces\Data\Enums\CanonicalListingStatus;

/**
 * A canonical product. The reference is the local identity used to correlate
 * item level results returned by an asynchronous push.
 */
final readonly class ProductData
{
    /**
     * @param  list<VariantData>  $variants
     * @param  list<AttributeValueData>  $attributes
     * @param  list<string>  $images
     */
    public function __construct(
        public string $reference,
        public string $name,
        public ?string $description = null,
        public ?string $categoryId = null,
        public ?string $brandId = null,
        public ?CanonicalListingStatus $status = null,
        public array $variants = [],
        public array $attributes = [],
        public array $images = [],
        public ?string $remoteId = null,
    ) {}
}
