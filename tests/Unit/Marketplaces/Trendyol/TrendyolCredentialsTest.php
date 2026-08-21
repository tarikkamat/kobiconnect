<?php

use App\Marketplaces\Trendyol\TrendyolCredentials;

it('builds the user agent Trendyol demands', function () {
    $credentials = new TrendyolCredentials(sellerId: '4321', apiKey: 'key', apiSecret: 'secret');

    expect($credentials->userAgent())->toBe('4321 - SelfIntegration');
});

it('lets an integrator name override the self integration suffix', function () {
    $credentials = new TrendyolCredentials(
        sellerId: '1234',
        apiKey: 'key',
        apiSecret: 'secret',
        integrator: 'TrendyolSoft',
    );

    expect($credentials->userAgent())->toBe('1234 - TrendyolSoft');
});

it('reads the connection credentials array', function () {
    $credentials = TrendyolCredentials::fromArray([
        'seller_id' => '999',
        'api_key' => 'key',
        'api_secret' => 'secret',
        'stage' => true,
        'listing_tier' => '150k',
    ]);

    expect($credentials->stage)->toBeTrue()
        ->and($credentials->listingTier)->toBe('150k')
        ->and($credentials->integrator)->toBe('SelfIntegration');
});

it('rejects a non numeric seller id', function () {
    new TrendyolCredentials(sellerId: 'abc', apiKey: 'key', apiSecret: 'secret');
})->throws(InvalidArgumentException::class, 'sellerId must be a numeric string');

it('rejects an empty key pair', function () {
    new TrendyolCredentials(sellerId: '1', apiKey: '', apiSecret: 'secret');
})->throws(InvalidArgumentException::class, 'apiKey and apiSecret are required');

it('rejects an integrator name Trendyol would refuse', function (string $integrator) {
    new TrendyolCredentials(sellerId: '1', apiKey: 'key', apiSecret: 'secret', integrator: $integrator);
})->with([
    'not alphanumeric' => 'Kobi Connect',
    'over thirty characters' => 'KobiConnectIntegrationPlatform1',
])->throws(InvalidArgumentException::class, 'alphanumeric and at most 30 characters');
