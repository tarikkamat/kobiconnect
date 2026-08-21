<?php

namespace App\Marketplaces\Contracts;

use App\Marketplaces\Data\OrderData;
use App\Marketplaces\Data\PullPage;
use DateTimeImmutable;

interface SupportsOrderSync
{
    /**
     * @return PullPage<OrderData>
     */
    public function pullOrders(?DateTimeImmutable $updatedSince = null, ?string $cursor = null): PullPage;

    public function pullOrder(string $remoteOrderId): ?OrderData;
}
