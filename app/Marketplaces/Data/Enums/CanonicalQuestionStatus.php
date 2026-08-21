<?php

namespace App\Marketplaces\Data\Enums;

/**
 * Marketplace-independent status of a customer question.
 */
enum CanonicalQuestionStatus: string
{
    case AwaitingAnswer = 'awaiting_answer';

    case Answered = 'answered';

    case Reported = 'reported';

    case Rejected = 'rejected';

    case Expired = 'expired';

    /**
     * Whether an answer may still be submitted.
     */
    public function isAnswerable(): bool
    {
        return $this === self::AwaitingAnswer;
    }
}
