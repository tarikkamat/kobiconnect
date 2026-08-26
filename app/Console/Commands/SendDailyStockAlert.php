<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\Digest\DailyStockAlert;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use stdClass;

final class SendDailyStockAlert extends Command
{
    protected $signature = 'email:daily-stock-alert';

    protected $description = 'Kritik stok seviyesindeki ürünleri yetkili kullanıcılara bildirir.';

    public function handle(): int
    {
        // Kimlikleri geciyoruz, modelleri degil — SyncCommand ile ayni idiom.
        $tenants = Tenant::query()->pluck('id')->map(strval(...));

        tenancy()->runForMultiple($tenants, function (): void {
            rescue(fn () => $this->sendForCurrentTenant());
        });

        return self::SUCCESS;
    }

    private function sendForCurrentTenant(): void
    {
        $query = DB::table('inventory_items')
            ->join('product_variants', 'product_variants.id', '=', 'inventory_items.variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->join('warehouses', 'warehouses.id', '=', 'inventory_items.warehouse_id')
            ->whereColumn('inventory_items.available', '<=', 'inventory_items.safety_stock')
            ->where('inventory_items.safety_stock', '>', 0);

        $count = $query->count();

        if ($count === 0) {
            return;
        }

        $items = array_values($query->select(
            'product_variants.sku',
            'products.name as product_name',
            'product_variants.options',
            'inventory_items.available',
            'inventory_items.safety_stock',
            'warehouses.name as warehouse_name'
        )
            ->limit(20)
            ->get()
            ->map(function (stdClass $row): array {
                $options = json_decode((string) $row->options, true);
                $name = $row->product_name;
                if (is_array($options) && count($options) > 0) {
                    $name .= ' ('.implode(', ', array_column($options, 'value')).')';
                }

                return [
                    'sku' => (string) $row->sku,
                    'name' => (string) $name,
                    'available' => (int) $row->available,
                    'safetyStock' => (int) $row->safety_stock,
                    'warehouse' => (string) $row->warehouse_name,
                ];
            })
            ->all());

        $data = [
            'count' => $count,
            'items' => $items,
        ];

        $recipients = User::query()
            ->whereHas('roles.permissions', function ($query) {
                $query->where('name', 'stock.manage');
            })
            ->get();

        foreach ($recipients as $user) {
            Mail::to($user)->queue(new DailyStockAlert($data));
        }
    }
}
