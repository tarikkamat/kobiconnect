<?php

namespace App\Marketplaces\Contracts;

use App\Marketplaces\Data\MappingContext;
use App\Marketplaces\Data\ProductData;
use App\Marketplaces\Data\PullPage;
use App\Marketplaces\Data\PushResult;
use DateTimeImmutable;

interface SupportsProductSync
{
    /**
     * @param  list<ProductData>  $products
     */
    public function createProducts(array $products, MappingContext $context): PushResult;

    /**
     * @param  list<ProductData>  $products
     */
    public function updateProducts(array $products, MappingContext $context): PushResult;

    /**
     * @return PullPage<ProductData>
     */
    public function pullProducts(?DateTimeImmutable $updatedSince = null, ?string $cursor = null): PullPage;

    /**
     * Read back the item level results of an accepted push.
     */
    public function productPushResult(string $remoteBatchId): PushResult;
}
