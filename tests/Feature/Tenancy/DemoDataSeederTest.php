<?php

declare(strict_types=1);

use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\DB;

it('panelin tüm kartlarını dolduracak örnek veriyi basar', function (): void {
    $this->seed(DemoDataSeeder::class);

    expect(DB::table('channel_connections')->distinct()->count('marketplace'))->toBe(2)
        ->and(DB::table('products')->exists())->toBeTrue()
        ->and(DB::table('orders')->exists())->toBeTrue()
        ->and(DB::table('order_lines')->whereNull('variant_id')->exists())->toBeTrue()
        ->and(DB::table('inventory_items')->whereColumn('available', '<=', 'safety_stock')->exists())->toBeTrue()
        ->and(DB::table('sync_runs')->exists())->toBeTrue();
});

it('veri içeren tenantta ikinci çalıştırmada dokunmaz', function (): void {
    $this->seed(DemoDataSeeder::class);
    $orders = DB::table('orders')->count();

    $this->seed(DemoDataSeeder::class);

    expect(DB::table('orders')->count())->toBe($orders);
});
