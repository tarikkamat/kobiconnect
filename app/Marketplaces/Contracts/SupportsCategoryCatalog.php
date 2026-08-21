<?php

namespace App\Marketplaces\Contracts;

use App\Marketplaces\Data\AttributeData;
use App\Marketplaces\Data\CategoryNodeData;

interface SupportsCategoryCatalog
{
    /**
     * The full category tree. Refreshed on a schedule, not on demand.
     *
     * @return list<CategoryNodeData>
     */
    public function categoryTree(): array;

    /**
     * The attributes a category accepts, with the flags local pre-validation
     * needs before a product is pushed.
     *
     * @return list<AttributeData>
     */
    public function categoryAttributes(string $remoteCategoryId): array;
}
