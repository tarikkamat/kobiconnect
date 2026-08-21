<?php

namespace App\Marketplaces\Data;

/**
 * A brand as known by the marketplace.
 */
final readonly class BrandData
{
    public function __construct(
        public string $remoteId,
        public string $name,
    ) {}
}
