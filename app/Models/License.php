<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LicenseStatus;
use Carbon\CarbonInterface;
use Database\Factories\LicenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Feature;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Bir tenant = bir lisans (BACKEND-PLAN.md §3).
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $plan_id
 * @property LicenseStatus $status
 * @property int $seats
 * @property Collection<string, mixed> $limits
 * @property CarbonInterface $starts_at
 * @property CarbonInterface|null $ends_at
 * @property CarbonInterface|null $grace_until
 * @property CarbonInterface|null $cancelled_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Plan $plan
 * @property-read Tenant $tenant
 */
#[Fillable(['tenant_id', 'plan_id', 'status', 'seats', 'limits', 'starts_at', 'ends_at', 'grace_until', 'cancelled_at'])]
class License extends Model
{
    use CentralConnection;

    /** @use HasFactory<LicenseFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return HasMany<LicenseEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(LicenseEvent::class);
    }

    /**
     * Suresi dolmamis, askiya alinmamis lisans.
     */
    public function isActive(): bool
    {
        return $this->status === LicenseStatus::Active
            && ($this->ends_at === null || $this->ends_at->isFuture());
    }

    /**
     * Suresi doldu ama odeme bekleme penceresi henuz kapanmadi.
     */
    public function inGracePeriod(): bool
    {
        return $this->status === LicenseStatus::Active
            && $this->ends_at !== null && $this->ends_at->isPast()
            && $this->grace_until !== null && $this->grace_until->isFuture();
    }

    /**
     * Panele girebilir mi? Grace period dahil.
     */
    public function hasAccess(): bool
    {
        return $this->isActive() || $this->inGracePeriod();
    }

    /**
     * Grace period'da okuma serbest, yazma yasak — §3.2.
     */
    public function isReadOnly(): bool
    {
        return $this->inGracePeriod();
    }

    /**
     * Sayisal kota. Anahtar yoksa veya sayisal degilse limitsiz demektir.
     */
    public function limit(string $key): ?int
    {
        $value = $this->limits->get($key);

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * Pennant'a yazilacak bayrak haritasi.
     *
     * ponytail: `channels.allowed` listesi ayrica `channel.{kod}` gate'lerine
     * acilir — §3.1 limits sekli ile §3.2'deki kullanim ayni kaynaktan beslenir.
     *
     * @return array<string, mixed>
     */
    public function featureFlags(): array
    {
        $flags = $this->limits->all();

        $allowed = $flags['channels.allowed'] ?? [];

        if (is_array($allowed)) {
            foreach ($allowed as $code) {
                $flags['channel.'.$code] = true;
            }
        }

        return $flags;
    }

    /**
     * Lisans limitlerini tenant scope'lu Pennant bayraklarina yazar.
     */
    public function syncFeatures(): void
    {
        $tenant = $this->tenant;

        Feature::flushCache();

        // ponytail: Pennant'ta "bu scope'un tum bayraklarini unut" API'si yok.
        // Plan dususunde eski bayraklar acik kalmasin diye satirlari siliyoruz.
        DB::connection((string) config('pennant.stores.database.connection'))
            ->table((string) config('pennant.stores.database.table'))
            ->where('scope', Feature::serializeScope($tenant))
            ->delete();

        foreach ($this->featureFlags() as $feature => $value) {
            Feature::for($tenant)->activate($feature, $value);
        }
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LicenseStatus::class,
            'limits' => AsCollection::class,
            'seats' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'grace_until' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
