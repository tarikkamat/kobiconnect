<?php

declare(strict_types=1);

use App\Marketplaces\Contracts\SupportsBrandCatalog;
use App\Marketplaces\Contracts\SupportsCatalogMatching;
use App\Marketplaces\Data\MappingContext;
use App\Marketplaces\Data\PriceData;
use App\Marketplaces\Data\StockData;
use App\Marketplaces\Hepsiburada\HepsiburadaCredentials;
use App\Marketplaces\Hepsiburada\HepsiburadaDriver;
use App\Marketplaces\Support\Capability;
use App\Marketplaces\Support\Exceptions\MarketplaceException;
use App\Marketplaces\Support\MarketplaceManager;
use App\Support\Sync\BindsCredentials;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\Hepsiburada\Fixture;

/**
 * Fixture'lar SIT ortamindan OLCULMUS gercek yanitlardir (bkz. Fixture).
 */
function hbDriver(): HepsiburadaDriver
{
    $driver = app(MarketplaceManager::class)->driver('hepsiburada');

    expect($driver)->toBeInstanceOf(HepsiburadaDriver::class);

    return $driver->for(new HepsiburadaCredentials(
        merchantId: 'c5779c28-af0a-43e1-a8a6-8b30782e79ec',
        serviceKey: 'test-secret',
        integrator: 'finansfatura_dev',
        sit: true,
    ));
}

it('yetenekleri arayuzlerden turetir, elle listelemez', function (): void {
    $driver = app(MarketplaceManager::class)->driver('hepsiburada');

    $claimed = $driver->capabilities();

    foreach (Capability::cases() as $capability) {
        $implements = $driver instanceof ($capability->contract());

        expect(in_array($capability, $claimed, true))->toBe(
            $implements,
            "{$capability->value}: iddia ile arayuz uyusmuyor",
        );
    }
});

it('marka katalogu iddia ETMEZ — Hepsiburada da marka varligi yok', function (): void {
    $driver = app(MarketplaceManager::class)->driver('hepsiburada');

    expect($driver)->not->toBeInstanceOf(SupportsBrandCatalog::class)
        ->and($driver->capabilities())->not->toContain(Capability::BrandCatalog);
});

it('on eslesme yetenegini iddia eder — islenmezse hicbir urun satilmaz', function (): void {
    $driver = app(MarketplaceManager::class)->driver('hepsiburada');

    expect($driver)->toBeInstanceOf(SupportsCatalogMatching::class)
        ->and($driver)->toBeInstanceOf(BindsCredentials::class);
});

it('kimlik bilgisi olmadan cagrilirsa anlamli hata verir', function (): void {
    $driver = app(MarketplaceManager::class)->driver('hepsiburada');

    expect(fn () => $driver->pullOrders())
        ->toThrow(MarketplaceException::class);
});

it('merchantId UUID degilse kimlik bilgisini reddeder', function (): void {
    expect(fn () => HepsiburadaCredentials::fromArray([
        'merchant_id' => 'not-a-uuid',
        'service_key' => 'x',
        'integrator' => 'finansfatura_dev',
    ]))->toThrow(InvalidArgumentException::class);
});

it('katalogu mpop host undan page/size ile okur', function (): void {
    // Olculmus yanit `last: false` tasiyor; ikinci sayfa akisi bitiriyor.
    Http::fakeSequence()
        ->push(Fixture::json('measured-categories'))
        ->push(Fixture::json('measured-categories-last'));

    $nodes = hbDriver()->categoryTree();

    expect($nodes)->not->toBeEmpty();

    Http::assertSent(function ($request): bool {
        expect($request->url())->toContain('mpop-sit.hepsiburada.com')
            ->and($request->url())->toContain('/product/api/categories/get-all-categories')
            // Katalog sayfalamasi page/size (listing ve siparis limit/offset).
            ->and($request->url())->toContain('page=')
            ->and($request->url())->toContain('size=');

        return true;
    });
});

it('zorunlu User-Agent basligini gonderir', function (): void {
    Http::fakeSequence()
        ->push(Fixture::json('measured-categories'))
        ->push(Fixture::json('measured-categories-last'));

    hbDriver()->categoryTree();

    Http::assertSent(fn ($request): bool => $request->header('User-Agent')[0] === 'finansfatura_dev');
});

it('attribute degerlerini TEKIL /attribute/ yolundan ceker', function (): void {
    Http::fake([
        '*/attributes' => Http::response(Fixture::json('measured-attributes')),
        '*' => Http::response(['success' => true, 'data' => []]),
    ]);

    hbDriver()->categoryAttributes('26012174');

    // Olculdu: /attributes/{id}/values 404 doner, /attribute/{id}/values 200.
    Http::assertSent(fn ($request): bool => ! str_contains($request->url(), '/attributes/000009D/'));
});

it('uc attribute kovasini tek kanonik listeye duzler', function (): void {
    Http::fake([
        '*/attributes' => Http::response(Fixture::json('measured-attributes')),
        '*' => Http::response(['success' => true, 'data' => []]),
    ]);

    $attributes = hbDriver()->categoryAttributes('26012174');

    // Olculmus yanit: baseAttributes 22 + attributes 12 + variantAttributes 0.
    expect($attributes)->toHaveCount(34)
        ->and(collect($attributes)->pluck('remoteId'))->toContain('merchantSku');
});

it('siparisleri oms host undan limit/offset ile okur', function (): void {
    Http::fake(['*' => Http::response(Fixture::json('measured-orders'))]);

    hbDriver()->pullOrders();

    Http::assertSent(function ($request): bool {
        expect($request->url())->toContain('oms-external-sit.hepsiburada.com')
            ->and($request->url())->toContain('limit=')
            ->and($request->url())->toContain('offset=');

        return true;
    });
});

it('listeleme okumasini cagirana gore dogru DTO ya cevirir', function (): void {
    Http::fake(['*' => Http::response(Fixture::json('measured-listings'))]);

    $stock = hbDriver()->pullStock();
    $prices = hbDriver()->pullPrices();

    // Ayni listing satiri hem fiyat hem stok tasir.
    expect($stock->items[0])->toBeInstanceOf(StockData::class)
        ->and($prices->items[0])->toBeInstanceOf(PriceData::class);
});

it('fiyati virgullu Turkce ondalik olarak gonderir', function (): void {
    Http::fake(['*' => Http::response(['id' => 'upload-1'])]);

    hbDriver()->pushPrices([
        new PriceData(reference: 'KOBI001', listPrice: '130.50', salePrice: '130.50'),
    ], new MappingContext(externalSellerId: 'c5779c28-af0a-43e1-a8a6-8b30782e79ec'));

    Http::assertSent(function ($request): bool {
        // Nokta ayirici SESSIZ hatadir: urun 0 fiyatla canliya cikar.
        expect($request->data()[0]['price'])->toBe('130,50');

        return true;
    });
});

it('bos gonderimde ag cagrisi yapmaz', function (): void {
    Http::fake();

    $result = hbDriver()->pushStock([], new MappingContext(externalSellerId: 'c5779c28-af0a-43e1-a8a6-8b30782e79ec'));

    expect($result->accepted)->toBeTrue();
    Http::assertNothingSent();
});
