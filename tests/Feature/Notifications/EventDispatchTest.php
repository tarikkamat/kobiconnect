<?php

declare(strict_types=1);

use App\Actions\Sync\ApplyBatchResult;
use App\Events\NotificationEventOccurred;
use App\Marketplaces\Data\Enums\SyncState;
use App\Marketplaces\Data\PushResult;
use App\Models\ChannelConnection;
use App\Models\ChannelOperation;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Notifications\NotificationEvent;
use Database\Seeders\TenantRoleSeeder;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->seed(TenantRoleSeeder::class);
});

it('dispatches StockCriticalLow when available falls to or below safety_stock', function (): void {
    Event::fake([NotificationEventOccurred::class]);

    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->for($product)->create(['sku' => 'TEST-SKU-CRITICAL']);
    $warehouse = Warehouse::factory()->create();

    $item = InventoryItem::create([
        'variant_id' => $variant->getKey(),
        'warehouse_id' => $warehouse->getKey(),
        'on_hand' => 10,
        'reserved' => 8, // available = 10 - 8 = 2
        'safety_stock' => 5, // 2 <= 5 -> critical!
    ]);

    Event::assertDispatched(NotificationEventOccurred::class, function (NotificationEventOccurred $event) use ($variant) {
        return $event->event === NotificationEvent::StockCriticalLow
            && $event->payload['sku'] === 'TEST-SKU-CRITICAL'
            && $event->payload['variant_id'] === (string) $variant->getKey();
    });
});

it('dispatches ProductApproved and ProductRejected in ApplyBatchResult', function (): void {
    Event::fake([NotificationEventOccurred::class]);

    $connection = ChannelConnection::factory()->create(['name' => 'Trendyol Test']);

    $opApproved = ChannelOperation::factory()->create([
        'connection_id' => $connection->getKey(),
        'entity_type' => 'product',
        'entity_id' => 101,
        'operation' => 'product_push',
        'desired_state' => ['reference' => 'SKU-APP-1'],
        'status' => SyncState::InFlight,
    ]);

    $opRejected = ChannelOperation::factory()->create([
        'connection_id' => $connection->getKey(),
        'entity_type' => 'product',
        'entity_id' => 102,
        'operation' => 'product_push',
        'desired_state' => ['reference' => 'SKU-REJ-1'],
        'status' => SyncState::InFlight,
    ]);

    $result = new PushResult(
        accepted: true,
        itemResults: [
            'SKU-APP-1' => ['accepted' => true, 'remoteId' => 'REM-1'],
            'SKU-REJ-1' => ['accepted' => false, 'message' => 'Geçersiz kategori'],
        ],
    );

    $apply = app(ApplyBatchResult::class);
    $apply(collect([$opApproved, $opRejected]), $result, final: true);

    Event::assertDispatched(NotificationEventOccurred::class, function (NotificationEventOccurred $event) {
        return $event->event === NotificationEvent::ProductApproved
            && $event->payload['product_id'] === '101'
            && $event->payload['sku'] === 'SKU-APP-1';
    });

    Event::assertDispatched(NotificationEventOccurred::class, function (NotificationEventOccurred $event) {
        return $event->event === NotificationEvent::ProductRejected
            && $event->payload['product_id'] === '102'
            && $event->payload['reason'] === 'Geçersiz kategori';
    });
});
