<?php

use App\Marketplaces\Support\Exceptions\MarketplaceException;
use App\Marketplaces\Trendyol\TrendyolRateLimiter;
use Illuminate\Support\Sleep;

beforeEach(function () {
    // Sleep has to move Carbon too: the buckets live in the cache and their
    // windows expire against the clock, not against wall time.
    Sleep::fake(syncWithCarbon: true);
});

it('spends the endpoint budget before it waits', function () {
    config()->set('marketplaces.trendyol.rate_limits.endpoint', ['limit' => 3, 'seconds' => 10]);

    $limiter = app(TrendyolRateLimiter::class);

    foreach (range(1, 3) as $ignored) {
        $limiter->acquire('4321', 'getBrands', '50k');
    }

    Sleep::assertNeverSlept();

    $limiter->acquire('4321', 'getBrands', '50k');

    Sleep::assertSleptTimes(1);
});

it('sizes the minute quota from config and the seller listing tier', function () {
    config()->set('marketplaces.trendyol.rate_limits.endpoint', ['limit' => 1000, 'seconds' => 10]);
    config()->set('marketplaces.trendyol.rate_limits.headroom', 0.5);
    config()->set('marketplaces.trendyol.rate_limits.groups.product_read.50k', 4);

    $limiter = app(TrendyolRateLimiter::class);

    // 4 published per minute at 50% headroom leaves 2, shared by every endpoint
    // in the group - the third call has to wait for the minute window.
    $limiter->acquire('4321', 'getBrands', '50k');
    $limiter->acquire('4321', 'getCategoryTree', '50k');

    Sleep::assertNeverSlept();

    $limiter->acquire('4321', 'getBrandsByName', '50k');

    Sleep::assertSleptTimes(1);
});

it('gives a bigger tier a bigger budget', function () {
    config()->set('marketplaces.trendyol.rate_limits.headroom', 1.0);
    config()->set('marketplaces.trendyol.rate_limits.groups.product_read', ['50k' => 1, '150k' => 5]);

    $limiter = app(TrendyolRateLimiter::class);

    $limiter->acquire('4321', 'getBrands', '150k');
    $limiter->acquire('4321', 'getBrands', '150k');

    Sleep::assertNeverSlept();
});

it('keeps one seller from eating another seller budget', function () {
    config()->set('marketplaces.trendyol.rate_limits.endpoint', ['limit' => 1, 'seconds' => 10]);

    $limiter = app(TrendyolRateLimiter::class);

    $limiter->acquire('4321', 'getBrands', '50k');
    $limiter->acquire('9999', 'getBrands', '50k');

    Sleep::assertNeverSlept();
});

it('refuses an endpoint that names no service group', function () {
    app(TrendyolRateLimiter::class)->acquire('4321', 'createProducts', '50k');
})->throws(MarketplaceException::class, 'has no service group in config/marketplaces.php');

it('refuses a listing tier the group has no limit for', function () {
    app(TrendyolRateLimiter::class)->acquire('4321', 'getBrands', '900k');
})->throws(MarketplaceException::class, 'no limit for listing tier [900k]');

it('gives up instead of waiting forever', function () {
    // No Carbon sync here: the window deliberately never elapses.
    Sleep::fake();
    config()->set('marketplaces.trendyol.rate_limits.endpoint', ['limit' => 1, 'seconds' => 3600]);
    config()->set('marketplaces.trendyol.rate_limits.max_waits', 2);

    $limiter = app(TrendyolRateLimiter::class);
    $limiter->acquire('4321', 'getBrands', '50k');

    expect(fn () => $limiter->acquire('4321', 'getBrands', '50k'))
        ->toThrow(MarketplaceException::class, 'did not free up after 2 waits');
});
