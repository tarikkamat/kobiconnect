<?php

namespace App\Marketplaces\Data;

/**
 * A single attribute value, either as an option in a remote catalog or as a
 * value assigned to a product or variant.
 */
final readonly class AttributeValueData
{
    public function __construct(
        public string $value,
        public ?string $attributeCode = null,
        public ?string $remoteId = null,
    ) {}
}
