<?php

declare(strict_types=1);

namespace App\Marketplaces\Hepsiburada\Mappers;

use App\Marketplaces\Data\Enums\CanonicalOrderStatus;
use App\Marketplaces\Data\MappingContext;
use App\Marketplaces\Data\OrderData;
use App\Marketplaces\Data\OrderLineData;
use App\Marketplaces\Hepsiburada\Enums\HepsiburadaOrderStatus;
use App\Marketplaces\Support\Mapper;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * Hepsiburada order rows -> canonical orders.
 *
 * ⚠️ The REST row shape is the biggest unmeasured hole in this adapter: the SIT
 * merchant has zero orders, so `GET /orders/merchantid/{id}` only ever answered
 * `{"totalCount":0,...,"items":[]}` (§11.2, Ek A #6). The field names below come
 * from the documented `create-order` webhook payload, whose equivalence to the
 * REST body is NOT verified.
 *
 * Everything is therefore read defensively:
 *  - `items[]` are LINE rows, each repeating the customer and address. They are
 *    grouped by `orderNumber` (falling back to `packageNumber`, then the row's
 *    own id), so the mapper behaves correctly whether the service returns one
 *    row per order or one row per line.
 *  - Money arrives as `{currency, amount}` objects; a bare number is accepted too.
 *  - An unreadable date yields epoch 0 rather than dropping the order.
 *
 * Pure by contract: no database, no clock, no container.
 *
 * @implements Mapper<OrderData>
 */
final class OrderMapper implements Mapper
{
    /**
     * Group flat line rows into orders.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<OrderData>
     */
    public function fromLines(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $index => $row) {
            $key = $this->text($row['orderNumber'] ?? null)
                ?? $this->text($row['packageNumber'] ?? null)
                ?? $this->text($row['id'] ?? null)
                ?? (string) $index;

            $grouped[$key][] = $row;
        }

        $orders = [];

        foreach ($grouped as $key => $lines) {
            $orders[] = $this->toCanonical([...$lines[0], 'orderNumber' => $key, 'lines' => $lines]);
        }

        return $orders;
    }

    /**
     * @param  array<string, mixed>  $remote
     */
    public function toCanonical(array $remote): OrderData
    {
        $rows = $this->rows($remote['lines'] ?? $remote['items'] ?? null);
        $rows = $rows === [] ? [$remote] : $rows;
        $lines = array_map($this->line(...), $rows);

        // No package/order level status is documented; the line statuses are.
        // The most urgent one wins so a partially cancelled order never reads
        // as fully cancelled.
        $externalStatus = $this->text($remote['status'] ?? null) ?? $lines[0]->externalStatus;
        $orderNumber = $this->text($remote['orderNumber'] ?? null) ?? $this->text($remote['id'] ?? null) ?? '';

        return new OrderData(
            remoteId: $this->text($remote['packageNumber'] ?? null) ?? $orderNumber,
            remoteOrderNumber: $orderNumber,
            status: $this->canonicalStatus($externalStatus),
            externalStatus: $externalStatus,
            placedAt: $this->timestamp($remote['orderDate'] ?? null)
                ?? $this->timestamp($remote['lastStatusUpdateDate'] ?? null)
                ?? new DateTimeImmutable('@0', new DateTimeZone('UTC')),
            currency: $this->currency($remote, $rows),
            lines: $lines,
            // Package level data is a v1.1 surface (`/packages/...`); the order
            // read carries no shipment object of its own.
            shipments: [],
            customer: $this->customer($remote),
            totals: $this->totals($rows),
            raw: $remote,
        );
    }

    /**
     * The identifying skeleton of an order. Hepsiburada order mutations
     * (packaging, splitting, invoicing, cancellation) are out of MVP scope, so
     * no speculative write body is emitted here.
     *
     * @param  OrderData  $canonical
     * @return array<string, mixed>
     */
    public function toRemote(object $canonical, MappingContext $context): array
    {
        return [
            'orderNumber' => $canonical->remoteOrderNumber,
            'status' => $canonical->externalStatus,
            'items' => array_map(
                static fn (OrderLineData $line): array => [
                    'id' => $line->remoteId,
                    'sku' => $line->sku,
                    'quantity' => $line->quantity,
                ],
                $canonical->lines,
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function line(array $row): OrderLineData
    {
        $externalStatus = $this->text($row['status'] ?? null) ?? '';

        return new OrderLineData(
            remoteId: $this->text($row['lineItemId'] ?? $row['id'] ?? null) ?? '',
            // `sku` on an order line is the HEPSIBURADA sku (e.g. HBV00000NE0YY),
            // not our merchantSku - never key local inventory on it directly.
            sku: $this->text($row['merchantSku'] ?? $row['sku'] ?? null) ?? '',
            quantity: (int) ($row['quantity'] ?? 0),
            unitPrice: $this->money($row['unitPrice'] ?? null) ?? '0',
            status: $this->canonicalStatus($externalStatus),
            externalStatus: $externalStatus,
            barcode: $this->text($row['barcode'] ?? null),
            discount: $this->money($this->object($row['hbDiscount'] ?? null)['totalPrice'] ?? null) ?? '0',
            commission: $this->money($row['commission'] ?? null),
            vatRate: is_numeric($row['vatRate'] ?? null) ? sprintf('%.4F', (float) $row['vatRate']) : null,
        );
    }

    /**
     * Personal data, all of it: name, e-mail, phone, the full address and - on
     * the invoice object - the customer's national id (`turkishIdentityNumber`).
     * It leaves the mapper in one array so persistence has exactly one thing to
     * encrypt and exactly one thing to prune (BACKEND-PLAN §13).
     *
     * @param  array<string, mixed>  $remote
     * @return array{firstName?: string, lastName?: string, email?: string, phone?: string, identityNumber?: string, taxNumber?: string, shippingAddress?: array<string, mixed>, invoiceAddress?: array<string, mixed>}
     */
    private function customer(array $remote): array
    {
        $shipping = $this->object($remote['shippingAddress'] ?? null);
        $invoice = $this->object($remote['invoice'] ?? null);
        $customer = [];

        // Hepsiburada sends one `customerName` string, not a first/last pair.
        // Splitting on the last space is a convention, not a fact; the raw
        // value survives untouched in OrderData::$raw.
        $name = $this->text($remote['customerName'] ?? $shipping['name'] ?? null);

        if ($name !== null) {
            $position = mb_strrpos($name, ' ');
            $customer['firstName'] = $position === false ? $name : mb_substr($name, 0, $position);

            if ($position !== false) {
                $customer['lastName'] = mb_substr($name, $position + 1);
            }
        }

        $scalars = [
            'email' => $shipping['email'] ?? null,
            'phone' => $shipping['phoneNumber'] ?? null,
            'identityNumber' => $invoice['turkishIdentityNumber'] ?? null,
            'taxNumber' => $invoice['taxNumber'] ?? null,
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
     * @param  list<array<string, mixed>>  $rows
     * @return array{gross?: string, discount?: string, shipping?: string, commission?: string, net?: string}
     */
    private function totals(array $rows): array
    {
        $gross = 0.0;
        $discount = 0.0;
        $commission = 0.0;

        foreach ($rows as $row) {
            $gross += (float) ($this->money($row['totalPrice'] ?? null) ?? 0);
            $discount += (float) ($this->money($this->object($row['hbDiscount'] ?? null)['totalPrice'] ?? null) ?? 0);
            $commission += (float) ($this->money($row['commission'] ?? null) ?? 0);
        }

        return [
            'gross' => sprintf('%.4F', $gross),
            'discount' => sprintf('%.4F', $discount),
            'commission' => sprintf('%.4F', $commission),
            'net' => sprintf('%.4F', $gross - $discount),
        ];
    }

    /**
     * @param  array<string, mixed>  $remote
     * @param  list<array<string, mixed>>  $rows
     */
    private function currency(array $remote, array $rows): string
    {
        foreach ($rows as $row) {
            $currency = $this->text($this->object($row['totalPrice'] ?? null)['currency'] ?? null);

            if ($currency !== null) {
                return $currency;
            }
        }

        return $this->text($remote['currency'] ?? null) ?? 'TRY';
    }

    /**
     * An unrecognised status is never guessed at: the raw string travels in
     * `externalStatus` and the canonical value is `Unknown`, which triggers
     * nothing (§5.8).
     */
    private function canonicalStatus(string $externalStatus): CanonicalOrderStatus
    {
        return HepsiburadaOrderStatus::tryFromRemote($externalStatus)?->toCanonical()
            ?? CanonicalOrderStatus::Unknown;
    }

    /**
     * `{currency, amount}` or a bare number. Four decimals matches the
     * canonical decimal(14,4); money never lands in a float column.
     */
    private function money(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $value['amount'] ?? null;
        }

        return is_numeric($value) ? sprintf('%.4F', (float) $value) : null;
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
     * ⚠️ The date format is undocumented. ISO-8601 strings and epoch
     * milliseconds are both accepted; anything else yields null and the caller
     * falls back rather than dropping the order.
     */
    private function timestamp(mixed $value): ?DateTimeImmutable
    {
        if (is_numeric($value)) {
            $milliseconds = (int) $value;

            return $milliseconds > 0
                ? (new DateTimeImmutable('@'.intdiv($milliseconds, 1000)))->setTimezone(new DateTimeZone('UTC'))
                : null;
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'));
        } catch (Throwable) {
            return null;
        }
    }
}
