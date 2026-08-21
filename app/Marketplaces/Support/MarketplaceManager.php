<?php

namespace App\Marketplaces\Support;

use App\Marketplaces\Contracts\MarketplaceDriver;
use App\Marketplaces\Support\Exceptions\MarketplaceException;
use BackedEnum;
use Illuminate\Support\Manager;

/**
 * Resolves marketplace drivers from config/marketplaces.php.
 */
final class MarketplaceManager extends Manager
{
    /**
     * There is no default marketplace: a caller always names the one it means.
     */
    public function getDefaultDriver(): ?string
    {
        return null;
    }

    /**
     * @param  BackedEnum|string|null  $driver
     *
     * @throws MarketplaceException
     */
    public function driver($driver = null): MarketplaceDriver
    {
        $marketplace = $driver instanceof BackedEnum ? $driver->value : $driver;

        if (! is_string($marketplace) || $marketplace === '') {
            throw MarketplaceException::unknownDriver(get_debug_type($driver));
        }

        $instance = parent::driver($marketplace);

        if (! $instance instanceof MarketplaceDriver) {
            throw MarketplaceException::invalidDriver($marketplace, get_debug_type($instance));
        }

        return $instance;
    }

    /**
     * @param  string  $driver
     *
     * @throws MarketplaceException
     */
    protected function createDriver($driver): mixed
    {
        if (isset($this->customCreators[$driver])) {
            return $this->callCustomCreator($driver);
        }

        $class = $this->config->get("marketplaces.drivers.{$driver}");

        if (! is_string($class) || ! class_exists($class)) {
            throw MarketplaceException::unknownDriver($driver);
        }

        return $this->container->make($class);
    }
}
