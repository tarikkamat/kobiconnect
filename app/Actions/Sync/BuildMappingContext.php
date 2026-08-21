<?php

declare(strict_types=1);

namespace App\Actions\Sync;

use App\Marketplaces\Data\Enums\OperationType;
use App\Marketplaces\Data\MappingContext;
use App\Marketplaces\Support\Capability;
use App\Models\ChannelAttributeMapping;
use App\Models\ChannelAttributeValueMapping;
use App\Models\ChannelBrandMapping;
use App\Models\ChannelCategoryMapping;
use App\Models\ChannelConnection;

/**
 * Everything a mapper needs from the database, resolved once per push so the
 * mappers themselves stay pure.
 *
 * The category, brand and attribute maps are only loaded for product pushes:
 * a stock or price push is keyed by barcode and would pay four joins for
 * nothing, and those are the pushes that run all day.
 */
final class BuildMappingContext
{
    public function __invoke(ChannelConnection $connection, OperationType $operation): MappingContext
    {
        $catalog = $operation->capability() === Capability::ProductSync;

        return new MappingContext(
            externalSellerId: (string) $connection->external_seller_id,
            categoryIds: $catalog ? $this->categories($connection) : [],
            brandIds: $catalog ? $this->brands($connection) : [],
            attributeIds: $catalog ? $this->attributes($connection) : [],
            attributeValueIds: $catalog ? $this->attributeValues($connection) : [],
            fieldOverrides: $connection->field_overrides,
            settings: $connection->settings,
        );
    }

    /**
     * @return array<string, string>
     */
    private function categories(ChannelConnection $connection): array
    {
        return $this->stringMap(
            ChannelCategoryMapping::query()
                ->where('connection_id', $connection->getKey())
                ->pluck('remote_category_id', 'category_id')
                ->all(),
        );
    }

    /**
     * @return array<string, string>
     */
    private function brands(ChannelConnection $connection): array
    {
        return $this->stringMap(
            ChannelBrandMapping::query()
                ->where('connection_id', $connection->getKey())
                ->pluck('remote_brand_id', 'brand_id')
                ->all(),
        );
    }

    /**
     * MappingContext keys attributes by code alone while the mapping table is
     * per (connection, remote category, attribute). A code mapped differently
     * in two categories therefore collapses to the last row.
     *
     * ponytail: harmless while one category maps one way; the day it is not,
     * the fix is a category aware key in MappingContext, not here.
     *
     * @return array<string, string>
     */
    private function attributes(ChannelConnection $connection): array
    {
        return $this->stringMap(
            ChannelAttributeMapping::query()
                ->where('connection_id', $connection->getKey())
                ->join('attributes', 'attributes.id', '=', 'channel_attribute_mappings.attribute_id')
                ->pluck('channel_attribute_mappings.remote_attribute_id', 'attributes.code')
                ->all(),
        );
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function attributeValues(ChannelConnection $connection): array
    {
        $rows = ChannelAttributeValueMapping::query()
            ->join(
                'channel_attribute_mappings',
                'channel_attribute_mappings.id',
                '=',
                'channel_attribute_value_mappings.mapping_id',
            )
            ->join('attributes', 'attributes.id', '=', 'channel_attribute_mappings.attribute_id')
            ->join('attribute_values', 'attribute_values.id', '=', 'channel_attribute_value_mappings.attribute_value_id')
            ->where('channel_attribute_mappings.connection_id', $connection->getKey())
            ->get([
                'attributes.code as attribute_code',
                'attribute_values.value as value',
                'channel_attribute_value_mappings.remote_value_id as remote_value_id',
            ]);

        $map = [];

        foreach ($rows as $row) {
            $map[(string) $row->getAttribute('attribute_code')][(string) $row->getAttribute('value')]
                = (string) $row->getAttribute('remote_value_id');
        }

        return $map;
    }

    /**
     * @param  array<array-key, mixed>  $map
     * @return array<string, string>
     */
    private function stringMap(array $map): array
    {
        $result = [];

        foreach ($map as $key => $value) {
            $result[(string) $key] = (string) $value;
        }

        return $result;
    }
}
