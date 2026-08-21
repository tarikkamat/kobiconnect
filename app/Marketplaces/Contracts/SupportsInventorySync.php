<?php

namespace App\Marketplaces\Contracts;

use App\Marketplaces\Data\MappingContext;
use App\Marketplaces\Data\PullPage;
use App\Marketplaces\Data\PushResult;
use App\Marketplaces\Data\StockData;

interface SupportsInventorySync
{
    /**
     * @param  list<StockData>  $stock
     */
    public function pushStock(array $stock, MappingContext $context): PushResult;

    /**
     * Remote quantities, used by reconciliation to detect drift.
     *
     * @return PullPage<StockData>
     */
    public function pullStock(?string $cursor = null): PullPage;
}
