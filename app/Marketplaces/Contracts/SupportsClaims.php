<?php

namespace App\Marketplaces\Contracts;

use App\Marketplaces\Data\ClaimData;
use App\Marketplaces\Data\MappingContext;
use App\Marketplaces\Data\PullPage;
use App\Marketplaces\Data\PushResult;
use DateTimeImmutable;

interface SupportsClaims
{
    /**
     * @return PullPage<ClaimData>
     */
    public function pullClaims(?DateTimeImmutable $updatedSince = null, ?string $cursor = null): PullPage;

    /**
     * @param  list<string>  $itemRemoteIds
     */
    public function approveClaimItems(ClaimData $claim, array $itemRemoteIds, MappingContext $context): PushResult;

    /**
     * @param  list<string>  $itemRemoteIds
     */
    public function rejectClaimItems(ClaimData $claim, array $itemRemoteIds, string $reason, MappingContext $context): PushResult;
}
