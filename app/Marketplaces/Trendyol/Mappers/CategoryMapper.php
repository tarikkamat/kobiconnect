<?php

namespace App\Marketplaces\Trendyol\Mappers;

use App\Marketplaces\Data\CategoryNodeData;
use App\Marketplaces\Data\MappingContext;
use App\Marketplaces\Support\Mapper;

/**
 * getCategoryTree answers with a bare, recursive array; there is no pagination
 * and the whole tree arrives in one response (TRENDYOL.md 4.1.4).
 *
 * Category names are localised, ids are not - never key on the name.
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
        $children = [];
        $subCategories = $remote['subCategories'] ?? [];

        if (is_array($subCategories)) {
            foreach ($subCategories as $subCategory) {
                if (is_array($subCategory)) {
                    $children[] = $this->toCanonical($subCategory);
                }
            }
        }

        return new CategoryNodeData(
            remoteId: (string) ($remote['id'] ?? ''),
            name: (string) ($remote['name'] ?? ''),
            parentRemoteId: isset($remote['parentId']) ? (string) $remote['parentId'] : null,
            // A node with no subCategories is a leaf, and only a leaf accepts
            // products (TRENDYOL.md K9) - assert this before publishing.
            isLeaf: $children === [],
            children: $children,
        );
    }

    /**
     * Trendyol's own catalog shape, i.e. the inverse of toCanonical. It is not a
     * product payload: the create/update attribute payload is the unresolved P0
     * of TRENDYOL.md 9.6 and is deliberately not written in this phase.
     *
     * @param  CategoryNodeData  $canonical
     * @return array<string, mixed>
     */
    public function toRemote(object $canonical, MappingContext $context): array
    {
        return [
            'id' => (int) $canonical->remoteId,
            'name' => $canonical->name,
            'parentId' => $canonical->parentRemoteId === null ? null : (int) $canonical->parentRemoteId,
            'subCategories' => array_map(
                fn (CategoryNodeData $child): array => $this->toRemote($child, $context),
                $canonical->children,
            ),
        ];
    }
}
