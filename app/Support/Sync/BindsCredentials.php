<?php

declare(strict_types=1);

namespace App\Support\Sync;

use App\Marketplaces\Contracts\MarketplaceDriver;

/**
 * A driver whose API identity comes from the channel connection rather than
 * from the application configuration.
 *
 * The engine never names a marketplace, so it cannot call
 * `TrendyolDriver::for(TrendyolCredentials)` directly; it asks for this
 * capability the same way it asks for any other, with instanceof.
 *
 * ponytail: declared here instead of app/Marketplaces/Contracts because the
 * sync engine is its only consumer today. Move it next to the Supports*
 * contracts when a second driver implements it.
 */
interface BindsCredentials
{
    /**
     * @param  array<string, mixed>  $credentials  channel_connections.credentials
     */
    public function withCredentials(array $credentials): MarketplaceDriver;
}
