<?php

declare(strict_types=1);

use App\Models\ChannelOperation;
use App\Support\Sync\MarketplaceWindow;
use Tests\TestCase;

// Cache and config only - no database, so this stays a unit test.
uses(TestCase::class);

function stockOperation(string $barcode = '8690000000001', string $hash = 'hash-a'): ChannelOperation
{
    return new ChannelOperation([
        'connection_id' => 7,
        'entity_type' => 'App\Models\ProductVariant',
        'entity_id' => 3,
        'operation' => 'stock_update',
        'desired_state' => ['reference' => 'variant-3', 'barcode' => $barcode, 'quantity' => 5],
        'payload_hash' => $hash,
    ]);
}

it('drops a repeat of the same values inside the window', function (): void {
    config(['marketplaces.fake.dedup_window_seconds' => 900]);

    $window = new MarketplaceWindow;
    $operation = stockOperation();

    expect($window->suppresses($operation, 'fake'))->toBeFalse();

    $window->remember($operation, 'fake');

    expect($window->suppresses($operation, 'fake'))->toBeTrue();
});

it('lets a changed value straight through', function (): void {
    config(['marketplaces.fake.dedup_window_seconds' => 900]);

    $window = new MarketplaceWindow;

    $window->remember(stockOperation(hash: 'hash-a'), 'fake');

    expect($window->suppresses(stockOperation(hash: 'hash-b'), 'fake'))->toBeFalse();
});

it('keys the window on the barcode the marketplace deduplicates on', function (): void {
    config(['marketplaces.fake.dedup_window_seconds' => 900]);

    $window = new MarketplaceWindow;

    $window->remember(stockOperation(barcode: '111'), 'fake');

    expect($window->suppresses(stockOperation(barcode: '222'), 'fake'))->toBeFalse();
});

it('is disabled for a marketplace that does not punish repeats', function (): void {
    config(['marketplaces.fake.dedup_window_seconds' => 0]);

    $window = new MarketplaceWindow;
    $operation = stockOperation();

    $window->remember($operation, 'fake');

    expect($window->suppresses($operation, 'fake'))->toBeFalse()
        ->and($window->seconds('fake'))->toBe(0);
});
