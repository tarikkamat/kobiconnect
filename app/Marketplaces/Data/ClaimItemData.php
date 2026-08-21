<?php

namespace App\Marketplaces\Data;

use App\Marketplaces\Data\Enums\CanonicalClaimStatus;

/**
 * A single claimed item.
 */
final readonly class ClaimItemData
{
    public function __construct(
        public string $remoteId,
        public CanonicalClaimStatus $status,
        public string $externalStatus,
        public int $quantity = 1,
        public ?string $orderLineRemoteId = null,
        public ?string $reason = null,
        public ?string $reasonCode = null,
    ) {}
}
