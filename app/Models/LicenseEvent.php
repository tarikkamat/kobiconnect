<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Lisans denetim izi — §3.1. Yalnizca eklenir, guncellenmez.
 *
 * @property int $id
 * @property int $license_id
 * @property string $type
 * @property array<string, mixed> $payload
 * @property CarbonInterface $created_at
 * @property-read License $license
 */
#[Fillable(['license_id', 'type', 'payload'])]
class LicenseEvent extends Model
{
    use CentralConnection;

    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<License, $this>
     */
    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
