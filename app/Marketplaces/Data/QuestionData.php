<?php

namespace App\Marketplaces\Data;

use App\Marketplaces\Data\Enums\CanonicalQuestionStatus;
use DateTimeImmutable;

/**
 * A customer question about a listing.
 */
final readonly class QuestionData
{
    public function __construct(
        public string $remoteId,
        public string $body,
        public CanonicalQuestionStatus $status,
        public string $externalStatus,
        public DateTimeImmutable $askedAt,
        public ?string $productRemoteId = null,
        public ?string $answer = null,
        public ?DateTimeImmutable $answeredAt = null,
    ) {}
}
