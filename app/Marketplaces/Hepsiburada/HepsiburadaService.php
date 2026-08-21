<?php

declare(strict_types=1);

namespace App\Marketplaces\Hepsiburada;

/**
 * Hepsiburada is not one API. Catalog, listing and order live on three
 * different hosts behind the same Basic credential pair (HEPSIBURADA.md H1,
 * measured), so a client cannot hold a single base url the way Trendyol's does.
 *
 * The service is also the rate limit bucket: the published budget is per egress
 * IP per host, never per seller (H11).
 */
enum HepsiburadaService: string
{
    /** mpop - categories, attributes, product writes, pre-match decisions. */
    case Catalog = 'catalog';

    /** listing-external - price, stock, salability. */
    case Listing = 'listing';

    /** oms-external - orders, packages, claims. */
    case Oms = 'oms';
}
