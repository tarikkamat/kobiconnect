<?php

namespace App\Marketplaces\Data;

use DateTimeImmutable;

/**
 * One page of an incremental pull.
 *
 * The cursor is the opaque token the marketplace expects to continue paging;
 * the watermark is what gets persisted so the next run resumes from here.
 *
 * @template TItem of object
 */
final readonly class PullPage
{
    /**
     * @param  list<TItem>  $items
     */
    public function __construct(
        public array $items,
        public bool $hasMore = false,
        public ?string $cursor = null,
        public ?DateTimeImmutable $watermark = null,
    ) {}
}
