<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AllocationType;
use App\Enums\RuleScope;
use Database\Factories\ChannelStockRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $connection_id
 * @property RuleScope $scope_type
 * @property int|null $scope_id
 * @property AllocationType $allocation_type
 * @property string|null $allocation_value
 * @property int $buffer
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['connection_id', 'scope_type', 'scope_id', 'allocation_type', 'allocation_value', 'buffer'])]
class ChannelStockRule extends Model
{
    /** @use HasFactory<ChannelStockRuleFactory> */
    use HasFactory;

    /** @return BelongsTo<ChannelConnection, $this> */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(ChannelConnection::class, 'connection_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope_type' => RuleScope::class,
            'allocation_type' => AllocationType::class,
            'allocation_value' => 'decimal:4',
        ];
    }
}
