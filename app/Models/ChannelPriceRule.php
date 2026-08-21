<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MarkupType;
use App\Enums\RuleScope;
use Database\Factories\ChannelPriceRuleFactory;
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
 * @property MarkupType $markup_type
 * @property string $markup_value
 * @property string|null $round_to
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['connection_id', 'scope_type', 'scope_id', 'markup_type', 'markup_value', 'round_to'])]
class ChannelPriceRule extends Model
{
    /** @use HasFactory<ChannelPriceRuleFactory> */
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
            'markup_type' => MarkupType::class,
            'markup_value' => 'decimal:4',
            'round_to' => 'decimal:2',
        ];
    }
}
