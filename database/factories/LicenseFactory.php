<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Actions\Licensing\ActivateLicense;
use App\Enums\LicenseStatus;
use App\Models\License;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<License>
 */
class LicenseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $endsAt = now()->addMonth();

        return [
            'tenant_id' => fn (): string => Tenant::create()->getTenantKey(),
            'plan_id' => Plan::factory(),
            'status' => LicenseStatus::Active,
            'seats' => 1,
            'limits' => [],
            'starts_at' => now(),
            'ends_at' => $endsAt,
            'grace_until' => $endsAt->addDays(ActivateLicense::GRACE_DAYS),
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(['tenant_id' => $tenant->getTenantKey()]);
    }

    /**
     * Suresi dolmus ama grace period devam ediyor — salt-okunur mod.
     */
    public function inGracePeriod(): static
    {
        return $this->state([
            'status' => LicenseStatus::Active,
            'ends_at' => now()->subDay(),
            'grace_until' => now()->addDays(7),
        ]);
    }

    /**
     * Grace period da bitmis.
     */
    public function expired(): static
    {
        return $this->state([
            'status' => LicenseStatus::Active,
            'ends_at' => now()->subMonth(),
            'grace_until' => now()->subDays(7),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(['status' => LicenseStatus::Suspended]);
    }
}
