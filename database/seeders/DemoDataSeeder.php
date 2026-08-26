<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ListingSyncState;
use App\Enums\ProcessingStatus;
use App\Marketplaces\Data\Enums\CanonicalOrderStatus;
use App\Marketplaces\Data\Enums\SyncState;
use App\Models\ChannelConnection;
use App\Models\ChannelListing;
use App\Models\ChannelOperation;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Price;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SyncRun;
use App\Models\Warehouse;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Panel sunumu için ÖRNEK tenant verisi. Kök seeder'a (DatabaseSeeder) BİLEREK
 * bağlı değildir — provisioning'de çalışmaz, yalnızca elle çalıştırılır:
 *
 *   php artisan tenants:seed --class='Database\Seeders\DemoDataSeeder' --tenants=1002 --force
 *
 * Panelin senkron kartlarının hepsini doldurur: bağlantı, ürün/varyant/stok,
 * kritik stok, son 7 güne yayılmış siparişler, eşleşmemiş satırlar ve senkron
 * koşuları. Veri içeren bir tenant'ta ikinci kez çalıştırılırsa dokunmaz.
 */
class DemoDataSeeder extends Seeder
{
    // Ornek veri basarken gercek senkron tetiklenmez: listing/stok olaylari
    // pazaryerine istek acan dinleyicilere bagli.
    use WithoutModelEvents;

    public function run(): void
    {
        if (Product::query()->exists()) {
            $this->command->warn('Tenant zaten veri içeriyor, demo seeder atlandı.');

            return;
        }

        $trendyol = ChannelConnection::factory()->create([
            'name' => 'Demo Mağaza Trendyol',
            'last_health_check_at' => now(),
        ]);

        // Kimlik şeması Trendyol'dan farklı: HepsiburadaCredentials::fromArray()
        // merchant_id (UUID) + service_key + çıplak integrator kullanıcı adı bekler.
        $merchantId = fake()->uuid();
        $hepsiburada = ChannelConnection::factory()->create([
            'marketplace' => 'hepsiburada',
            'name' => 'Demo Mağaza Hepsiburada',
            'credentials' => [
                'merchant_id' => $merchantId,
                'service_key' => fake()->uuid(),
                'integrator_user_agent' => 'demo_dev',
                'sit' => false,
            ],
            'external_seller_id' => $merchantId,
            'last_health_check_at' => now(),
        ]);

        $warehouse = Warehouse::firstOrCreate(
            ['code' => 'ANA'],
            ['name' => 'Ana Depo', 'is_default' => true],
        );

        $variants = Product::factory()->active()->count(8)->create()
            ->map(function (Product $product) use ($warehouse): ProductVariant {
                $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
                Price::factory()->create(['variant_id' => $variant->id]);
                InventoryItem::factory()->create([
                    'variant_id' => $variant->id,
                    'warehouse_id' => $warehouse->id,
                ]);

                return $variant;
            });

        // Kanal eşleşmeleri: çoğu varyant Trendyol'da, yarısı Hepsiburada'da
        // satışta; biri gönderim hatasında, sonuncusu hiçbir kanalda değil —
        // stok ekranındaki kanal avatarları her durumu gösterebilsin.
        foreach ($variants as $i => $variant) {
            if ($i < 7) {
                ChannelListing::factory()->synced()->create([
                    'connection_id' => $trendyol->id,
                    'variant_id' => $variant->id,
                ]);
            }

            if ($i < 4) {
                ChannelListing::factory()->synced()->create([
                    'connection_id' => $hepsiburada->id,
                    'variant_id' => $variant->id,
                ]);
            }
        }

        ChannelListing::factory()->create([
            'connection_id' => $hepsiburada->id,
            'variant_id' => $variants[5]->id,
            'sync_state' => ListingSyncState::Failed,
            'error' => ['message' => 'Barkod pazaryerinde bulunamadı'],
        ]);

        // Kritik stok kartı boş kalmasın: iki varyant emniyet stokunun altında.
        InventoryItem::query()
            ->whereIn('variant_id', $variants->take(2)->pluck('id'))
            ->update(['on_hand' => 2, 'safety_stock' => 10]);

        // Son ~7 güne yayılmış siparişler; ilki bugüne düşer ki "bugün" kartı
        // dolsun. Her üç siparişten biri Hepsiburada'ya düşer.
        foreach (range(1, 12) as $i) {
            $connection = $i % 3 === 0 ? $hepsiburada : $trendyol;
            $order = Order::query()->forceCreate([
                'connection_id' => $connection->id,
                'remote_id' => 'PKG-'.(1000 + $i),
                'remote_order_number' => ($connection->is($hepsiburada) ? 'HB-' : 'TY-').(52000 + $i),
                'status' => CanonicalOrderStatus::Created,
                'external_status' => 'Created',
                'placed_at' => Carbon::now('Europe/Istanbul')->subHours(($i - 1) * 14)->utc(),
                'totals' => ['net' => fake()->randomFloat(2, 150, 2500)],
            ]);

            $variant = $variants[$i % $variants->count()];
            OrderLine::query()->forceCreate([
                'order_id' => $order->id,
                'variant_id' => $variant->id,
                'remote_line_id' => 'L-'.$i.'-1',
                'sku' => $variant->sku,
                'barcode' => $variant->barcode,
                'quantity' => fake()->numberBetween(1, 3),
                'unit_price' => fake()->randomFloat(2, 50, 800),
                'status' => CanonicalOrderStatus::Created->value,
            ]);

            // Birkaç siparişe katalogda karşılığı olmayan satır: "eşleşmemiş" kartı.
            if ($i <= 3) {
                OrderLine::query()->forceCreate([
                    'order_id' => $order->id,
                    'variant_id' => null,
                    'remote_line_id' => 'L-'.$i.'-2',
                    'sku' => 'BILINMEYEN-'.$i,
                    'barcode' => '869'.fake()->unique()->numerify('##########'),
                    'quantity' => 1,
                    'unit_price' => fake()->randomFloat(2, 50, 300),
                    'status' => CanonicalOrderStatus::Created->value,
                ]);
            }
        }

        // Senkron sağlığı: karışık sonuçlu son koşular + outbox'ta bekleyen/batan işler.
        $runs = [
            [$trendyol, 'orders', ProcessingStatus::Completed],
            [$hepsiburada, 'orders', ProcessingStatus::Completed],
            [$trendyol, 'products', ProcessingStatus::Completed],
            [$trendyol, 'stock', ProcessingStatus::Failed],
            [$hepsiburada, 'prices', ProcessingStatus::Running],
        ];
        foreach ($runs as $i => [$runConnection, $resource, $status]) {
            SyncRun::factory()->create([
                'connection_id' => $runConnection->id,
                'resource' => $resource,
                'status' => $status,
                'started_at' => now()->subMinutes(30 * ($i + 1)),
                'finished_at' => $status === ProcessingStatus::Running ? null : now()->subMinutes(30 * ($i + 1))->addMinutes(3),
                'error' => $status === ProcessingStatus::Failed ? 'Trendyol 429: istek limiti aşıldı' : null,
            ]);
        }

        ChannelOperation::factory()->count(2)->create(['connection_id' => $trendyol->id]);
        ChannelOperation::factory()->create(['connection_id' => $hepsiburada->id]);
        ChannelOperation::factory()->create([
            'connection_id' => $trendyol->id,
            'status' => SyncState::Failed,
            'attempts' => 3,
            'error' => 'Barkod pazaryerinde bulunamadı',
        ]);
    }
}
