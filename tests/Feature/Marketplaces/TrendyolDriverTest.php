<?php

use App\Marketplaces\Data\BrandData;
use App\Marketplaces\Support\Capability;
use App\Marketplaces\Support\MarketplaceManager;
use App\Marketplaces\Trendyol\TrendyolCredentials;
use App\Marketplaces\Trendyol\TrendyolDriver;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\Trendyol\Fixture;

function trendyolTestDriver(): TrendyolDriver
{
    return app(TrendyolDriver::class)->for(new TrendyolCredentials(
        sellerId: '4321',
        apiKey: 'key',
        apiSecret: 'secret',
    ));
}

it('is registered as a marketplace driver', function () {
    $driver = app(MarketplaceManager::class)->driver('trendyol');

    expect($driver)->toBeInstanceOf(TrendyolDriver::class)
        ->and($driver->identifier())->toBe('trendyol')
        ->and($driver->displayName())->toBe('Trendyol');
});

it('claims exactly the capabilities whose contracts it implements', function () {
    $driver = app(TrendyolDriver::class);
    $claimed = $driver->capabilities();

    // Beklenti sabit bir liste DEGIL, surucunun implement ettigi arayuzlerden
    // turetilir. Test edilen sey su: iddia edilen yetenek ile implement edilen
    // contract birbirini tutuyor mu (BACKEND-PLAN 6.2). Yeni bir yetenek
    // eklendiginde bu test kendiliginde dogru kalir; yalnizca capabilities()
    // elle bakim yapilan bir listeye donerse kirilir.
    foreach (Capability::cases() as $capability) {
        expect(in_array($capability, $claimed, true))
            ->toBe($driver instanceof ($capability->contract()), $capability->value);
    }

    expect($claimed)->not->toBeEmpty();
});

it('pages brands until a page comes back empty', function () {
    Http::fake([
        '*page=0*' => Http::response(Fixture::json('brands')),
        '*page=1*' => Http::response(Fixture::json('brands-empty')),
    ]);

    $driver = trendyolTestDriver();
    $first = $driver->brands();

    expect($first->items)->toEqual([new BrandData(remoteId: '10', name: 'TrendyolMilla')])
        ->and($first->hasMore)->toBeTrue()
        ->and($first->cursor)->toBe('1');

    $second = $driver->brands($first->cursor);

    expect($second->items)->toBe([])
        ->and($second->hasMore)->toBeFalse();
});

it('finds a brand only on an exact, case sensitive name', function () {
    Http::fake(['*' => Http::response(Fixture::json('brands-by-name'))]);

    $driver = trendyolTestDriver();

    expect($driver->findBrandByName('TRENDYOLMİLLA'))
        ->toEqual(new BrandData(remoteId: '40', name: 'TRENDYOLMİLLA'))
        ->and($driver->findBrandByName('Trendyolmilla'))->toBeNull();

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'product/brands/by-name?name='));
});

it('reads the category tree and marks the nodes that accept products', function () {
    Http::fake(['*' => Http::response(Fixture::json('category-tree'))]);

    $tree = trendyolTestDriver()->categoryTree();

    expect($tree)->toHaveCount(1)
        ->and($tree[0]->isLeaf)->toBeFalse()
        ->and($tree[0]->children[0]->isLeaf)->toBeTrue();

    // The storefront header is documented all lowercase on this endpoint.
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('storefrontcode', 'TR')
        && $request->hasHeader('Accept-Language', 'tr'));
});

it('pulls the value list of an attribute that refuses free text', function () {
    Http::fake([
        '*/attributes' => Http::response(Fixture::json('category-attributes')),
        '*/values*' => Http::response(Fixture::json('category-attribute-values')),
    ]);

    $attributes = trendyolTestDriver()->categoryAttributes('14609');

    expect($attributes)->toHaveCount(1)
        ->and($attributes[0]->remoteId)->toBe('293')
        ->and($attributes[0]->isRequired)->toBeTrue()
        ->and($attributes[0]->isVarianter)->toBeTrue()
        ->and($attributes[0]->values)->toHaveCount(1)
        ->and($attributes[0]->values[0]->value)->toBe('Tek Ebat')
        ->and($attributes[0]->values[0]->remoteId)->toBe('4872');

    Http::assertSent(fn (Request $request): bool => str_contains(
        $request->url(),
        'product/categories/14609/attributes/293/values',
    ));
});

it('skips the value fan out when the attribute accepts free text', function () {
    $payload = Fixture::json('category-attributes');
    $payload['categoryAttributes'][0]['allowCustom'] = true;

    Http::fake(['*' => Http::response($payload)]);

    $attributes = trendyolTestDriver()->categoryAttributes('14609');

    expect($attributes[0]->allowsCustomValue)->toBeTrue()
        ->and($attributes[0]->values)->toBe([]);

    Http::assertSentCount(1);
});

it('serves reference data from the cache instead of spending the budget again', function () {
    Http::fake(['*' => Http::response(Fixture::json('category-tree'))]);

    $driver = trendyolTestDriver();
    $driver->categoryTree();
    $driver->categoryTree();

    Http::assertSentCount(1);
});
