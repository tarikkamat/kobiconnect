<?php

namespace App\Marketplaces\Data;

use App\Marketplaces\Support\Exceptions\MappingException;

/**
 * Everything a mapper needs that lives outside the DTO it is mapping:
 * the resolved category, brand and attribute mappings for the connection, the
 * tenant level field overrides and the connection settings.
 *
 * Mappers receive this and stay pure - they never query the database.
 */
final readonly class MappingContext
{
    /**
     * @param  array<string, string>  $categoryIds  canonical category id => remote category id
     * @param  array<string, string>  $brandIds  canonical brand id => remote brand id
     * @param  array<string, string>  $attributeIds  canonical attribute code => remote attribute id
     * @param  array<string, array<string, string>>  $attributeValueIds  attribute code => [canonical value => remote value id]
     * @param  array<string, mixed>  $fieldOverrides  channel_connections.field_overrides
     * @param  array<string, mixed>  $settings  channel_connections.settings
     */
    public function __construct(
        public string $externalSellerId,
        public array $categoryIds = [],
        public array $brandIds = [],
        public array $attributeIds = [],
        public array $attributeValueIds = [],
        public array $fieldOverrides = [],
        public array $settings = [],
    ) {}

    /**
     * @throws MappingException
     */
    public function remoteCategoryId(string $categoryId): string
    {
        return $this->categoryIds[$categoryId] ?? throw MappingException::missingCategory($categoryId);
    }

    /**
     * @throws MappingException
     */
    public function remoteBrandId(string $brandId): string
    {
        return $this->brandIds[$brandId] ?? throw MappingException::missingBrand($brandId);
    }

    /**
     * @throws MappingException
     */
    public function remoteAttributeId(string $attributeCode): string
    {
        return $this->attributeIds[$attributeCode] ?? throw MappingException::missingAttribute($attributeCode);
    }

    /**
     * @throws MappingException
     */
    public function remoteAttributeValueId(string $attributeCode, string $value): string
    {
        return $this->attributeValueIds[$attributeCode][$value]
            ?? throw MappingException::missingAttributeValue($attributeCode, $value);
    }

    public function override(string $key, mixed $default = null): mixed
    {
        return data_get($this->fieldOverrides, $key, $default);
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }
}
