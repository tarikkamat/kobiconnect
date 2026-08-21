<?php

namespace App\Marketplaces\Support\Exceptions;

/**
 * Thrown when a mapper needs a resolved mapping the context does not carry.
 *
 * Mappers stay pure: they never hit the database to fill a gap themselves.
 */
class MappingException extends MarketplaceException
{
    public static function missingCategory(string $categoryId): self
    {
        return new self("No remote category mapping resolved for category [{$categoryId}].");
    }

    public static function missingBrand(string $brandId): self
    {
        return new self("No remote brand mapping resolved for brand [{$brandId}].");
    }

    public static function missingAttribute(string $attributeCode): self
    {
        return new self("No remote attribute mapping resolved for attribute [{$attributeCode}].");
    }

    public static function missingAttributeValue(string $attributeCode, string $value): self
    {
        return new self("No remote value mapping resolved for attribute [{$attributeCode}] value [{$value}].");
    }
}
