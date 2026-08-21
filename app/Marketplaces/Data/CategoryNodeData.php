<?php

namespace App\Marketplaces\Data;

/**
 * A node of a marketplace category tree. Only leaf nodes accept products.
 */
final readonly class CategoryNodeData
{
    /**
     * @param  list<CategoryNodeData>  $children
     */
    public function __construct(
        public string $remoteId,
        public string $name,
        public ?string $parentRemoteId = null,
        public bool $isLeaf = false,
        public array $children = [],
    ) {}
}
