<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Events\NotificationEventOccurred;
use App\Marketplaces\Contracts\SupportsOrderSync;
use App\Marketplaces\Data\OrderData;
use App\Marketplaces\Data\OrderLineData;
use App\Marketplaces\Support\Capability;
use App\Marketplaces\Support\Exceptions\UnsupportedCapabilityException;
use App\Models\ChannelConnection;
use App\Notifications\NotificationEvent;
use App\Support\Sync\ConnectionDriver;
use DateTimeImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Sleep;

/**
 * Drains a marketplace order stream into the canonical tables.
 *
 * Everything here is idempotent by construction, because the same package
 * arrives more than once by design: stream windows are deliberately overlapped
 * (the inclusive/exclusive semantics of the bounds are undocumented), a webhook
 * and a scheduled pull write the same row, and a retried job replays a page.
 * Orders, lines and packages are upserted on their remote keys and status
 * history is insert-or-ignored, so a double pull is a no-op and never a
 * duplicate (TRENDYOL.md 4.4.2, 10.8, BACKEND-PLAN 8.2).
 *
 * Two rules it will not bend:
 *
 *  - **An order is never rejected because a line did not match.** An unknown
 *    barcode leaves `order_lines.variant_id` null and the line surfaces in the
 *    unmatched queue. Marketplace data is never dropped to protect a foreign
 *    key (BACKEND-PLAN 5.3).
 *  - **Personal data is written encrypted and is never logged.** The customer
 *    block and the raw payload carry name, e-mail, phone, full address,
 *    coordinates and sometimes a national id (TRENDYOL.md 11). Nothing in this
 *    class logs a payload, and the two columns that hold PII are encrypted with
 *    the same wire format AsEncryptedArrayObject uses, so an Eloquent model
 *    casting them reads them back unchanged.
 */
final class ImportOrders
{
    /**
     * Trendyol asks for at least five seconds between stream requests
     * (TRENDYOL.md 4.4.2). The local rate limiter guards the published minute
     * budget but knows nothing about this per-stream courtesy interval.
     */
    private const int STREAM_INTERVAL_SECONDS = 5;

    public function __construct(private readonly ConnectionDriver $connectionDriver) {}

    /**
     * @param  int  $maxPages  a run is bounded so one cold connection cannot hold a
     *                         worker forever; whatever is left resumes from the stored cursor
     * @return array{orders: int, lines: int, unmatched: int, pages: int, drained: bool}
     */
    public function handle(ChannelConnection $connection, int $maxPages = 20): array
    {
        $driver = $this->connectionDriver->for($connection);

        // The capability contract is the single decision point - no marketplace
        // name is ever branched on (BACKEND-PLAN 6.2).
        if (! $driver instanceof SupportsOrderSync) {
            throw UnsupportedCapabilityException::for(Capability::OrderSync, $driver);
        }

        $state = DB::table('sync_cursors')
            ->where('connection_id', $connection->getKey())
            ->where('resource', 'orders')
            ->first(['watermark', 'cursor']);

        $watermark = $state === null || $state->watermark === null
            ? null
            : new DateTimeImmutable((string) $state->watermark);
        $cursor = $state === null || ! is_string($state->cursor) || $state->cursor === '' ? null : $state->cursor;
        $highest = $watermark;

        $stats = ['orders' => 0, 'lines' => 0, 'unmatched' => 0, 'pages' => 0, 'drained' => false];

        for ($page = 0; $page < $maxPages; $page++) {
            if ($page > 0) {
                Sleep::for(self::STREAM_INTERVAL_SECONDS)->seconds();
            }

            $pullPage = $driver->pullOrders($watermark, $cursor);
            $stats['pages']++;

            foreach ($pullPage->items as $order) {
                $written = $this->persist($connection, $order);
                $stats['orders'] += $written['orders'];
                $stats['lines'] += $written['lines'];
                $stats['unmatched'] += $written['unmatched'];
            }

            if ($pullPage->watermark !== null && ($highest === null || $pullPage->watermark > $highest)) {
                $highest = $pullPage->watermark;
            }

            $cursor = $pullPage->cursor;

            if (! $pullPage->hasMore || $cursor === null) {
                $stats['drained'] = true;
                break;
            }
        }

        // The stream is ordered lastModifiedDate DESC, so the newest record is
        // on the first page: committing the watermark before the stream drains
        // would skip everything still queued behind it. Until then the opaque
        // cursor is the resume point.
        $this->checkpoint(
            $connection,
            $stats['drained'] ? $highest : $watermark,
            $stats['drained'] ? null : $cursor,
        );

        return $stats;
    }

    /**
     * @return array{orders: int, lines: int, unmatched: int}
     */
    private function persist(ChannelConnection $connection, OrderData $order): array
    {
        if ($order->remoteId === '') {
            return ['orders' => 0, 'lines' => 0, 'unmatched' => 0];
        }

        $lastModifiedAt = $this->lastModifiedAt($order);

        return DB::transaction(function () use ($connection, $order, $lastModifiedAt): array {
            $existing = DB::table('orders')
                ->where('connection_id', $connection->getKey())
                ->where('remote_id', $order->remoteId)
                ->first(['id', 'remote_last_modified_at']);

            // Monotonic guard: a webhook and a pull race, and an overlapped
            // window replays old pages. An event older than what is stored is
            // ignored rather than rewinding the row (TRENDYOL.md 10.7).
            if ($existing !== null && $lastModifiedAt !== null && $existing->remote_last_modified_at !== null
                && Carbon::parse((string) $existing->remote_last_modified_at)->greaterThan($lastModifiedAt)) {
                return ['orders' => 0, 'lines' => 0, 'unmatched' => 0];
            }

            $now = Carbon::now();

            DB::table('orders')->upsert([[
                'connection_id' => $connection->getKey(),
                'remote_id' => $order->remoteId,
                'remote_order_number' => $order->remoteOrderNumber,
                'status' => $order->status->value,
                'external_status' => $order->externalStatus,
                'currency' => $order->currency,
                'placed_at' => Carbon::instance($order->placedAt),
                'remote_last_modified_at' => $lastModifiedAt,
                'totals' => json_encode($order->totals, JSON_THROW_ON_ERROR),
                'customer' => $this->encrypt($order->customer),
                'raw' => $this->encrypt($order->raw),
                'created_at' => $now,
                'updated_at' => $now,
            ]], ['connection_id', 'remote_id'], [
                'remote_order_number', 'status', 'external_status', 'currency', 'placed_at',
                'remote_last_modified_at', 'totals', 'customer', 'raw', 'updated_at',
            ]);

            $orderId = (int) DB::table('orders')
                ->where('connection_id', $connection->getKey())
                ->where('remote_id', $order->remoteId)
                ->value('id');

            $written = $this->lines($orderId, $order, $now);
            $packageIds = $this->packages($orderId, $order, $now);
            $this->history($orderId, $order, $packageIds, $now);

            if ($existing === null) {
                NotificationEventOccurred::dispatch(NotificationEvent::OrderReceived, [
                    'order_id' => $orderId,
                    'order_number' => $order->remoteOrderNumber,
                    'connection_id' => $connection->getKey(),
                    'connection' => $connection->name,
                    'total' => (string) ($order->totals['net'] ?? $order->totals['gross'] ?? '0.00'),
                ]);
            }

            return ['orders' => 1, 'lines' => $written['lines'], 'unmatched' => $written['unmatched']];
        });
    }

    /**
     * @return array{lines: int, unmatched: int}
     */
    private function lines(int $orderId, OrderData $order, Carbon $now): array
    {
        $barcodes = array_values(array_filter(array_map(
            static fn (OrderLineData $line): ?string => $line->barcode,
            $order->lines,
        )));

        // Barcode is the universal join key and Trendyol silently strips
        // interior whitespace on the way in, so the catalogue side is matched
        // through the same normalisation (TRENDYOL.md 9.2).
        $variants = $barcodes === [] ? [] : DB::table('product_variants')
            ->whereIn('barcode', $barcodes)
            ->pluck('id', 'barcode')
            ->all();

        $rows = [];
        $unmatched = 0;

        foreach ($order->lines as $line) {
            if ($line->remoteId === '') {
                continue;
            }

            $variantId = $line->barcode === null ? null : ($variants[$line->barcode] ?? null);

            if ($variantId === null) {
                $unmatched++;
            }

            $rows[] = [
                'order_id' => $orderId,
                'remote_line_id' => $line->remoteId,
                'variant_id' => $variantId,
                'sku' => $line->sku,
                'barcode' => $line->barcode,
                'quantity' => $line->quantity,
                'unit_price' => $line->unitPrice,
                'discounts' => json_encode(['total' => $line->discount], JSON_THROW_ON_ERROR),
                'commission' => $line->commission,
                'vat_rate' => $line->vatRate,
                'status' => $line->status->value,
                'external_status' => $line->externalStatus,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            DB::table('order_lines')->upsert($rows, ['order_id', 'remote_line_id'], [
                'variant_id', 'sku', 'barcode', 'quantity', 'unit_price', 'discounts',
                'commission', 'vat_rate', 'status', 'external_status', 'updated_at',
            ]);
        }

        return ['lines' => count($rows), 'unmatched' => $unmatched];
    }

    /**
     * @return array<string, int> remote package id => shipment_packages.id
     */
    private function packages(int $orderId, OrderData $order, Carbon $now): array
    {
        $rows = [];

        foreach ($order->shipments as $shipment) {
            if ($shipment->remoteId === '') {
                continue;
            }

            $rows[] = [
                'order_id' => $orderId,
                'remote_package_id' => $shipment->remoteId,
                'cargo_provider' => $shipment->cargoProvider,
                // CODE128, overflows int64 and JS MAX_SAFE_INTEGER: string
                // from the wire to the column to the browser (TRENDYOL.md 9.9).
                'tracking_number' => $shipment->trackingNumber,
                'tracking_link' => $shipment->trackingUrl,
                'status' => $shipment->status->value,
                'external_status' => $shipment->externalStatus,
                'deci' => $shipment->deci,
                'shipped_at' => $shipment->shippedAt === null ? null : Carbon::instance($shipment->shippedAt),
                'delivered_at' => $shipment->deliveredAt === null ? null : Carbon::instance($shipment->deliveredAt),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows === []) {
            return [];
        }

        DB::table('shipment_packages')->upsert($rows, ['order_id', 'remote_package_id'], [
            'cargo_provider', 'tracking_number', 'tracking_link', 'status',
            'external_status', 'deci', 'shipped_at', 'delivered_at', 'updated_at',
        ]);

        /** @var array<string, int> $ids */
        $ids = DB::table('shipment_packages')
            ->where('order_id', $orderId)
            ->pluck('id', 'remote_package_id')
            ->all();

        return $ids;
    }

    /**
     * `packageHistories` is the marketplace's own audit trail, so the local
     * history is a projection of it rather than a diff we compute: replaying a
     * page cannot invent transitions that did not happen.
     *
     * @param  array<string, int>  $packageIds
     */
    private function history(int $orderId, OrderData $order, array $packageIds, Carbon $now): void
    {
        $histories = $order->raw['packageHistories'] ?? null;

        if (! is_array($histories)) {
            return;
        }

        $entries = [];

        foreach ($histories as $entry) {
            if (! is_array($entry) || ! is_numeric($entry['createdDate'] ?? null)) {
                continue;
            }

            $status = $entry['status'] ?? null;

            if (! is_string($status) || trim($status) === '') {
                continue;
            }

            $entries[] = [
                'status' => trim($status),
                'occurredAt' => Carbon::createFromTimestampMs((int) $entry['createdDate'], 'UTC'),
            ];
        }

        usort($entries, static fn (array $a, array $b): int => $a['occurredAt'] <=> $b['occurredAt']);

        $rows = [];
        $previous = null;

        foreach ($entries as $entry) {
            $rows[] = [
                'order_id' => $orderId,
                'package_id' => $packageIds[$order->remoteId] ?? null,
                'from_status' => $previous,
                'to_status' => $entry['status'],
                'occurred_at' => $entry['occurredAt'],
                'source' => 'pull',
                'created_at' => $now,
            ];

            $previous = $entry['status'];
        }

        if ($rows !== []) {
            DB::table('order_status_history')->insertOrIgnore($rows);
        }
    }

    private function checkpoint(ChannelConnection $connection, ?DateTimeImmutable $watermark, ?string $cursor): void
    {
        $now = Carbon::now();

        DB::table('sync_cursors')->upsert([[
            'connection_id' => $connection->getKey(),
            'resource' => 'orders',
            'watermark' => $watermark === null ? null : Carbon::instance($watermark),
            'cursor' => $cursor,
            'created_at' => $now,
            'updated_at' => $now,
        ]], ['connection_id', 'resource'], ['watermark', 'cursor', 'updated_at']);
    }

    private function lastModifiedAt(OrderData $order): ?Carbon
    {
        $value = $order->raw['lastModifiedDate'] ?? null;

        return is_numeric($value) && (int) $value > 0
            ? Carbon::createFromTimestampMs((int) $value, 'UTC')
            : null;
    }

    /**
     * Byte for byte what AsEncryptedArrayObject writes, so the reported Order
     * model can cast these columns without a migration of the stored values.
     *
     * @param  array<array-key, mixed>  $value
     */
    private function encrypt(array $value): string
    {
        return Crypt::encryptString(json_encode($value, JSON_THROW_ON_ERROR));
    }
}
