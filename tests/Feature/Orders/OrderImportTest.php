<?php

declare(strict_types=1);

use App\Actions\Orders\ImportOrders;
use App\Models\ChannelConnection;
use App\Models\ProductVariant;
use Carbon\CarbonInterval;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\Fixtures\Trendyol\Fixture;

beforeEach(function (): void {
    $this->grantActiveLicense();

    config()->set('marketplaces.trendyol.rate_limits.groups.order_read', [
        '50k' => 30, '75k' => 40, '150k' => 50, '500k' => 100, 'unlimited' => 100,
    ]);
    config()->set('marketplaces.trendyol.rate_limits.endpoints.getShipmentPackagesStream', 'order_read');
    config()->set('marketplaces.trendyol.rate_limits.endpoints.getShipmentPackages', 'order_read');

    Sleep::fake();

    Http::fake([
        '*nextCursor=*' => Http::response(Fixture::json('order-stream-page-2')),
        '*orders/stream*' => Http::response(Fixture::json('order-stream-page-1')),
    ]);
});

function orderConnection(): ChannelConnection
{
    return ChannelConnection::factory()->create([
        'credentials' => ['seller_id' => '4321', 'api_key' => 'key', 'api_secret' => 'secret'],
    ]);
}

it('imports every package of the stream and matches lines on barcode', function (): void {
    // Trendyol barkodun ic bosluklarini siler; katalog tarafi ayni normalize
    // edilmis bicimle eslesir (TRENDYOL.md 9.2).
    $variant = ProductVariant::factory()->create(['barcode' => 'KOBI001']);

    $stats = app(ImportOrders::class)->handle(orderConnection());

    expect($stats['orders'])->toBe(3)
        ->and($stats['drained'])->toBeTrue()
        ->and(DB::table('orders')->count())->toBe(3)
        ->and(DB::table('shipment_packages')->count())->toBe(3);

    $line = DB::table('order_lines')->where('remote_line_id', '90001')->first();

    expect($line->variant_id)->toBe($variant->getKey())
        ->and($line->barcode)->toBe('KOBI001')
        ->and($line->quantity)->toBe(2)
        ->and((float) $line->unit_price)->toBe(149.9);
});

it('never rejects an order because a line did not match', function (): void {
    $stats = app(ImportOrders::class)->handle(orderConnection());

    // Hicbir varyant yok: butun satirlar eslesmemis, ama siparisler eksiksiz.
    expect($stats['unmatched'])->toBe(4)
        ->and(DB::table('orders')->count())->toBe(3)
        ->and(DB::table('order_lines')->whereNull('variant_id')->count())->toBe(4)
        ->and(DB::table('order_lines')->where('remote_line_id', '90002')->value('sku'))
        ->toBe('ESLESMEYEN-9');
});

it('is idempotent: the same package pulled twice writes one row', function (): void {
    $connection = orderConnection();
    $action = app(ImportOrders::class);

    $action->handle($connection);
    $action->handle($connection);

    expect(DB::table('orders')->count())->toBe(3)
        ->and(DB::table('order_lines')->count())->toBe(4)
        ->and(DB::table('shipment_packages')->count())->toBe(3)
        // packageHistories bir projeksiyon: tekrar cekmek gecmisi cogaltmaz.
        ->and(DB::table('order_status_history')->count())->toBe(7);
});

it('projects packageHistories into the status history with its previous status', function (): void {
    app(ImportOrders::class)->handle(orderConnection());

    $orderId = DB::table('orders')->where('remote_id', '1230001')->value('id');

    $history = DB::table('order_status_history')
        ->where('order_id', $orderId)
        ->orderBy('occurred_at')
        ->get(['from_status', 'to_status', 'source']);

    expect($history)->toHaveCount(5)
        ->and($history[0]->from_status)->toBeNull()
        ->and($history[0]->to_status)->toBe('Created')
        ->and($history[4]->from_status)->toBe('Shipped')
        ->and($history[4]->to_status)->toBe('Delivered')
        ->and($history[0]->source)->toBe('pull');
});

it('stores the customer block and the raw payload encrypted', function (): void {
    app(ImportOrders::class)->handle(orderConnection());

    $row = DB::table('orders')->where('remote_id', '1234567')->first(['customer', 'raw']);

    // Kolonda duz metin YOK: ne TCKN, ne e-posta, ne tam adres.
    expect($row->customer)->not->toContain('12345678901')
        ->and($row->customer)->not->toContain('ayse.yilmaz@example.com')
        ->and($row->raw)->not->toContain('12345678901');

    // AsEncryptedArrayObject ile ayni tel formati: model cast'i oldugu gibi okur.
    $customer = json_decode(Crypt::decryptString($row->customer), true);

    expect($customer['identityNumber'])->toBe('12345678901')
        ->and($customer['shippingAddress']['city'])->toBe('İstanbul');
});

it('commits the watermark only when the stream drains, and the cursor until then', function (): void {
    $connection = orderConnection();

    app(ImportOrders::class)->handle($connection);

    $cursor = DB::table('sync_cursors')
        ->where('connection_id', $connection->getKey())
        ->where('resource', 'orders')
        ->first(['watermark', 'cursor']);

    expect($cursor->cursor)->toBeNull()
        ->and($cursor->watermark)->not->toBeNull();

    // Tukenmemis akis: imlec saklanir, watermark ilerlemez — akis
    // lastModifiedDate DESC sirali oldugu icin en yenisi ilk sayfada gelir.
    DB::table('sync_cursors')->where('connection_id', $connection->getKey())->delete();

    app(ImportOrders::class)->handle($connection, maxPages: 1);

    $partial = DB::table('sync_cursors')
        ->where('connection_id', $connection->getKey())
        ->first(['watermark', 'cursor']);

    expect($partial->cursor)->toBe('eyJsYXN0TW9kaWZpZWREYXRlIjoxNzYyODYxNTAwMDAwfQ==')
        ->and($partial->watermark)->toBeNull();
});

it('ignores a replay that is older than what is already stored', function (): void {
    $connection = orderConnection();

    app(ImportOrders::class)->handle($connection);

    DB::table('orders')->where('remote_id', '1234567')->update([
        'status' => 'delivered',
        'remote_last_modified_at' => now()->addDay(),
    ]);

    app(ImportOrders::class)->handle($connection);

    // Monoton koruma: eski bir olay satiri geri sarmaz (TRENDYOL.md 10.7).
    expect(DB::table('orders')->where('remote_id', '1234567')->value('status'))->toBe('delivered');
});

it('waits between stream pages as Trendyol asks', function (): void {
    app(ImportOrders::class)->handle(orderConnection());

    Sleep::assertSlept(fn (CarbonInterval $duration): bool => $duration->totalSeconds === 5.0, 1);
});
