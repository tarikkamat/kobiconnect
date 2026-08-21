<?php

namespace App\Marketplaces\Trendyol\Enums;

use App\Marketplaces\Data\Enums\CanonicalOrderStatus;

/**
 * Trendyol shipment package statuses.
 *
 * These twelve values are the **query filter** set of TRENDYOL.md 5.1, not the
 * response set of 5.2. That distinction is the whole reason this enum exists:
 * the published OpenAPI `ShipmentPackage.status` enum defines only eight values
 * (`Created, Picking, Invoiced, Shipped, Cancelled, Delivered, UnDelivered,
 * Returned`) and omits `Awaiting`, `AtCollectionPoint`, `UnSupplied` and
 * `UnPacked` - all four of which are accepted filters and do come back on real
 * packages. Deriving one shared type from the response schema loses them, so
 * this type is derived from the filter set and is a superset of both.
 *
 * What is deliberately NOT shared is the *parameter* semantics:
 *   - `getShipmentPackages` takes `status`, a single package level value
 *   - `getShipmentPackagesStream` takes `packageItemStatuses`, a comma
 *     separated list of *line* level values (TRENDYOL.md 4.4.2, 5.3)
 * The two are different filters over different objects even though they draw
 * from the same vocabulary, which is why TrendyolDriver exposes them through
 * separate arguments and never lets one fall through to the other.
 *
 * `Repack` appears in one prose sentence of the getShipmentPackages guide and
 * nowhere else - not in the OpenAPI enum, not in the status description table,
 * not in the webhook list. It is treated as stale documentation and is NOT a
 * case here (TRENDYOL.md 5.1, unverified). tryFromRemote() therefore returns
 * null for it rather than dropping the package: the caller keeps the raw string
 * and routes the row to review.
 */
enum TrendyolPackageStatus: string
{
    /**
     * Payment approval is pending. Stock operations only: Trendyol accepts no
     * responsibility for a package shipped in this state, and warns the status
     * may stop returning data altogether (TRENDYOL.md 4.4.1).
     */
    case Awaiting = 'Awaiting';

    case Created = 'Created';

    case Picking = 'Picking';

    case Invoiced = 'Invoiced';

    case Shipped = 'Shipped';

    case Cancelled = 'Cancelled';

    case Delivered = 'Delivered';

    case UnDelivered = 'UnDelivered';

    case Returned = 'Returned';

    case AtCollectionPoint = 'AtCollectionPoint';

    case UnPacked = 'UnPacked';

    case UnSupplied = 'UnSupplied';

    public function toCanonical(): CanonicalOrderStatus
    {
        return match ($this) {
            self::Awaiting => CanonicalOrderStatus::PendingPayment,
            self::Created => CanonicalOrderStatus::Created,
            self::Picking => CanonicalOrderStatus::Picking,
            self::Invoiced => CanonicalOrderStatus::Invoiced,
            self::Shipped => CanonicalOrderStatus::Shipped,
            self::Cancelled => CanonicalOrderStatus::Cancelled,
            self::Delivered => CanonicalOrderStatus::Delivered,
            self::UnDelivered => CanonicalOrderStatus::Undelivered,
            self::Returned => CanonicalOrderStatus::Returned,
            self::AtCollectionPoint => CanonicalOrderStatus::AtCollectionPoint,
            self::UnPacked => CanonicalOrderStatus::Unpacked,
            self::UnSupplied => CanonicalOrderStatus::Unsupplied,
        };
    }

    /**
     * Parse a status off a payload. Unknown values return null and are never
     * folded into a default case (TRENDYOL.md 5): the caller stores the raw
     * string and flags the row, because a wrong guess here is a package the
     * warehouse ships when it should not have.
     *
     * The case insensitive second pass is defensive only. Trendyol's own
     * casing (`UnDelivered`, `AtCollectionPoint`) is inconsistent enough
     * across their documentation that an exact match alone is a bet.
     */
    public static function tryFromRemote(mixed $value): ?self
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $exact = self::tryFrom($value);

        if ($exact !== null) {
            return $exact;
        }

        foreach (self::cases() as $case) {
            if (strcasecmp($case->value, $value) === 0) {
                return $case;
            }
        }

        return null;
    }
}
