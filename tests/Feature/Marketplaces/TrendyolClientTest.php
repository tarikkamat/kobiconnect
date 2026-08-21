<?php

use App\Marketplaces\Support\Exceptions\MarketplaceException;
use App\Marketplaces\Trendyol\Exceptions\TrendyolApiException;
use App\Marketplaces\Trendyol\TrendyolClient;
use App\Marketplaces\Trendyol\TrendyolCredentials;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\Fixtures\Trendyol\Fixture;

function trendyolTestClient(bool $stage = false): TrendyolClient
{
    return app(TrendyolClient::class)->as(new TrendyolCredentials(
        sellerId: '4321',
        apiKey: 'key',
        apiSecret: 'secret',
        stage: $stage,
    ));
}

it('signs every request with basic auth and the user agent Trendyol requires', function () {
    Http::fake(['*' => Http::response(Fixture::json('brands'))]);

    $payload = trendyolTestClient()->get('getBrands', 'product/brands', ['page' => 0, 'size' => 1000]);

    expect($payload)->toBe(Fixture::json('brands'));

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://apigw.trendyol.com/integration/product/brands?page=0&size=1000'
        && $request->hasHeader('Authorization', 'Basic '.base64_encode('key:secret'))
        && $request->hasHeader('User-Agent', '4321 - SelfIntegration'));
});

it('talks to the stage host for a stage connection', function () {
    Http::fake(['*' => Http::response(Fixture::json('brands'))]);

    trendyolTestClient(stage: true)->get('getBrands', 'product/brands');

    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://stageapigw.trendyol.com/integration/'));
});

it('carries the request id from the context onto the outgoing call', function () {
    Http::fake(['*' => Http::response(Fixture::json('brands'))]);
    Context::add('request_id', '01K5ZQ7YAAAAAAAAAAAAAAAAAA');

    trendyolTestClient()->get('getBrands', 'product/brands');

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('X-Request-Id', '01K5ZQ7YAAAAAAAAAAAAAAAAAA'));
});

it('refuses to send anything without credentials', function () {
    app(TrendyolClient::class)->get('getBrands', 'product/brands');
})->throws(MarketplaceException::class, 'No Trendyol credentials bound');

it('parses all three error envelopes', function (string $fixture, int $status, string $key) {
    Http::fake(['*' => Http::response(Fixture::json($fixture), $status)]);
    Sleep::fake();

    try {
        trendyolTestClient()->get('getBrands', 'product/brands');
    } catch (TrendyolApiException $exception) {
        expect($exception->status)->toBe($status)
            ->and($exception->endpoint)->toBe('getBrands')
            ->and($exception->errors[0]['key'])->toBe($key)
            ->and($exception->getMessage())->toContain($key);

        return;
    }

    $this->fail('The client swallowed a failed Trendyol response.');
})->with([
    'documented envelope' => ['error-400', 400, 'invalid.barcode'],
    'authentication envelope' => ['error-401', 401, 'ClientApiAuthenticationException'],
    'order and throttling envelope' => ['error-429', 429, 'too.many.requests'],
]);

it('backs off and retries a throttled request', function () {
    Sleep::fake();
    Http::fakeSequence()
        ->push(Fixture::json('error-429'), 429)
        ->push(Fixture::json('brands'), 200);

    $payload = trendyolTestClient()->get('getBrands', 'product/brands');

    expect($payload)->toBe(Fixture::json('brands'));
    Http::assertSentCount(2);
    Sleep::assertSleptTimes(1);
});

it('gives up after the configured number of attempts', function () {
    Sleep::fake();
    Http::fake(['*' => Http::response(Fixture::json('error-429'), 429)]);

    expect(fn () => trendyolTestClient()->get('getBrands', 'product/brands'))
        ->toThrow(TrendyolApiException::class);

    Http::assertSentCount(3);
    Sleep::assertSleptTimes(2);
});

it('never retries a permanent failure', function () {
    Sleep::fake();
    Http::fake(['*' => Http::response(Fixture::json('error-400'), 400)]);

    expect(fn () => trendyolTestClient()->get('getBrands', 'product/brands'))
        ->toThrow(TrendyolApiException::class);

    Http::assertSentCount(1);
    Sleep::assertNeverSlept();
});

it('treats a stage 503 as an ip allow list problem and a production 503 as a blip', function (bool $stage, int $sent) {
    Sleep::fake();
    Http::fake(['*' => Http::response('', 503)]);

    expect(fn () => trendyolTestClient(stage: $stage)->get('getBrands', 'product/brands'))
        ->toThrow(TrendyolApiException::class);

    Http::assertSentCount($sent);
})->with([
    'stage' => [true, 1],
    'production' => [false, 3],
]);
