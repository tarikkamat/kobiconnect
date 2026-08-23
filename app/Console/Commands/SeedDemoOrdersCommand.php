<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Marketplaces\Data\Enums\CanonicalOrderStatus;
use App\Models\ChannelConnection;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\Warehouse;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class SeedDemoOrdersCommand extends Command
{
    protected $signature = 'demo:seed-orders
                            {--tenant= : Specific tenant ID (e.g. 1004)}
                            {--count=50 : Number of demo orders to generate}';

    protected $description = 'Generate realistic demo orders with varied margins, commissions, and shipping details';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        $count = (int) $this->option('count');

        $tenants = $tenantId !== null
            ? Tenant::query()->where('id', (string) $tenantId)->get()
            : Tenant::query()->get();

        if ($tenants->isEmpty()) {
            $this->error('Tenant bulunamadı.');

            return self::FAILURE;
        }

        foreach ($tenants as $tenant) {
            $this->info("Tenant {$tenant->id} için {$count} adet demo sipariş oluşturuluyor...");

            $tenant->run(function () use ($count): void {
                $this->seedOrdersForCurrentTenant($count);
            });
        }

        $this->info('Tüm demo siparişler başarıyla oluşturuldu!');

        return self::SUCCESS;
    }

    private function seedOrdersForCurrentTenant(int $count): void
    {
        // 1. Kanalları sağla (Trendyol & Hepsiburada)
        $trendyol = ChannelConnection::query()->where('marketplace', 'trendyol')->first()
            ?? ChannelConnection::factory()->create([
                'marketplace' => 'trendyol',
                'name' => 'Trendyol Ana Mağaza',
                'last_health_check_at' => now(),
            ]);

        $hepsiburada = ChannelConnection::query()->where('marketplace', 'hepsiburada')->first()
            ?? ChannelConnection::factory()->create([
                'marketplace' => 'hepsiburada',
                'name' => 'Hepsiburada Mağaza',
                'credentials' => [
                    'merchant_id' => fake()->uuid(),
                    'service_key' => fake()->uuid(),
                    'integrator_user_agent' => 'demo_dev',
                ],
                'last_health_check_at' => now(),
            ]);

        $connections = [$trendyol, $hepsiburada];

        // 2. Ürün / Varyantları sağla
        $variants = ProductVariant::query()->get();
        if ($variants->isEmpty()) {
            $warehouse = Warehouse::firstOrCreate(['code' => 'ANA'], ['name' => 'Ana Depo', 'is_default' => true]);
            $products = Product::factory()->active()->count(10)->create();
            foreach ($products as $p) {
                $v = ProductVariant::factory()->create(['product_id' => $p->id]);
                $variants->push($v);
            }
        }

        $commissionRates = [8.5, 11.0, 14.5, 17.0, 20.0, 22.5, 25.0];
        $cargoProviders = [
            'Trendyol Express',
            'HepsiJet',
            'Yurtiçi Kargo',
            'Aras Kargo',
            'MNG Kargo',
            'Sendeo',
        ];
        $statuses = [
            CanonicalOrderStatus::Delivered->value => 65, // %65 teslim edildi
            CanonicalOrderStatus::Shipped->value => 15,   // %15 kargoda
            CanonicalOrderStatus::Invoiced->value => 8,   // %8 faturalandı
            CanonicalOrderStatus::Created->value => 5,    // %5 yeni
            CanonicalOrderStatus::Cancelled->value => 4,  // %4 iptal
            CanonicalOrderStatus::Returned->value => 3,   // %3 iade
        ];

        $customers = [
            ['first' => 'Ahmet', 'last' => 'Yılmaz', 'city' => 'İstanbul', 'district' => 'Kadıköy'],
            ['first' => 'Mehmet', 'last' => 'Kaya', 'city' => 'Ankara', 'district' => 'Çankaya'],
            ['first' => 'Ayşe', 'last' => 'Demir', 'city' => 'İzmir', 'district' => 'Karşıyaka'],
            ['first' => 'Fatma', 'last' => 'Çelik', 'city' => 'Bursa', 'district' => 'Nilüfer'],
            ['first' => 'Emre', 'last' => 'Şahin', 'city' => 'Antalya', 'district' => 'Muratpaşa'],
            ['first' => 'Zeynep', 'last' => 'Yıldız', 'city' => 'Adana', 'district' => 'Seyhan'],
            ['first' => 'Burak', 'last' => 'Öztürk', 'city' => 'Kocaeli', 'district' => 'İzmit'],
            ['first' => 'Elif', 'last' => 'Aydın', 'city' => 'Eskişehir', 'district' => 'Tepebaşı'],
            ['first' => 'Can', 'last' => 'Koç', 'city' => 'Gaziantep', 'district' => 'Şahinbey'],
            ['first' => 'Selin', 'last' => 'Arslan', 'city' => 'Samsun', 'district' => 'Atakum'],
        ];

        $now = CarbonImmutable::now('Europe/Istanbul');

        for ($i = 1; $i <= $count; $i++) {
            /** @var ChannelConnection $conn */
            $conn = $connections[$i % count($connections)];
            $isHb = $conn->marketplace === 'hepsiburada';

            // Son 45 güne yayılmış sipariş tarihleri (son günlere daha sık)
            $daysAgo = (int) (pow(fake()->randomFloat(2, 0, 1), 1.8) * 45);
            $hoursAgo = fake()->numberBetween(0, 23);
            $minutesAgo = fake()->numberBetween(0, 59);
            $placedAt = $now->subDays($daysAgo)->setTime($hoursAgo, $minutesAgo)->toDateTimeString();

            // Durum ağırlıklı rastgele seçim
            $rand = fake()->numberBetween(1, 100);
            $cum = 0;
            $selectedStatus = CanonicalOrderStatus::Delivered->value;
            foreach ($statuses as $st => $weight) {
                $cum += $weight;
                if ($rand <= $cum) {
                    $selectedStatus = $st;
                    break;
                }
            }

            $orderNo = ($isHb ? 'HB-' : 'TY-').(90000000 + $i * 137);
            $remoteId = 'PKG-'.(800000 + $i);

            $customerInfo = $customers[$i % count($customers)];
            $encryptedCustomer = Crypt::encryptString(json_encode([
                'firstName' => $customerInfo['first'],
                'lastName' => $customerInfo['last'],
                'email' => strtolower($customerInfo['first']).'.'.strtolower($customerInfo['last']).'@example.com',
                'phone' => '05'.fake()->numerify('#########'),
                'shippingAddress' => [
                    'city' => $customerInfo['city'],
                    'district' => $customerInfo['district'],
                    'address' => $customerInfo['district'].' Mah. '.fake()->streetName().' No:'.fake()->buildingNumber(),
                ],
            ]));

            // Kalemler
            $lineCount = fake()->randomElement([1, 1, 1, 2, 2, 3]);
            $orderGross = 0.0;
            $orderCommission = 0.0;
            $lines = [];

            for ($li = 1; $li <= $lineCount; $li++) {
                $variant = $variants->isNotEmpty() ? $variants[($i + $li) % $variants->count()] : null;
                $sku = $variant?->sku ?? ('SKU-'.fake()->numerify('###-####'));
                $barcode = $variant?->barcode ?? fake()->ean13();

                $unitPrice = fake()->randomElement([
                    fake()->randomFloat(2, 85, 250),
                    fake()->randomFloat(2, 250, 750),
                    fake()->randomFloat(2, 750, 1850),
                    fake()->randomFloat(2, 1850, 3400),
                ]);
                $quantity = fake()->randomElement([1, 1, 1, 2, 3]);
                $commission = $commissionRates[array_rand($commissionRates)];
                $discount = fake()->randomElement([0.0, 0.0, 0.0, 25.0, 50.0]);

                $lineGross = $unitPrice * $quantity;
                $orderGross += $lineGross;
                $orderCommission += ($lineGross * ($commission / 100.0));

                $lines[] = [
                    'remote_line_id' => 'LINE-'.$orderNo.'-'.$li,
                    'variant_id' => $variant?->id,
                    'sku' => $sku,
                    'barcode' => $barcode,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discounts' => json_encode($discount > 0 ? ['total' => $discount] : []),
                    'commission' => $commission,
                    'vat_rate' => 20.0,
                    'status' => $selectedStatus,
                    'external_status' => ucfirst($selectedStatus),
                    'created_at' => $placedAt,
                    'updated_at' => $placedAt,
                ];
            }

            // Kargo ve ceza simülasyonu
            $cargo = $cargoProviders[array_rand($cargoProviders)];
            $deci = fake()->randomFloat(2, 1.0, 7.5);
            $trackingNo = fake()->numerify('73184295##########');

            // Standart kargo ücreti (40 - 65 TL)
            $shippingCost = fake()->randomFloat(2, 38.50, 62.00);

            // Kargo Desi Aşım & Baremi Cezası (%28 olasılıkla veya 3.5 deci üstünde)
            $hasCargoPenalty = $deci > 3.5 || fake()->boolean(28);
            $cargoPenalty = $hasCargoPenalty ? fake()->randomFloat(2, 19.50, 68.00) : 0.0;

            // Gecikme / İptal Cezası (İptal/İadelerde veya %12 rastgele gecikmede)
            $hasLatePenalty = in_array($selectedStatus, ['cancelled', 'returned'], true) || fake()->boolean(12);
            $latePenalty = $hasLatePenalty ? fake()->randomFloat(2, 50.00, 150.00) : 0.0;

            $totalDeductions = round($orderCommission + $shippingCost + $cargoPenalty + $latePenalty, 2);
            $netPayout = max(0.0, round($orderGross - $totalDeductions, 2));

            $orderTotals = [
                'gross' => round($orderGross, 2),
                'commission' => round($orderCommission, 2),
                'shipping_cost' => $shippingCost,
                'cargo_penalty' => $cargoPenalty,
                'late_penalty' => $latePenalty,
                'total_deductions' => $totalDeductions,
                'net' => $netPayout,
            ];

            $orderId = DB::table('orders')->insertGetId([
                'connection_id' => $conn->id,
                'remote_id' => $remoteId,
                'remote_order_number' => $orderNo,
                'status' => $selectedStatus,
                'external_status' => ucfirst($selectedStatus),
                'currency' => 'TRY',
                'placed_at' => $placedAt,
                'remote_last_modified_at' => $placedAt,
                'totals' => json_encode($orderTotals),
                'customer' => $encryptedCustomer,
                'raw' => null,
                'created_at' => $placedAt,
                'updated_at' => $placedAt,
            ]);

            foreach ($lines as &$line) {
                $line['order_id'] = $orderId;
            }
            unset($line);

            DB::table('order_lines')->insert($lines);

            DB::table('shipment_packages')->insert([
                'order_id' => $orderId,
                'remote_package_id' => $remoteId,
                'cargo_provider' => $cargo,
                'tracking_number' => $trackingNo,
                'tracking_link' => 'https://kargotakip.example.com/?tracking='.$trackingNo,
                'status' => $selectedStatus,
                'external_status' => ucfirst($selectedStatus),
                'deci' => $deci,
                'shipped_at' => in_array($selectedStatus, ['shipped', 'delivered'], true) ? $placedAt : null,
                'delivered_at' => $selectedStatus === 'delivered' ? $placedAt : null,
                'created_at' => $placedAt,
                'updated_at' => $placedAt,
            ]);
        }
    }
}
