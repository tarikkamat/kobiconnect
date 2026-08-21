<?php

declare(strict_types=1);

use App\Marketplaces\Data\MappingContext;
use App\Marketplaces\Data\PriceData;
use App\Marketplaces\Data\StockData;
use App\Marketplaces\Support\Capability;
use App\Marketplaces\Support\Exceptions\MarketplaceException;
use App\Marketplaces\Trendyol\TrendyolCredentials;
use App\Marketplaces\Trendyol\TrendyolDriver;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\Fixtures\Trendyol\Fixture;

beforeEach(function (): void {
    $this->grantActiveLicense();

    // config/marketplaces.php is not this wave's file; the three endpoints
    // added here are reported for it. An unmapped endpoint throws by design.
    config()->set('marketplaces.trendyol.rate_limits.endpoints.updatePriceAndInventory', 'inventory_price_write');
    config()->set('marketplaces.trendyol.rate_limits.endpoints.getBatchRequestResult', 'product_read');
    config()->set('marketplaces.trendyol.rate_limits.endpoints.filterApprovedProductsInventoryAndPrice', 'product_read');

    Sleep::fake();
});

function pushDriver(): TrendyolDriver
{
    return app(TrendyolDriver::class)->for(new TrendyolCredentials(
        sellerId: '4321',
        apiKey: 'key',
        apiSecret: 'secret',
    ));
}

function pushContext(): MappingContext
{
    return new MappingContext(externalSellerId: '4321');
}

it('claims the inventory and price capabilities now that it implements them', function (): void {
    $claimed = app(TrendyolDriver::class)->capabilities();

    expect($claimed)->toContain(Capability::InventorySync)
        ->and($claimed)->toContain(Capability::PriceSync)
        // product_sync stays out until the Ek A #1 attribute contradiction is
        // settled on stage - reading a batch result does not require it.
        ->and($claimed)->not->toContain(Capability::ProductSync);
});

it('posts only the quantity to the inventory service and returns the batch id', function (): void {
    Http::fake(['*price-and-inventory' => Http::response(Fixture::json('price-and-inventory-accepted'))]);

    $result = pushDriver()->pushStock([
        new StockData(reference: '8680000000', quantity: 100, sku: 'SKU-1', barcode: '8680000000'),
    ], pushContext());

    expect($result->accepted)->toBeTrue()
        ->and($result->remoteBatchId)->toBe('fa75dfd5-6ce6-4730-a09e-97563500000-1529854840')
        // Acceptance is not success: the item results are still outstanding.
        ->and($result->isPending())->toBeTrue();

    Http::assertSent(function (Request $request): bool {
        // The write goes to /inventory/... while its result is read from
        // /product/... - the most common path mistake (TRENDYOL.md 4.2.6).
        return str_contains($request->url(), '/inventory/sellers/4321/products/price-and-inventory')
            && $request->method() === 'POST'
            && $request['items'] === [['barcode' => '8680000000', 'quantity' => 100]]
            && $request->header('User-Agent') === ['4321 - SelfIntegration'];
    });
});

it('caps a quantity at the documented product maximum', function (): void {
    Http::fake(['*' => Http::response(Fixture::json('price-and-inventory-accepted'))]);

    pushDriver()->pushStock([
        new StockData(reference: '8680000000', quantity: 999_999, barcode: '8680000000'),
    ], pushContext());

    Http::assertSent(fn (Request $request): bool => $request['items'][0]['quantity'] === 20000);
});

it('posts only the two prices for a price push', function (): void {
    Http::fake(['*' => Http::response(Fixture::json('price-and-inventory-accepted'))]);

    pushDriver()->pushPrices([
        new PriceData(
            reference: '8680000000',
            listPrice: '113.85',
            salePrice: '112.85',
            barcode: '8680000000',
        ),
    ], pushContext());

    // Sending the quantity as well would only widen the 15 minute collision
    // surface: the three fields are independent (TRENDYOL.md 9.5).
    Http::assertSent(fn (Request $request): bool => $request['items'] === [[
        'barcode' => '8680000000',
        'salePrice' => 112.85,
        'listPrice' => 113.85,
    ]]);
});

it('refuses a batch larger than the marketplace accepts', function (): void {
    Http::fake(['*' => Http::response(Fixture::json('price-and-inventory-accepted'))]);

    $stock = array_map(
        static fn (int $i): StockData => new StockData(reference: "barcode-{$i}", quantity: 1, barcode: "barcode-{$i}"),
        range(1, 1001),
    );

    // Over the ceiling Trendyol would accept and truncate, which is the one
    // failure the ledger could never observe.
    expect(fn () => pushDriver()->pushStock($stock, pushContext()))
        ->toThrow(MarketplaceException::class);

    Http::assertNothingSent();
});

it('judges a batch item by item and never by the envelope', function (): void {
    Http::fake(['*batch-requests*' => Http::response(Fixture::json('batch-result-inventory'))]);

    $result = pushDriver()->batchResult('fa75dfd5-6ce6-4730-a09e-97563500000-1529854840');

    // failedItemCount is 1 of 2: a normal partial success, not a failed push.
    expect($result->accepted)->toBeTrue()
        ->and($result->isPending())->toBeFalse()
        ->and($result->itemResults['8680000000']['accepted'])->toBeTrue()
        ->and($result->itemResults['8680000001']['accepted'])->toBeFalse()
        ->and($result->itemResults['8680000001']['message'])->toBe('Barkod bulunamadi')
        // Loose on purpose: PHP casts a numeric string array key to int, and
        // most barcodes are numeric. Lookups still work because the cast is
        // symmetric, but the key that comes back out is an int.
        ->and($result->failedReferences())->toEqual(['8680000001']);

    Http::assertSent(fn (Request $request): bool => str_contains(
        $request->url(),
        '/product/sellers/4321/products/batch-requests/fa75dfd5-6ce6-4730-a09e-97563500000-1529854840',
    ));
});

it('reads an empty item list as still running rather than as success', function (): void {
    Http::fake(['*batch-requests*' => Http::response(Fixture::json('batch-result-running'))]);

    // An inventory batch never carries a top level status, so "done" can only
    // be read from the items themselves (TRENDYOL.md 6.4).
    expect(pushDriver()->batchResult('batch-1')->isPending())->toBeTrue();
});

it('reads back approved stock and price as reconciliation ground truth', function (): void {
    Http::fake(['*inventory-and-price*' => Http::response(Fixture::json('approved-inventory-and-price'))]);

    $stock = pushDriver()->pullStock();
    $prices = pushDriver()->pullPrices();

    expect($stock->items)->toHaveCount(1)
        ->and($stock->items[0]->barcode)->toBe('60506560')
        ->and($stock->items[0]->quantity)->toBe(50)
        ->and($stock->items[0]->sku)->toBe('056565964')
        ->and($stock->hasMore)->toBeFalse()
        ->and($prices->items[0]->listPrice)->toBe('749.99')
        ->and($prices->items[0]->salePrice)->toBe('699.99');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'orderByDirection=asc'));
});
