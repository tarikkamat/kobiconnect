<?php

use App\Marketplaces\Data\Enums\OperationType;
use App\Marketplaces\Support\Capability;
use App\Marketplaces\Support\Exceptions\UnsupportedCapabilityException;
use Tests\Unit\Marketplaces\Fixtures\FakeOrderDriver;

it('detects a capability from the contracts the driver implements', function () {
    $driver = new FakeOrderDriver;

    expect(Capability::OrderSync->driverSupports($driver))->toBeTrue()
        ->and(Capability::ProductSync->driverSupports($driver))->toBeFalse()
        ->and(Capability::Webhooks->driverSupports($driver))->toBeFalse();
});

it('lists every capability a driver implements', function () {
    expect(Capability::supportedBy(new FakeOrderDriver))->toBe([Capability::OrderSync]);
});

it('reports the same capabilities the driver declares', function () {
    $driver = new FakeOrderDriver;

    expect($driver->capabilities())->toBe(Capability::supportedBy($driver));
});

it('guards an unsupported capability', function () {
    Capability::Claims->ensureSupported(new FakeOrderDriver);
})->throws(UnsupportedCapabilityException::class, 'Marketplace [fake] does not support capability [claims].');

it('allows a supported capability', function () {
    Capability::OrderSync->ensureSupported(new FakeOrderDriver);
})->throwsNoExceptions();

it('maps every capability to a distinct contract', function () {
    $contracts = array_map(fn (Capability $capability): string => $capability->contract(), Capability::cases());

    expect($contracts)->toHaveCount(count(array_unique($contracts)));
});

it('routes every operation type to the capability that drains it', function () {
    expect(OperationType::StockUpdate->capability())->toBe(Capability::InventorySync)
        ->and(OperationType::ProductCreate->capability())->toBe(Capability::ProductSync)
        ->and(OperationType::TrackingNumber->capability())->toBe(Capability::ShipmentUpdates)
        ->and(OperationType::QuestionAnswer->capability())->toBe(Capability::Questions);
});
