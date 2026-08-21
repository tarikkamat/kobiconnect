<?php

namespace App\Marketplaces\Data;

use App\Marketplaces\Data\Enums\CanonicalClaimStatus;
use DateTimeImmutable;

/**
 * A return claim. The header carries no remote status; the displayed status is
 * derived from the items.
 */
final readonly class ClaimData
{
    /**
     * @param  list<ClaimItemData>  $items
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $remoteId,
        public string $orderRemoteId,
        public DateTimeImmutable $openedAt,
        public array $items = [],
        public array $raw = [],
    ) {}

    /**
     * The most urgent item status, used as the claim status in the UI.
     */
    public function status(): ?CanonicalClaimStatus
    {
        foreach ($this->items as $item) {
            if ($item->status->isActionable()) {
                return $item->status;
            }
        }

        return $this->items[0]->status ?? null;
    }
}
