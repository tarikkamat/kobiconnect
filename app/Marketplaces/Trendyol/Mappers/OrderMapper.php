<?php

namespace App\Marketplaces\Trendyol\Mappers;

use App\Marketplaces\Data\Enums\CanonicalOrderStatus;
use App\Marketplaces\Data\MappingContext;
use App\Marketplaces\Data\OrderData;
use App\Marketplaces\Data\OrderLineData;
use App\Marketplaces\Data\ShipmentData;
use App\Marketplaces\Support\Mapper;
use App\Marketplaces\Trendyol\Enums\TrendyolPackageStatus;
use DateTimeImmutable;
use DateTimeZone;

/**
 * One Trendyol shipment package -> one canonical order.
 *
 * That is the correct grain, not a convenience: a partial cancellation or a
 * split keeps `orderNumber` but mints a NEW `shipmentPackageId` and a new cargo
 * barcode (TRENDYOL.md 4.4.1, 9.1). The package is what the seller picks,
 * invoices and ships, so it is what an order row represents; `orderNumber` is
 * carried alongside as the reconciliation key across sibling packages.
 *
 * Field names follow the guide pages, not the published OpenAPI schema: the
 * schema still lists the names Trendyol removed on 6 April 2026 (`id`,
 * `merchantSku`, `amount`, `price`, `grossAmount`, `productCode`,
 * `vatBaseAmount`...) and reading those gets null (TRENDYOL.md 12.1).
 *
 * Pure by contract: no database, no clock, no container. Everything it cannot
 * derive from the payload arrives through the MappingContext.
 *
 * @implements Mapper<OrderData>
 */
final class OrderMapper implements Mapper
{
    /**
     * Trendyol silently strips interior whitespace from barcodes on the way in
     * ("ABC 123" is stored as "ABC123"), so the value they hand back is the
     * canonical one and anything we key on has to be normalised the same way
     * (TRENDYOL.md 9.2).
     */
    public static function normaliseBarcode(mixed $barcode): ?string
    {
        if (! is_string($barcode)) {
            return null;
        }

        $normalised = preg_replace('/\s+/u', '', $barcode) ?? '';

        return $normalised === '' ? null : $normalised;
    }

    /**
     * @param  array<string, mixed>  $remote
     */
    public function toCanonical(array $remote): OrderData
    {
        $externalStatus = $this->text($remote['status'] ?? null)
            ?? $this->text($remote['shipmentPackageStatus'] ?? null)
            ?? '';

        $lines = $this->lines($remote);
        $histories = $this->histories($remote);

        return new OrderData(
            remoteId: $this->text($remote['shipmentPackageId'] ?? null) ?? '',
            remoteOrderNumber: $this->text($remote['orderNumber'] ?? null) ?? '',
            status: $this->canonicalStatus($externalStatus),
            externalStatus: $externalStatus,
            // Epoch milliseconds are absolute, so the "GMT +3" vs "GMT" label
            // contradiction of TRENDYOL.md 9.9 cannot change the instant; the raw
            // epoch stays in `raw` for stage calibration. Falling back keeps a
            // package with a broken date importable rather than rejecting it.
            placedAt: $this->timestamp($remote['orderDate'] ?? null)
                ?? $this->timestamp($remote['lastModifiedDate'] ?? null)
                ?? new DateTimeImmutable('@0', new DateTimeZone('UTC')),
            currency: $this->currency($remote, $lines),
            lines: $lines,
            shipments: [$this->shipment($remote, $externalStatus, $lines, $histories)],
            customer: $this->customer($remote),
            totals: $this->totals($remote),
            raw: $remote,
        );
    }

    /**
     * The inverse: the identifying skeleton of a package as Trendyol's own
     * package mutation endpoints take it (`shipmentPackageId` plus
     * `lines[{lineId, quantity}]`).
     *
     * It is deliberately not a fuller payload. Status updates, splitting,
     * cargo notification and invoicing are the outbox half of this phase and
     * live behind SupportsShipmentUpdates; emitting a speculative body here
     * would be a write path nothing calls and nothing verifies.
     *
     * @param  OrderData  $canonical
     * @return array<string, mixed>
     */
    public function toRemote(object $canonical, MappingContext $context): array
    {
        return [
            'shipmentPackageId' => $canonical->remoteId,
            'orderNumber' => $canonical->remoteOrderNumber,
            'status' => $canonical->externalStatus,
            'lines' => array_map(
                static fn (OrderLineData $line): array => [
                    'lineId' => $line->remoteId,
                    'quantity' => $line->quantity,
                ],
                $canonical->lines,
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $remote
     * @return list<OrderLineData>
     */
    private function lines(array $remote): array
    {
        $lines = [];

        foreach ($this->rows($remote['lines'] ?? null) as $line) {
            // Line status diverges from package status on partial cancellation
            // and partial non-supply, so it is parsed separately and never
            // inherited (TRENDYOL.md 5.3).
            $externalStatus = $this->text($line['orderLineItemStatusName'] ?? null) ?? '';

            $lines[] = new OrderLineData(
                remoteId: $this->text($line['lineId'] ?? null) ?? '',
                // merchantSku was removed from order responses on 6 April 2026
                // and replaced by stockCode (TRENDYOL.md 12.1).
                sku: $this->text($line['stockCode'] ?? null) ?? '',
                quantity: (int) ($line['quantity'] ?? 0),
                unitPrice: $this->decimal($line['lineUnitPrice'] ?? null) ?? '0',
                status: $this->canonicalStatus($externalStatus),
                externalStatus: $externalStatus,
                barcode: self::normaliseBarcode($line['barcode'] ?? null),
                discount: $this->decimal($line['lineTotalDiscount'] ?? null) ?? '0',
                // `commission` is a rate, not an amount.
                commission: $this->decimal($line['commission'] ?? null),
                vatRate: $this->decimal($line['vatRate'] ?? null),
            );
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $remote
     * @param  list<OrderLineData>  $lines
     * @param  list<array{status: string, occurredAt: DateTimeImmutable}>  $histories
     */
    private function shipment(array $remote, string $externalStatus, array $lines, array $histories): ShipmentData
    {
        return new ShipmentData(
            remoteId: $this->text($remote['shipmentPackageId'] ?? null) ?? '',
            status: $this->canonicalStatus($externalStatus),
            externalStatus: $externalStatus,
            orderRemoteId: $this->text($remote['orderNumber'] ?? null),
            cargoProvider: $this->text($remote['cargoProviderName'] ?? null),
            // Declared int64 but the real values overflow int64 and JavaScript's
            // MAX_SAFE_INTEGER: string end to end (TRENDYOL.md 9.9).
            trackingNumber: $this->text($remote['cargoTrackingNumber'] ?? null),
            trackingUrl: $this->text($remote['cargoTrackingLink'] ?? null),
            deci: is_numeric($remote['cargoDeci'] ?? null) ? (float) $remote['cargoDeci'] : null,
            // The package carries no shippedAt/deliveredAt field of its own;
            // packageHistories is where those instants actually live.
            shippedAt: $this->firstOccurrence($histories, TrendyolPackageStatus::Shipped),
            deliveredAt: $this->firstOccurrence($histories, TrendyolPackageStatus::Delivered),
            lineRemoteIds: array_map(static fn (OrderLineData $line): string => $line->remoteId, $lines),
        );
    }

    /**
     * Personal data, all of it: name, e-mail, phone, the full address with
     * coordinates and - on gold, fertiliser or orders above 5000 TRY - the
     * customer's national id in `identityNumber` (TRENDYOL.md 11.1).
     *
     * It leaves this mapper in one array so the persistence layer has exactly
     * one thing to encrypt and exactly one thing to prune.
     *
     * @param  array<string, mixed>  $remote
     * @return array{firstName?: string, lastName?: string, email?: string, phone?: string, identityNumber?: string, taxNumber?: string, shippingAddress?: array<string, mixed>, invoiceAddress?: array<string, mixed>}
     */
    private function customer(array $remote): array
    {
        $shipping = $this->object($remote['shipmentAddress'] ?? null);
        $invoice = $this->object($remote['invoiceAddress'] ?? null);

        $customer = [];

        $scalars = [
            'firstName' => $remote['customerFirstName'] ?? null,
            'lastName' => $remote['customerLastName'] ?? null,
            'email' => $remote['customerEmail'] ?? null,
            'phone' => $shipping['phone'] ?? null,
            // `customerTckn` is the OpenAPI name, `identityNumber` the one the
            // guide's response actually carries; take whichever arrives.
            'identityNumber' => $remote['identityNumber'] ?? $remote['customerTckn'] ?? null,
            // Only present when commercial=true.
            'taxNumber' => $remote['taxNumber'] ?? $invoice['taxNumber'] ?? null,
        ];

        foreach ($scalars as $key => $value) {
            $text = $this->text($value);

            if ($text !== null) {
                $customer[$key] = $text;
            }
        }

        if ($shipping !== []) {
            $customer['shippingAddress'] = $shipping;
        }

        if ($invoice !== []) {
            $customer['invoiceAddress'] = $invoice;
        }

        return $customer;
    }

    /**
     * `commission` is a percentage per line, not an amount, so no commission
     * total is derived from it here.
     *
     * @param  array<string, mixed>  $remote
     * @return array{gross?: string, discount?: string, shipping?: string, commission?: string, net?: string}
     */
    private function totals(array $remote): array
    {
        $totals = [];

        $fields = [
            'gross' => 'packageGrossAmount',
            'discount' => 'packageTotalDiscount',
            'net' => 'packageTotalPrice',
        ];

        foreach ($fields as $key => $field) {
            $value = $this->decimal($remote[$field] ?? null);

            if ($value !== null) {
                $totals[$key] = $value;
            }
        }

        return $totals;
    }

    /**
     * Package level currency is not guaranteed, so it is derived from the lines
     * first (TRENDYOL.md 9.9).
     *
     * @param  array<string, mixed>  $remote
     * @param  list<OrderLineData>  $lines
     */
    private function currency(array $remote, array $lines): string
    {
        foreach ($this->rows($remote['lines'] ?? null) as $line) {
            $currency = $this->text($line['currencyCode'] ?? null);

            if ($currency !== null) {
                return $currency;
            }
        }

        return $this->text($remote['currencyCode'] ?? null) ?? 'TRY';
    }

    /**
     * @param  array<string, mixed>  $remote
     * @return list<array{status: string, occurredAt: DateTimeImmutable}>
     */
    private function histories(array $remote): array
    {
        $histories = [];

        foreach ($this->rows($remote['packageHistories'] ?? null) as $entry) {
            $occurredAt = $this->timestamp($entry['createdDate'] ?? null);
            $status = $this->text($entry['status'] ?? null);

            if ($occurredAt === null || $status === null) {
                continue;
            }

            $histories[] = ['status' => $status, 'occurredAt' => $occurredAt];
        }

        usort($histories, static fn (array $a, array $b): int => $a['occurredAt'] <=> $b['occurredAt']);

        return $histories;
    }

    /**
     * @param  list<array{status: string, occurredAt: DateTimeImmutable}>  $histories
     */
    private function firstOccurrence(array $histories, TrendyolPackageStatus $status): ?DateTimeImmutable
    {
        foreach ($histories as $entry) {
            if (TrendyolPackageStatus::tryFromRemote($entry['status']) === $status) {
                return $entry['occurredAt'];
            }
        }

        return null;
    }

    /**
     * An unrecognised remote status is never dropped and never guessed at. The
     * raw string always travels in `externalStatus`, and the DTO gets the only
     * canonical state that triggers nothing but a stock reservation, so an
     * unknown package cannot be picked, invoiced or shipped by mistake.
     *
     * ponytail: PendingPayment is a stand-in with a known ceiling - the list
     * reads "Ödeme bekleniyor" for a status that is merely unrecognised, which
     * is operationally right (do not fulfil) but not literally true. The
     * upgrade is a CanonicalOrderStatus::Unknown case; it is reported as a core
     * change rather than made here because app/Marketplaces/Data is owned
     * elsewhere. Until then the raw value is shown beside the badge everywhere.
     */
    private function canonicalStatus(string $externalStatus): CanonicalOrderStatus
    {
        return TrendyolPackageStatus::tryFromRemote($externalStatus)?->toCanonical()
            ?? CanonicalOrderStatus::PendingPayment;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(mixed $value): array
    {
        $rows = [];

        foreach (is_array($value) ? $value : [] as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function object(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private function text(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    /**
     * Money is `number/double` in JSON and must never land in a float column;
     * four decimals matches the canonical decimal(14,4) (TRENDYOL.md 9.9).
     */
    private function decimal(mixed $value): ?string
    {
        return is_numeric($value) ? sprintf('%.4F', (float) $value) : null;
    }

    private function timestamp(mixed $value): ?DateTimeImmutable
    {
        if (! is_numeric($value)) {
            return null;
        }

        $milliseconds = (int) $value;

        if ($milliseconds <= 0) {
            return null;
        }

        return (new DateTimeImmutable('@'.intdiv($milliseconds, 1000)))
            ->setTimezone(new DateTimeZone('UTC'));
    }
}
