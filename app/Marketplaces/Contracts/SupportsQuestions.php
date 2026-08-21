<?php

namespace App\Marketplaces\Contracts;

use App\Marketplaces\Data\MappingContext;
use App\Marketplaces\Data\PullPage;
use App\Marketplaces\Data\PushResult;
use App\Marketplaces\Data\QuestionData;
use DateTimeImmutable;

interface SupportsQuestions
{
    /**
     * @return PullPage<QuestionData>
     */
    public function pullQuestions(?DateTimeImmutable $updatedSince = null, ?string $cursor = null): PullPage;

    public function answerQuestion(QuestionData $question, string $answer, MappingContext $context): PushResult;
}
