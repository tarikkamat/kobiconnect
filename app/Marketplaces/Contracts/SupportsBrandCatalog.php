<?php

namespace App\Marketplaces\Contracts;

use App\Marketplaces\Data\BrandData;
use App\Marketplaces\Data\PullPage;

interface SupportsBrandCatalog
{
    /**
     * @return PullPage<BrandData>
     */
    public function brands(?string $cursor = null): PullPage;

    public function findBrandByName(string $name): ?BrandData;
}
