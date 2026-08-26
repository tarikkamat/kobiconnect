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
     * Kayitli olmayan / kurulamayan pazaryeri icin `null`.
     *
     * Cagiran taraflar bunu `try { driver() } catch { null }` olarak tekrar
     * tekrar yaziyordu; yakalama tek yerde ve yalnizca MarketplaceException'i
     * yutuyor — surucunun kendi hatasi yukari cikmaya devam eder.
     */
    public function tryDriver(?string $marketplace): ?MarketplaceDriver
    {
        try {
            return $this->driver($marketplace);
        } catch (MarketplaceException) {
            return null;
        }
    }

    /**
     * Yetenegi destekleyen pazaryeri anahtarlari. Yetenek surucunun
     * implement ettigi arayuzden turer, yani liste config'e eklenen her
     * pazaryeriyle kendiliginden buyur.
     *
     * @return list<string>
     */
    public function supporting(Capability $capability): array
    {
        $drivers = $this->config->get('marketplaces.drivers');

        return array_values(array_filter(
            array_keys(is_array($drivers) ? $drivers : []),
            fn (string $marketplace): bool => ($driver = $this->tryDriver($marketplace)) !== null
                && $capability->driverSupports($driver),
        ));
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
