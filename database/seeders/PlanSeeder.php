<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\BillingPeriod;
use App\Models\Plan;
use App\Models\PlanFeature;
use Illuminate\Database\Seeder;

/**
 * Baslangic plan seti. `limits` sekli BACKEND-PLAN.md §3.1'deki ornekle aynidir;
 * lisans aktivasyonunda bu harita `licenses.limits`'e kopyalanir ve oradan
 * Pennant bayraklarina yazilir.
 */
class PlanSeeder extends Seeder
{
    /**
     * @var array<string, array{name: string, price: float, features: array<string, mixed>}>
     */
    private const PLANS = [
        'starter' => [
            'name' => 'Başlangıç',
            'price' => 199.00,
            'features' => [
                'channels.max' => 1,
                'channels.allowed' => ['trendyol'],
                'products.max' => 1000,
                'orders.per_month' => 500,
                'seats.max' => 2,
                'sync.interval_minutes' => 60,
            ],
        ],
        'professional' => [
            'name' => 'Profesyonel',
            'price' => 599.00,
            'features' => [
                'channels.max' => 3,
                'channels.allowed' => ['trendyol', 'hepsiburada'],
                'products.max' => 10000,
                'orders.per_month' => 5000,
                'seats.max' => 5,
                'sync.interval_minutes' => 15,
            ],
        ],
        'enterprise' => [
            'name' => 'Kurumsal',
            'price' => 1999.00,
            'features' => [
                'channels.max' => 10,
                'channels.allowed' => ['trendyol', 'hepsiburada'],
                'products.max' => 100000,
                'orders.per_month' => 50000,
                'seats.max' => 25,
                'sync.interval_minutes' => 5,
            ],
        ],
    ];

    public function run(): void
    {
        foreach (self::PLANS as $code => $plan) {
            $model = Plan::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $plan['name'],
                    'price' => $plan['price'],
                    'billing_period' => BillingPeriod::Monthly,
                    'is_public' => true,
                ],
            );

            foreach ($plan['features'] as $feature => $value) {
                PlanFeature::updateOrCreate(
                    ['plan_id' => $model->getKey(), 'feature' => $feature],
                    ['value' => $value],
                );
            }
        }
    }
}
