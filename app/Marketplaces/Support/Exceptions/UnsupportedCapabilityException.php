<?php

namespace App\Marketplaces\Support\Exceptions;

use App\Marketplaces\Contracts\MarketplaceDriver;
use App\Marketplaces\Support\Capability;

class UnsupportedCapabilityException extends MarketplaceException
{
    public static function for(Capability $capability, MarketplaceDriver $driver): self
    {
        return new self("Marketplace [{$driver->identifier()}] does not support capability [{$capability->value}].");
    }
}
