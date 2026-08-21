<?php

declare(strict_types=1);

namespace App\Marketplaces\Hepsiburada\Enums;

use App\Marketplaces\Data\Enums\CanonicalListingStatus;

/**
 * The catalog moderation lifecycle (HEPSIBURADA.md §5.1). This is NOT whether
 * the listing is on sale - that lives on a different host entirely (H9).
 *
 * 🔴 The naming inverts English intuition and the mapping is hard-coded from
 * the documentation table for exactly that reason:
 *   `MATCHED`     = "Satışa Hazır" - accepted into the catalog, DONE
 *   `PRE_MATCHED` = "Eşleşen"      - a match was PROPOSED and waits for us
 */
enum HepsiburadaProductStatus: string
{
    /** İncelenecek - the product entry team is looking at it. */
    case Waiting = 'WAITING';

    /** Ürün bilgileri eksik - our data is wrong; fix and resend. */
    case MissingInfo = 'MISSING_INFO';

    /** Eşleşen - Hepsiburada proposes a catalog match and waits for our verdict. */
    case PreMatched = 'PRE_MATCHED';

    /** Satışa Hazır - in the catalog. The catalog axis is finished. */
    case Matched = 'MATCHED';

    /** Ön Katalog Eşleşen - matched against the staged catalog. */
    case MatchedWithStaged = 'MATCHED_WITH_STAGED';

    /** Two meanings share this value: we rejected their proposal, or they rejected us. */
    case Rejected = 'REJECTED';

    /** Yaratıldı - our own catalog record was created. */
    case Created = 'CREATED';

    /**
     * Unknown values are never folded into a neighbour: `IN_EXTRENAL_PROGESS`
     * (upstream's own typo) and `BLOCKED` appear in SDK enums but in no portal
     * page (§5.1), and "Katalog Sürecinde" has no documented machine value at
     * all. A null here leaves ProductData.status null, which the update router
     * treats as "unknown, let the marketplace decide".
     */
    public static function tryFromRemote(?string $value): ?self
    {
        return $value === null ? null : self::tryFrom(mb_strtoupper(trim($value), 'UTF-8'));
    }

    /**
     * Whether `POST /products/import` still accepts an update for this product.
     *
     * §5.1: editability is derived from the status, there is no separate field.
     * A sync engine that pushes without checking loops forever against
     * `"Can not update product in matched or in catalog progress status"`.
     */
    public function allowsCatalogUpdate(): bool
    {
        return match ($this) {
            self::Waiting, self::MissingInfo, self::PreMatched => true,
            default => false,
        };
    }

    /**
     * The catalog axis, folded into the canonical listing status.
     *
     * `Matched`, `MatchedWithStaged` and `Created` all become `Locked`: on the
     * catalog axis - which is what ProductData.status describes - they mean
     * "accepted, and the record is no longer ours to edit", and
     * `CanonicalListingStatus::isEditable()` returning false is the behaviour
     * the sync engine must get right.
     *
     * ⚠️ Deliberate loss (§10 M19): whether such a product is actually SELLING
     * is a second, orthogonal axis carried by the listing host (`isSalable` +
     * `deactivationReasons[]`), and `channel_listings.remote_status` has room
     * for only one. The listing axis is read through pullPrices/pullStock.
     */
    public function toCanonical(): CanonicalListingStatus
    {
        return match ($this) {
            self::Waiting, self::MissingInfo => CanonicalListingStatus::PendingApproval,
            self::PreMatched => CanonicalListingStatus::AwaitingMatchDecision,
            self::Rejected => CanonicalListingStatus::Rejected,
            self::Matched, self::MatchedWithStaged, self::Created => CanonicalListingStatus::Locked,
        };
    }
}
