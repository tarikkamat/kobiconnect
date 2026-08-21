<?php

namespace App\Marketplaces\Data;

use App\Marketplaces\Data\Enums\CanonicalOrderStatus;
use DateTimeImmutable;

/**
 * A shipment package of an order.
 */
final readonly class ShipmentData
{
    /**
     * @param  list<string>  $lineRemoteIds
     */
    public function __construct(
        public string $remoteId,
        public CanonicalOrderStatus $status,
        public string $externalStatus,
        public ?string $orderRemoteId = null,
        public ?string $cargoProvider = null,
        public ?string $trackingNumber = null,
        public ?string $trackingUrl = null,
        public ?float $deci = null,
        public ?DateTimeImmutable $shippedAt = null,
        public ?DateTimeImmutable $deliveredAt = null,
        public array $lineRemoteIds = [],
    ) {}
}
