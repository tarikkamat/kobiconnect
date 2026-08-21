<?php

namespace App\Marketplaces\Trendyol\Mappers;

use App\Marketplaces\Data\AttributeData;
use App\Marketplaces\Data\AttributeValueData;
use App\Marketplaces\Data\MappingContext;
use App\Marketplaces\Support\Mapper;

/**
 * One entry of getCategoryAttributes' `categoryAttributes` array.
 *
 * That response never carries the values themselves - they come from
 * getCategoryAttributeValues, one call per (category, attribute) pair
 * (TRENDYOL.md 4.1.5) - so the caller merges the value rows into the entry
 * under `attributeValues` before mapping, and this stays pure.
 *
 * The five flags are carried into the canonical DTO because product validation
 * is built on them: `required` + `allowCustom = false` means a product cannot be
 * published before the value list is synced, `varianter`/`slicer` decide variant
 * grouping and are immutable once the product is approved (TRENDYOL.md 9.7).
 *
 * @implements Mapper<AttributeData>
 */
final class AttributeMapper implements Mapper
{
    /**
     * @param  array<string, mixed>  $remote
     */
    public function toCanonical(array $remote): AttributeData
    {
        $attribute = $remote['attribute'] ?? [];
        $attribute = is_array($attribute) ? $attribute : [];

        return new AttributeData(
            remoteId: (string) ($attribute['id'] ?? ''),
            name: (string) ($attribute['name'] ?? ''),
            isRequired: (bool) ($remote['required'] ?? false),
            allowsCustomValue: (bool) ($remote['allowCustom'] ?? false),
            allowsMultipleValues: (bool) ($remote['allowMultipleAttributeValues'] ?? false),
            isVarianter: (bool) ($remote['varianter'] ?? false),
            isSlicer: (bool) ($remote['slicer'] ?? false),
            values: $this->values($remote['attributeValues'] ?? []),
        );
    }

    /**
     * Trendyol's own catalog shape, i.e. the inverse of toCanonical. It is NOT a
     * product payload: whether createProducts/update want `attributeValueId` +
     * `customAttributeValue` or `attributeValueIds` + `attributeValue` is the
     * unresolved P0 contradiction of TRENDYOL.md 9.6 (three official sources,
     * three different answers, Ek A #1). The product serializers are written in
     * the phase that can verify the echo on stage.
     *
     * @param  AttributeData  $canonical
     * @return array<string, mixed>
     */
    public function toRemote(object $canonical, MappingContext $context): array
    {
        return [
            'attribute' => [
                'id' => (int) $canonical->remoteId,
                'name' => $canonical->name,
            ],
            'required' => $canonical->isRequired,
            'allowCustom' => $canonical->allowsCustomValue,
            'allowMultipleAttributeValues' => $canonical->allowsMultipleValues,
            'varianter' => $canonical->isVarianter,
            'slicer' => $canonical->isSlicer,
            'attributeValues' => array_map(
                static fn (AttributeValueData $value): array => [
                    'attributeValueId' => (int) $value->remoteId,
                    'attributeValueName' => $value->value,
                ],
                $canonical->values,
            ),
        ];
    }

    /**
     * @return list<AttributeValueData>
     */
    private function values(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $values = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            // Trendyol contradicts itself on the field name: the TR guide example
            // says `attributeValue`, the structure definition and Trendyol's own
            // plugin say `attributeValueName` (TRENDYOL.md 4.1.6, Ek A #23).
            // Read whichever arrives. Values also turn up space padded (9.9).
            $name = $row['attributeValueName'] ?? $row['attributeValue'] ?? '';

            $values[] = new AttributeValueData(
                value: trim((string) (is_scalar($name) ? $name : '')),
                remoteId: (string) ($row['attributeValueId'] ?? ''),
            );
        }

        return $values;
    }
}
