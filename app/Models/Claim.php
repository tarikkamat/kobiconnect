<?php

declare(strict_types=1);

namespace App\Models;

use App\Marketplaces\Data\Enums\CanonicalClaimStatus;
use Database\Factories\ClaimFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsEncryptedArrayObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Bir iade talebi — BACKEND-PLAN.md §5.3.
 *
 * Bu faz yalnizca GORUNURLUK: onay/red pazaryerine yazma demektir ve outbox
 * motoruna baglidir, MVP kapsaminda degildir.
 *
 * @property int $id
 * @property int $order_id
 * @property string $remote_claim_id
 * @property CanonicalClaimStatus $status
 * @property string $external_status
 * @property string|null $reason
 * @property Carbon $opened_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'order_id', 'remote_claim_id', 'status', 'external_status', 'reason', 'opened_at', 'raw',
])]
class Claim extends Model
{
    /** @use HasFactory<ClaimFactory> */
    use HasFactory;

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return HasMany<ClaimItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ClaimItem::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CanonicalClaimStatus::class,
            // KVKK — orders.raw ile ayni yukumluluk (BACKEND-PLAN.md §13).
            'raw' => AsEncryptedArrayObject::class,
            'opened_at' => 'datetime',
        ];
    }
}
