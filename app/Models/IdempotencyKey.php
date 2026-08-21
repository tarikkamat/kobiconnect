<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\IdempotencyKeyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Replay guard for client supplied `Idempotency-Key` headers. It lives in the
 * tenant schema, so no tenant column is needed.
 *
 * @property string $key
 * @property int|null $user_id
 * @property string $endpoint
 * @property string $request_hash
 * @property int|null $response_status
 * @property array<string, mixed>|null $response_body
 * @property Carbon|null $locked_at
 * @property Carbon $expires_at
 */
#[Fillable([
    'key', 'user_id', 'endpoint', 'request_hash', 'response_status',
    'response_body', 'locked_at', 'expires_at',
])]
#[WithoutTimestamps]
class IdempotencyKey extends Model
{
    /** @use HasFactory<IdempotencyKeyFactory> */
    use HasFactory;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'response_body' => 'array',
            'locked_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
