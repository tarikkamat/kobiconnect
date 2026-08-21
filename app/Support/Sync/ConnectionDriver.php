<?php

declare(strict_types=1);

namespace App\Support\Sync;

use App\Marketplaces\Contracts\MarketplaceDriver;
use App\Marketplaces\Support\MarketplaceManager;
use App\Models\ChannelConnection;

/**
 * Resolves the driver of a channel connection, bound to that connection's
 * credentials when the driver asks for them.
 *
 * This is the engine's only contact with the marketplace registry: everything
 * downstream talks to capability contracts, never to a marketplace name.
 */
final class ConnectionDriver
{
    public function __construct(private readonly MarketplaceManager $manager) {}

    public function for(ChannelConnection $connection): MarketplaceDriver
    {
        $driver = $this->manager->driver($connection->marketplace);

        if ($driver instanceof BindsCredentials) {
            return $driver->withCredentials($connection->credentials->toArray());
        }

        return $driver;
    }
}
