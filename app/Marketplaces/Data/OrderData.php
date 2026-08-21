<?php

namespace App\Marketplaces\Data;

use App\Marketplaces\Data\Enums\CanonicalOrderStatus;
use DateTimeImmutable;

/**
 * A canonical marketplace order.
 *
 * The customer payload is personal data: it is stored encrypted and pruned
 * according to the retention policy, never logged.
 */
final readonly class OrderData
{
    /**
     * @param  list<OrderLineData>  $lines
     * @param  list<ShipmentData>  $shipments
     * @param  array{firstName?: string, lastName?: string, email?: string, phone?: string, identityNumber?: string, taxNumber?: string, shippingAddress?: array<string, mixed>, invoiceAddress?: array<string, mixed>}  $customer
     * @param  array{gross?: string, discount?: string, shipping?: string, commission?: string, net?: string}  $totals
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $remoteId,
        public string $remoteOrderNumber,
        public CanonicalOrderStatus $status,
        public string $externalStatus,
        public DateTimeImmutable $placedAt,
        public string $currency = 'TRY',
        public array $lines = [],
        public array $shipments = [],
        public array $customer = [],
        public array $totals = [],
        public array $raw = [],
    ) {}
}
