<?php

declare(strict_types=1);

use App\Marketplaces\Data\Enums\CanonicalOrderStatus;
use App\Marketplaces\Data\MappingContext;
use App\Marketplaces\Trendyol\Enums\TrendyolPackageStatus;
use App\Marketplaces\Trendyol\Mappers\OrderMapper;
use Tests\Fixtures\Trendyol\Fixture;

/**
 * @return array<string, mixed>
 */
function trendyolPackage(string $fixture = 'order-stream-page-1', int $index = 0): array
{
    /** @var list<array<string, mixed>> $content */
    $content = Fixture::json($fixture)['content'];

    return $content[$index];
}

it('maps a shipment package onto the canonical order grain', function (): void {
    $order = (new OrderMapper)->toCanonical(trendyolPackage());

    // Bir paket = bir kanonik siparis. orderNumber paketler arasi mutabakat
    // anahtari olarak yaninda durur (TRENDYOL.md 9.1).
    expect($order->remoteId)->toBe('1234567')
        ->and($order->remoteOrderNumber)->toBe('1084507121')
        ->and($order->status)->toBe(CanonicalOrderStatus::Created)
        ->and($order->externalStatus)->toBe('Created')
        ->and($order->currency)->toBe('TRY')
        ->and($order->placedAt->format('Y-m-d H:i:s'))->toBe('2025-11-11 11:20:00')
        ->and($order->totals)->toBe([
            'gross' => '449.9000',
            'discount' => '30.0000',
            'net' => '419.9000',
        ]);
});

it('normalises interior whitespace out of barcodes', function (): void {
    $order = (new OrderMapper)->toCanonical(trendyolPackage());

    // Trendyol "KOBI 001" barkodunu sessizce "KOBI001" olarak iceri alir; join
    // anahtari onun dondurdugu bicim olmak zorunda (TRENDYOL.md 9.2).
    expect($order->lines[0]->barcode)->toBe('KOBI001')
        ->and($order->lines[1]->barcode)->toBe('ESLESMEYEN-9');
});

it('keeps money as fixed point strings and the tracking number as a string', function (): void {
    $order = (new OrderMapper)->toCanonical(trendyolPackage());

    expect($order->lines[0]->unitPrice)->toBe('149.9000')
        ->and($order->lines[0]->vatRate)->toBe('20.0000')
        ->and($order->lines[0]->commission)->toBe('12.5000')
        // int64 ve JS MAX_SAFE_INTEGER'i asar, uctan uca string (TRENDYOL.md 9.9).
        ->and($order->shipments[0]->trackingNumber)->toBe('7318429576123456789')
        ->and($order->shipments[0]->deci)->toBe(3.5);
});

it('reads the shipped and delivered instants out of packageHistories', function (): void {
    $order = (new OrderMapper)->toCanonical(trendyolPackage('order-stream-page-2'));

    expect($order->status)->toBe(CanonicalOrderStatus::Delivered)
        ->and($order->shipments[0]->shippedAt?->format('Y-m-d H:i'))->toBe('2025-11-09 23:13')
        ->and($order->shipments[0]->deliveredAt?->format('Y-m-d H:i'))->toBe('2025-11-10 15:53');
});

it('parses the line status separately from the package status', function (): void {
    $package = trendyolPackage();
    // Kismi iptalde satir statusu paket statusunden ayrisir (TRENDYOL.md 5.3).
    $package['lines'][1]['orderLineItemStatusName'] = 'Cancelled';

    $order = (new OrderMapper)->toCanonical($package);

    expect($order->status)->toBe(CanonicalOrderStatus::Created)
        ->and($order->lines[0]->status)->toBe(CanonicalOrderStatus::Created)
        ->and($order->lines[1]->status)->toBe(CanonicalOrderStatus::Cancelled)
        ->and($order->lines[1]->externalStatus)->toBe('Cancelled');
});

it('treats Awaiting as pending payment, which reserves stock and nothing else', function (): void {
    $order = (new OrderMapper)->toCanonical(trendyolPackage(index: 1));

    expect($order->status)->toBe(CanonicalOrderStatus::PendingPayment)
        ->and($order->externalStatus)->toBe('Awaiting')
        ->and($order->status->allowsFulfilment())->toBeFalse()
        ->and($order->status->reservesStock())->toBeTrue()
        ->and($order->status->isTerminal())->toBeFalse();
});

it('keeps an unknown remote status raw instead of folding it into a default', function (): void {
    // `Repack` yalnizca tek bir duz yazi cumlesinde geciyor; OpenAPI enum'unda,
    // statu tablosunda ve webhook listesinde YOK — bayat dokumantasyon.
    expect(TrendyolPackageStatus::tryFromRemote('Repack'))->toBeNull();

    $order = (new OrderMapper)->toCanonical(trendyolPackage('order-stream-unknown-status'));

    expect($order->externalStatus)->toBe('Repack')
        // Paket asla dusurulmez ve asla hazirlanabilir bir statuye katlanmaz.
        ->and($order->status->allowsFulfilment())->toBeFalse()
        ->and($order->lines)->toHaveCount(1);
});

it('carries personal data in one block and never anywhere else', function (): void {
    $order = (new OrderMapper)->toCanonical(trendyolPackage());

    expect($order->customer)->toMatchArray([
        'firstName' => 'Ayşe',
        'lastName' => 'Yılmaz',
        'email' => 'ayse.yilmaz@example.com',
        'phone' => '05001234567',
        'identityNumber' => '12345678901',
    ])
        ->and($order->customer['shippingAddress']['city'] ?? null)->toBe('İstanbul');
});

it('round trips the package identity through toRemote', function (): void {
    $package = trendyolPackage();
    $mapper = new OrderMapper;

    $remote = $mapper->toRemote(
        $mapper->toCanonical($package),
        new MappingContext(externalSellerId: '4321'),
    );

    expect($remote)->toBe([
        'shipmentPackageId' => '1234567',
        'orderNumber' => '1084507121',
        'status' => 'Created',
        'lines' => [
            ['lineId' => '90001', 'quantity' => 2],
            ['lineId' => '90002', 'quantity' => 1],
        ],
    ]);

    // Ve tekrar kanonige donduruldugunde kimlik alanlari degismez.
    expect($mapper->toCanonical($package + $remote)->remoteId)->toBe('1234567');
});

it('maps every documented package status onto a canonical one', function (): void {
    $expected = [
        'Awaiting' => CanonicalOrderStatus::PendingPayment,
        'Created' => CanonicalOrderStatus::Created,
        'Picking' => CanonicalOrderStatus::Picking,
        'Invoiced' => CanonicalOrderStatus::Invoiced,
        'Shipped' => CanonicalOrderStatus::Shipped,
        'Cancelled' => CanonicalOrderStatus::Cancelled,
        'Delivered' => CanonicalOrderStatus::Delivered,
        'UnDelivered' => CanonicalOrderStatus::Undelivered,
        'Returned' => CanonicalOrderStatus::Returned,
        'AtCollectionPoint' => CanonicalOrderStatus::AtCollectionPoint,
        'UnPacked' => CanonicalOrderStatus::Unpacked,
        'UnSupplied' => CanonicalOrderStatus::Unsupplied,
    ];

    foreach ($expected as $remote => $canonical) {
        expect(TrendyolPackageStatus::tryFromRemote($remote)?->toCanonical())->toBe($canonical);
    }

    // Filtre kumesi 12 deger, yanit kumesi 8: tip yanit semasindan turetilmez,
    // yoksa dort deger round-trip edilemez (TRENDYOL.md 5.1 vs 5.2).
    expect(TrendyolPackageStatus::cases())->toHaveCount(12);
});
