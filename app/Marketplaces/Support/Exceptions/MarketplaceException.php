<?php

namespace App\Marketplaces\Support\Exceptions;

use RuntimeException;

class MarketplaceException extends RuntimeException
{
    public static function unknownDriver(string $marketplace): self
    {
        return new self("Marketplace [{$marketplace}] is not registered in config/marketplaces.php.");
    }

    public static function invalidDriver(string $marketplace, string $class): self
    {
        return new self("Driver [{$class}] registered for marketplace [{$marketplace}] does not implement MarketplaceDriver.");
    }
}
