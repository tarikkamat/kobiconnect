<?php

use App\Marketplaces\Support\Exceptions\MarketplaceException;
use App\Marketplaces\Support\MarketplaceManager;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Tests\Unit\Marketplaces\Fixtures\FakeOrderDriver;
use Tests\Unit\Marketplaces\Fixtures\NotADriver;

/**
 * @param  array<string, class-string>  $drivers
 */
function marketplaceManager(array $drivers = []): MarketplaceManager
{
    $container = new Container;
    $container->instance('config', new Repository(['marketplaces' => ['drivers' => $drivers]]));

    return new MarketplaceManager($container);
}

it('resolves a registered driver', function () {
    $driver = marketplaceManager(['fake' => FakeOrderDriver::class])->driver('fake');

    expect($driver)->toBeInstanceOf(FakeOrderDriver::class)
        ->and($driver->identifier())->toBe('fake');
});

it('caches the resolved driver', function () {
    $manager = marketplaceManager(['fake' => FakeOrderDriver::class]);

    expect($manager->driver('fake'))->toBe($manager->driver('fake'));
});

it('fails on an unregistered marketplace', function () {
    marketplaceManager()->driver('hepsiburada');
})->throws(MarketplaceException::class, 'Marketplace [hepsiburada] is not registered in config/marketplaces.php.');

it('fails when the configured class does not exist', function () {
    marketplaceManager(['ghost' => 'App\Marketplaces\Ghost\GhostDriver'])->driver('ghost');
})->throws(MarketplaceException::class, 'Marketplace [ghost] is not registered in config/marketplaces.php.');

it('fails when the configured class is not a marketplace driver', function () {
    marketplaceManager(['broken' => NotADriver::class])->driver('broken');
})->throws(MarketplaceException::class, 'does not implement MarketplaceDriver');

it('fails when no marketplace is named', function () {
    marketplaceManager(['fake' => FakeOrderDriver::class])->driver();
})->throws(MarketplaceException::class, 'Marketplace [null] is not registered');
