<?php

declare(strict_types=1);

namespace App\Marketplaces\Hepsiburada\Mappers;

use App\Marketplaces\Data\AttributeData;
use App\Marketplaces\Data\AttributeValueData;
use App\Marketplaces\Data\MappingContext;
use App\Marketplaces\Support\Mapper;

/**
 * One entry of `getAllAttributesByCategory` (§4.1.2, measured).
 *
 * The response splits into three buckets and only two of them become
 * AttributeData (§5.6):
 *   `baseAttributes`    - the product envelope itself (merchantSku, UrunAdi,
 *                         Image1..N, price, stock ...). These are the mapper's
 *                         RESERVED keys, bound to ProductData/VariantData
 *                         fields, and never published as attributes.
 *   `attributes`        - category specific, isVarianter = false
 *   `variantAttributes` - what defines a variant, isVarianter = true
 *
 * Two measured facts that contradict the documentation:
 *   `id` is an OPAQUE string, not a Turkish slug: `000009D`, `Bluetooth`,
 *   `calisma_sekliNew1` all appear in one category. Never key a global
 *   attribute mapping on it - the same logical attribute gets a different id in
 *   a different category, which is why channel_attribute_mappings is keyed by
 *   (connection, remote_category_id, attribute_id).
 *
 *   `type` is lowercase and has FIVE values, not the documented two:
 *   string | integer | enum | media | video. `allowsCustomValue` carries only
 *   one bit of that, so the raw value travels in AttributeData::$type - local
 *   pre-validation needs it to tell a mandatory image URL field from free text.
 *
 * There is no `allowCustom` and no `slicer` on this API: custom values are
 * implied by `type !== 'enum'`, and isSlicer is always false.
 *
 * @implements Mapper<AttributeData>
 */
final class AttributeMapper implements Mapper
{
    /**
     * The caller merges the value rows in under `values` before mapping (they
     * come from a separate endpoint, one call per category × attribute pair),
     * and flags the bucket under `isVarianter`, so this stays pure.
     *
     * @param  array<string, mixed>  $remote
     */
    public function toCanonical(array $remote): AttributeData
    {
        $type = is_scalar($remote['type'] ?? null) ? mb_strtolower(trim((string) $remote['type']), 'UTF-8') : null;

        return new AttributeData(
            remoteId: (string) ($remote['id'] ?? ''),
            name: trim((string) ($remote['name'] ?? '')),
            isRequired: ($remote['mandatory'] ?? false) === true,
            // Only `enum` picks from a list; everything else takes free input.
            allowsCustomValue: $type !== 'enum',
            allowsMultipleValues: ($remote['multiValue'] ?? false) === true,
            isVarianter: ($remote['isVarianter'] ?? false) === true,
            // Hepsiburada has no slicer concept and will not grow one (§10 M5).
            isSlicer: false,
            values: $this->values($remote['values'] ?? []),
            type: $type === '' ? null : $type,
        );
    }

    /**
     * Hepsiburada's own attribute shape, i.e. the inverse of toCanonical. It is
     * NOT a product payload: on this marketplace a product's attributes are a
     * flat `{id: value}` map with no metadata at all (§9.4), which is
     * ProductMapper's job.
     *
     * @param  AttributeData  $canonical
     * @return array<string, mixed>
     */
    public function toRemote(object $canonical, MappingContext $context): array
    {
        return [
            'id' => $canonical->remoteId,
            'name' => $canonical->name,
            'mandatory' => $canonical->isRequired,
            'type' => $canonical->type ?? ($canonical->allowsCustomValue ? 'string' : 'enum'),
            'multiValue' => $canonical->allowsMultipleValues,
        ];
    }

    /**
     * The value endpoint answers `{id, value}` pairs, and it is `value` - never
     * `id` - that gets sent back on a product (§4.1.3).
     *
     * @return list<AttributeValueData>
     */
    public function values(mixed $rows): array
    {
        $values = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $value = $row['value'] ?? $row['name'] ?? null;

            if (! is_scalar($value) || trim((string) $value) === '') {
                continue;
            }

            $values[] = new AttributeValueData(
                value: trim((string) $value),
                remoteId: isset($row['id']) && is_scalar($row['id']) ? (string) $row['id'] : null,
            );
        }

        return $values;
    }
}
