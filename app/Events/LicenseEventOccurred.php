<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\License;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

abstract class LicenseEventOccurred implements LicenseEventContract
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly License $license,
        public readonly array $payload = [],
    ) {}

    public function license(): License
    {
        return $this->license;
    }

    public function eventType(): string
    {
        return Str::snake(class_basename($this));
    }

    /**
     * @return array<string, mixed>
     */
    public function eventPayload(): array
    {
        return $this->payload;
    }
}
