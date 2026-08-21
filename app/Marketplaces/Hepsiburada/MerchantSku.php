<?php

declare(strict_types=1);

namespace App\Marketplaces\Hepsiburada;

/**
 * The single most dangerous line of this integration (HEPSIBURADA.md §9.2).
 *
 * Hepsiburada SILENTLY uppercases `merchantSku` on the way in and forbids
 * spaces: what you send as `abc-1` is stored as `ABC-1`. Every later lookup
 * against the unnormalised value misses, the product looks absent, and a
 * duplicate gets created - the most common data accident on this platform.
 *
 * So normalisation happens at the boundary, once, and the normalised value is
 * what travels: it is the canonical `reference`, the value written to
 * `attributes.merchantSku`, and the key of `PushResult::itemResults`. Those
 * three being the same string is what makes the asynchronous poll result
 * correlatable at all (§6.2, §10.3 invariants 1 and 2).
 */
final class MerchantSku
{
    /**
     * Uppercase, no whitespace.
     *
     * `mb_strtoupper` is deliberate: PHP's `strtoupper` is byte based and
     * leaves every multibyte character untouched, so a Turkish SKU would be
     * "normalised" into something Hepsiburada never stores.
     *
     * ⚠️ Hepsiburada's own casefolding of Turkish characters is undocumented
     * (`ı` -> `I` or `İ`? - Ek A #3, P0). `mb_strtoupper` gives `ı` -> `I`, a
     * Turkish locale would give `i` -> `İ`; the three answers differ. Until it
     * is measured, references should be generated from ASCII only - a rule that
     * belongs where ProductData is built, not here, because throwing at push
     * time would break a seller over an unverified risk.
     */
    public static function normalise(string $value): string
    {
        return mb_strtoupper((string) preg_replace('/\s+/u', '', $value), 'UTF-8');
    }
}
