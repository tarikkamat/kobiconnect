<?php

declare(strict_types=1);

namespace App\Actions\Sync;

use App\Enums\ProcessingStatus;
use App\Events\NotificationEventOccurred;
use App\Marketplaces\Data\Enums\SyncDirection;
use App\Marketplaces\Data\PullPage;
use App\Models\ChannelConnection;
use App\Models\SyncCursor;
use App\Models\SyncRun;
use App\Notifications\NotificationEvent;
use DateTimeImmutable;
use InvalidArgumentException;
use Throwable;

/**
 * Watermark driven incremental pull - BACKEND-PLAN 7.1.
 *
 * The engine owns the bookkeeping and nothing else: where the last run got to
 * (`sync_cursors`) and what the run did (`sync_runs`, the operator facing
 * truth). What a page *means* belongs to the caller's sink, which is why this
 * takes one - orders, claims and questions all land in different tables while
 * sharing this loop.
 *
 * The watermark advances only after the sink returned. A failed run resumes
 * from where the last good one stopped and re-delivers, because at least once
 * is the only safe direction here.
 *
 * Page fan-out belongs to the driver, which owns the HTTP client, and it is
 * done with `Http::pool()` / `Http::batch()`. `Concurrency::run()` is banned:
 * its fork driver throws inside a web request and its process driver boots the
 * whole framework per task.
 */
final class RunPull
{
    /**
     * @param  callable(?DateTimeImmutable, ?string): PullPage<object>  $fetch  (updatedSince, cursor) => page
     * @param  callable(list<object>): void  $sink
     */
    public function __invoke(
        ChannelConnection $connection,
        string $resource,
        callable $fetch,
        callable $sink,
        int $maxPages = 50,
    ): SyncRun {
        if ($maxPages < 1) {
            throw new InvalidArgumentException('A pull needs at least one page.');
        }

        $cursor = SyncCursor::query()->firstOrNew([
            'connection_id' => $connection->getKey(),
            'resource' => $resource,
        ]);

        $since = $cursor->watermark?->toDateTimeImmutable();
        $watermark = $since;
        $token = $cursor->cursor;
        $pages = 0;
        $items = 0;

        $run = SyncRun::create([
            'connection_id' => $connection->getKey(),
            'resource' => $resource,
            'direction' => SyncDirection::Pull,
            'cursor_from' => $since?->format(DATE_ATOM),
            'started_at' => now(),
            'stats' => [],
            'status' => ProcessingStatus::Running,
        ]);

        try {
            do {
                $page = $fetch($since, $token);

                $sink($page->items);

                $items += count($page->items);
                $pages++;
                $token = $page->cursor;

                if ($page->watermark !== null && ($watermark === null || $page->watermark > $watermark)) {
                    $watermark = $page->watermark;
                }
            } while ($page->hasMore && $pages < $maxPages);
        } catch (Throwable $exception) {
            $run->update([
                'status' => ProcessingStatus::Failed,
                'finished_at' => now(),
                'stats' => ['pages' => $pages, 'items' => $items],
                'error' => ['class' => $exception::class, 'message' => $exception->getMessage()],
            ]);

            NotificationEventOccurred::dispatch(NotificationEvent::SyncFailed, [
                'connection_id' => (string) $connection->getKey(),
                'connection' => $connection->name,
                'reason' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $cursor->fill([
            'watermark' => $watermark,
            // A cursor is only worth keeping while there is more to read.
            'cursor' => $page->hasMore ? $token : null,
        ])->save();

        $run->update([
            'status' => ProcessingStatus::Completed,
            'finished_at' => now(),
            'cursor_to' => $watermark?->format(DATE_ATOM),
            'stats' => ['pages' => $pages, 'items' => $items, 'truncated' => $page->hasMore],
        ]);

        return $run;
    }
}
