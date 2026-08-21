<?php

declare(strict_types=1);

namespace App\Models;

use App\Marketplaces\Data\Enums\CanonicalClaimStatus;
use Database\Factories\ClaimItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $claim_id
 * @property int|null $order_line_id
 * @property string $remote_item_id
 * @property int $quantity
 * @property CanonicalClaimStatus $status
 * @property string $external_status
 * @property string|null $reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'claim_id', 'order_line_id', 'remote_item_id', 'quantity', 'status', 'external_status', 'reason',
])]
class ClaimItem extends Model
{
    /** @use HasFactory<ClaimItemFactory> */
    use HasFactory;

    /** @return BelongsTo<Claim, $this> */
    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class);
    }

    /**
     * Bilerek nullable: pazaryeri bizde karsiligi olmayan bir kaleme talep
     * acabilir; talep yine de kaydedilir.
     *
     * @return BelongsTo<OrderLine, $this>
     */
    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(OrderLine::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => CanonicalClaimStatus::class];
    }
}
