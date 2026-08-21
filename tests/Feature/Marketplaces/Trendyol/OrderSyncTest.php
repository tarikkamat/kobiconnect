<?php

declare(strict_types=1);

use App\Marketplaces\Data\Enums\CanonicalOrderStatus;
use App\Marketplaces\Support\Capability;
use App\Marketplaces\Support\Exceptions\MarketplaceException;
use App\Marketplaces\Trendyol\TrendyolCredentials;
use App\Marketplaces\Trendyol\TrendyolDriver;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\Trendyol\Fixture;

beforeEach(function (): void {
    // config/marketplaces.php bu is kumesinin DOKUNMA listesinde; siparis
    // endpoint'lerinin kalici kaydi raporda. Limitleyici kaydi olmayan bir
    // endpoint'te bilerek patlar, o yuzden test kendi butcesini tanimlar.
    config()->set('marketplaces.trendyol.rate_limits.groups.order_read', [
        '50k' => 30, '75k' => 40, '150k' => 50, '500k' => 100, 'unlimited' => 100,
    ]);
    config()->set('marketplaces.trendyol.rate_limits.endpoints.getShipmentPackages', 'order_read');
    config()->set('marketplaces.trendyol.rate_limits.endpoints.getShipmentPackagesStream', 'order_read');
});

function trendyolOrderDriver(): TrendyolDriver
{
    return app(TrendyolDriver::class)->for(new TrendyolCredentials(
        sellerId: '4321',
        apiKey: 'key',
        apiSecret: 'secret',
    ));
}

it('declares order sync as a capability derived from its contracts', function (): void {
    expect(app(TrendyolDriver::class)->capabilities())->toContain(Capability::OrderSync);
});

it('opens the stream without a cursor and continues with the opaque one alone', function (): void {
    Http::fake([
        '*nextCursor=*' => Http::response(Fixture::json('order-stream-page-2')),
        '*orders/stream*' => Http::response(Fixture::json('order-stream-page-1')),
    ]);

    $driver = trendyolOrderDriver();
    $first = $driver->pullOrders();

    expect($first->items)->toHaveCount(2)
        ->and($first->items[0]->remoteId)->toBe('1234567')
        ->and($first->items[1]->status)->toBe(CanonicalOrderStatus::PendingPayment)
        ->and($first->hasMore)->toBeTrue()
        ->and($first->cursor)->toBe('eyJsYXN0TW9kaWZpZWREYXRlIjoxNzYyODYxNTAwMDAwfQ==');

    $second = $driver->pullOrders(null, $first->cursor);

    expect($second->hasMore)->toBeFalse()
        ->and($second->cursor)->toBeNull()
        ->and($second->items)->toHaveCount(1);

    // Ilk istekte nextCursor GONDERILMEZ.
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'orders/stream?size=200')
        && ! str_contains($request->url(), 'nextCursor'));

    // Devam isteginde cursor birebir gider ve YANINDA FILTRE YOKTUR: ayni
    // cursor kullanilirken filtre degisirse Trendyol 400 doner.
    Http::assertSent(function (Request $request): bool {
        if (! str_contains($request->url(), 'nextCursor')) {
            return false;
        }

        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return $query === ['size' => '200', 'nextCursor' => 'eyJsYXN0TW9kaWZpZWREYXRlIjoxNzYyODYxNTAwMDAwfQ=='];
    });
});

it('reports the highest lastModifiedDate of the page as the watermark', function (): void {
    Http::fake(['*' => Http::response(Fixture::json('order-stream-page-1'))]);

    // Akis lastModifiedDate DESC sirali: en yenisi ilk sayfada gelir, bu yuzden
    // imleci ancak akis tukendiginde islemek cagirana kalir.
    expect(trendyolOrderDriver()->pullOrders()->watermark?->getTimestamp() * 1000)
        ->toBe(1762865408000);
});

it('sends an overlapping, at most two week window in epoch milliseconds', function (): void {
    Carbon::setTestNow('2026-08-19 12:00:00');
    Http::fake(['*' => Http::response(Fixture::json('order-stream-page-2'))]);

    trendyolOrderDriver()->pullOrders(new DateTimeImmutable('2026-08-18 09:00:00'));

    Http::assertSent(function (Request $request): bool {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        // Sinir dahil/haric semantigi belgesiz -> pencere bes dakika geri
        // cekilir ve tekrarlar shipmentPackageId uzerinden yutulur.
        return $query['lastModifiedStartDate'] === (string) (strtotime('2026-08-18 08:55:00') * 1000)
            && $query['lastModifiedEndDate'] === (string) (strtotime('2026-08-19 12:00:00') * 1000);
    });

    Carbon::setTestNow();
});

it('clamps a watermark older than the stream three month window', function (): void {
    Carbon::setTestNow('2026-08-19 12:00:00');
    Http::fake(['*' => Http::response(Fixture::json('order-stream-page-2'))]);

    trendyolOrderDriver()->pullOrders(new DateTimeImmutable('2025-01-01 00:00:00'));

    Http::assertSent(function (Request $request): bool {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return $query['lastModifiedStartDate'] === (string) (strtotime('2026-05-20 12:00:00') * 1000)
            && $query['lastModifiedEndDate'] === (string) (strtotime('2026-06-03 12:00:00') * 1000);
    });

    Carbon::setTestNow();
});

it('uses the paged v2 service only for a targeted order number lookup', function (): void {
    Http::fake(['*' => Http::response(Fixture::json('order-v2-single'))]);

    $order = trendyolOrderDriver()->pullOrder('1084507121');

    expect($order?->remoteOrderNumber)->toBe('1084507121')
        ->and($order?->status)->toBe(CanonicalOrderStatus::Invoiced);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'v2/orders?orderNumber=1084507121')
        // 10.000 kayit penceresi page x size uzerinden isler; hedefli sorgu
        // hep ilk sayfadadir (TRENDYOL.md 4.4.1).
        && str_contains($request->url(), 'page=0'));
});

it('returns null when the targeted lookup finds nothing', function (): void {
    Http::fake(['*' => Http::response(['content' => [], 'totalElements' => 0])]);

    expect(trendyolOrderDriver()->pullOrder('yok'))->toBeNull();
});

it('refuses to build an order url without bound credentials', function (): void {
    app(TrendyolDriver::class)->pullOrders();
})->throws(MarketplaceException::class);
