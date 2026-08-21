<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BillingPeriod;
use App\Models\Plan;
use App\Models\PlanFeature;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(2),
            'name' => fake()->unique()->words(2, true),
            'price' => fake()->randomFloat(2, 0, 5000),
            'billing_period' => BillingPeriod::Monthly,
            'is_public' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $features
     */
    public function withFeatures(array $features): static
    {
        return $this->afterCreating(function (Plan $plan) use ($features): void {
            foreach ($features as $feature => $value) {
                PlanFeature::create([
                    'plan_id' => $plan->getKey(),
                    'feature' => $feature,
                    'value' => $value,
                ]);
            }

            $plan->unsetRelation('features');
        });
    }
}
