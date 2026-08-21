<?php

namespace App\Marketplaces\Trendyol\Mappers;

use App\Marketplaces\Data\BrandData;
use App\Marketplaces\Data\MappingContext;
use App\Marketplaces\Support\Mapper;

/**
 * getBrands and getBrandsByName return the same brand object in two different
 * envelopes - `{"brands":[...]}` and a bare array (TRENDYOL.md 4.1.1, 4.1.2) -
 * so unwrapping is the caller's job and this maps a single row.
 *
 * @implements Mapper<BrandData>
 */
final class BrandMapper implements Mapper
{
    /**
     * @param  array<string, mixed>  $remote
     */
    public function toCanonical(array $remote): BrandData
    {
        return new BrandData(
            remoteId: (string) ($remote['id'] ?? ''),
            name: trim((string) ($remote['name'] ?? '')),
        );
    }

    /**
     * Trendyol's own catalog shape, i.e. the inverse of toCanonical. It is not a
     * product payload: the create/update attribute payload is the unresolved P0
     * of TRENDYOL.md 9.6 and is deliberately not written in this phase.
     *
     * @param  BrandData  $canonical
     * @return array<string, mixed>
     */
    public function toRemote(object $canonical, MappingContext $context): array
    {
        return [
            'id' => (int) $canonical->remoteId,
            'name' => $canonical->name,
        ];
    }
}
