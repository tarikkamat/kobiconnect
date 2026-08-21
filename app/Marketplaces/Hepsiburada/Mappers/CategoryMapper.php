<?php

declare(strict_types=1);

namespace App\Marketplaces\Hepsiburada\Mappers;

use App\Marketplaces\Data\CategoryNodeData;
use App\Marketplaces\Data\MappingContext;
use App\Marketplaces\Support\Mapper;

/**
 * One row of `get-all-categories`. The response is a FLAT paged list, not a
 * tree: hierarchy is rebuilt client side from `parentCategoryId` (§4.1.1).
 *
 * @implements Mapper<CategoryNodeData>
 */
final class CategoryMapper implements Mapper
{
    /**
     * @param  array<string, mixed>  $remote
     */
    public function toCanonical(array $remote): CategoryNodeData
    {
        $parent = $remote['parentCategoryId'] ?? null;

        return new CategoryNodeData(
            remoteId: (string) ($remote['categoryId'] ?? ''),
            // `displayName` is what the seller recognises in the picker, but
            // only `name` is guaranteed present.
            name: (string) ($remote['displayName'] ?? $remote['name'] ?? ''),
            parentRemoteId: is_scalar($parent) && (string) $parent !== '' ? (string) $parent : null,
            // Eligibility is the AND of THREE flags, not `leaf` alone (§9.8,
            // measured): categoryId 400276 is leaf:true, status:"ACTIVE" and
            // available:false, and every upload under it is refused. Since
            // `isLeaf` documents "only leaf nodes accept products", the
            // publishable-leaf reading is the correct one to fill.
            isLeaf: ($remote['leaf'] ?? false) === true
                && ($remote['status'] ?? null) === 'ACTIVE'
                && ($remote['available'] ?? false) === true,
        );
    }

    /**
     * Hepsiburada's own catalog shape, i.e. the inverse of toCanonical. There
     * is no category write endpoint; this exists for round-trip testing.
     *
     * `paths` is a string ARRAY (measured), against every source that called it
     * a single breadcrumb string.
     *
     * @param  CategoryNodeData  $canonical
     * @return array<string, mixed>
     */
    public function toRemote(object $canonical, MappingContext $context): array
    {
        return [
            'categoryId' => (int) $canonical->remoteId,
            'name' => $canonical->name,
            'displayName' => $canonical->name,
            'parentCategoryId' => $canonical->parentRemoteId === null ? null : (int) $canonical->parentRemoteId,
            'leaf' => $canonical->isLeaf,
            'status' => $canonical->isLeaf ? 'ACTIVE' : 'INACTIVE',
            'available' => $canonical->isLeaf,
        ];
    }
}
