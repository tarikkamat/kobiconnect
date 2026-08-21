<?php

namespace App\Marketplaces\Contracts;

use App\Marketplaces\Data\MappingContext;
use App\Marketplaces\Data\PriceData;
use App\Marketplaces\Data\PullPage;
use App\Marketplaces\Data\PushResult;

interface SupportsPriceSync
{
    /**
     * @param  list<PriceData>  $prices
     */
    public function pushPrices(array $prices, MappingContext $context): PushResult;

    /**
     * Remote prices, used by reconciliation to detect drift.
     *
     * @return PullPage<PriceData>
     */
    public function pullPrices(?string $cursor = null): PullPage;
}
