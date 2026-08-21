<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConnectionStatus;
use Database\Factories\ChannelConnectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\ArrayObject;
use Illuminate\Database\Eloquent\Casts\AsEncryptedArrayObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * `marketplace` stays an untyped string on purpose: the canonical marketplace
 * enum belongs to app/Marketplaces and is added as a cast from there.
 *
 * @property int $id
 * @property string $marketplace
 * @property string $name
 * @property ArrayObject<string, mixed> $credentials
 * @property string|null $external_seller_id
 * @property ConnectionStatus $status
 * @property array<string, mixed> $settings
 * @property array<string, mixed> $field_overrides
 * @property string $webhook_token
 * @property Carbon|null $last_health_check_at
 * @property array<string, mixed> $capabilities
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'marketplace', 'name', 'credentials', 'external_seller_id', 'status',
    'settings', 'field_overrides', 'webhook_token', 'last_health_check_at', 'capabilities',
])]
#[Hidden(['credentials', 'webhook_token'])]
class ChannelConnection extends Model
{
    /** @use HasFactory<ChannelConnectionFactory> */
    use HasFactory;

    /** @return HasMany<ChannelListing, $this> */
    public function listings(): HasMany
    {
        return $this->hasMany(ChannelListing::class, 'connection_id');
    }

    /** @return HasMany<ChannelOperation, $this> */
    public function operations(): HasMany
    {
        return $this->hasMany(ChannelOperation::class, 'connection_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'credentials' => AsEncryptedArrayObject::class,
            'status' => ConnectionStatus::class,
            'settings' => 'array',
            'field_overrides' => 'array',
            'capabilities' => 'array',
            'last_health_check_at' => 'datetime',
        ];
    }
}
