<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BillingPeriod;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $price
 * @property BillingPeriod $billing_period
 * @property bool $is_public
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, PlanFeature> $features
 */
#[Fillable(['code', 'name', 'price', 'billing_period', 'is_public'])]
class Plan extends Model
{
    use CentralConnection;

    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    /**
     * @return HasMany<PlanFeature, $this>
     */
    public function features(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }

    /**
     * Planin tum feature/limit haritasi: `['products.max' => 10000, ...]`.
     *
     * @return array<string, mixed>
     */
    public function featureMap(): array
    {
        return $this->features->pluck('value', 'feature')->all();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'billing_period' => BillingPeriod::class,
            'is_public' => 'boolean',
        ];
    }
}
