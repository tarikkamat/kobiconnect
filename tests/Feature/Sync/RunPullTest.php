<?php

declare(strict_types=1);

use App\Actions\Sync\RunPull;
use App\Enums\ProcessingStatus;
use App\Marketplaces\Data\Enums\CanonicalOrderStatus;
use App\Marketplaces\Data\OrderData;
use App\Marketplaces\Data\PullPage;
use App\Models\ChannelConnection;
use App\Models\SyncCursor;
use App\Models\SyncRun;

beforeEach(function (): void {
    $this->connection = ChannelConnection::factory()->create();
    $this->pull = app(RunPull::class);
});

it('walks every page and moves the watermark to the newest item', function (): void {
    $pages = [
        new PullPage(items: [new stdClass, new stdClass], hasMore: true, cursor: 'p2',
            watermark: new DateTimeImmutable('2026-08-01 10:00:00')),
        new PullPage(items: [new stdClass], hasMore: false, cursor: null,
            watermark: new DateTimeImmutable('2026-08-02 11:30:00')),
    ];

    $seen = [];
    $cursors = [];

    $run = ($this->pull)(
        $this->connection,
        'orders',
        function (?DateTimeImmutable $since, ?string $cursor) use (&$pages, &$cursors): PullPage {
            $cursors[] = $cursor;

            return array_shift($pages);
        },
        function (array $items) use (&$seen): void {
            $seen = [...$seen, ...$items];
        },
    );

    $cursor = SyncCursor::query()->where('resource', 'orders')->sole();

    expect($run->status)->toBe(ProcessingStatus::Completed)
        ->and($run->stats['pages'])->toBe(2)
        ->and($run->stats['items'])->toBe(3)
        ->and($seen)->toHaveCount(3)
        ->and($cursors)->toBe([null, 'p2'])
        ->and($cursor->watermark->format('Y-m-d H:i'))->toBe('2026-08-02 11:30')
        // Nothing left to read, so no half finished cursor is kept.
        ->and($cursor->cursor)->toBeNull();
});

it('resumes from the stored watermark', function (): void {
    SyncCursor::factory()->create([
        'connection_id' => $this->connection->getKey(),
        'resource' => 'orders',
        'watermark' => new DateTimeImmutable('2026-07-01 08:00:00'),
    ]);

    $since = null;

    ($this->pull)(
        $this->connection,
        'orders',
        function (?DateTimeImmutable $watermark) use (&$since): PullPage {
            $since = $watermark;

            return new PullPage([]);
        },
        fn (array $items) => null,
    );

    expect($since?->format('Y-m-d H:i'))->toBe('2026-07-01 08:00');
});

it('records the failure and keeps the watermark where it was', function (): void {
    SyncCursor::factory()->create([
        'connection_id' => $this->connection->getKey(),
        'resource' => 'orders',
        'watermark' => new DateTimeImmutable('2026-07-01 08:00:00'),
    ]);

    expect(fn () => ($this->pull)(
        $this->connection,
        'orders',
        fn (): PullPage => new PullPage(
            items: [new stdClass],
            watermark: new DateTimeImmutable('2026-08-05 09:00:00'),
        ),
        fn (array $items) => throw new RuntimeException('order upsert failed'),
    ))->toThrow(RuntimeException::class);

    $run = SyncRun::query()->sole();
    $cursor = SyncCursor::query()->sole();

    expect($run->status)->toBe(ProcessingStatus::Failed)
        ->and($run->error['message'])->toBe('order upsert failed')
        // At least once: the next run re-reads the same window.
        ->and($cursor->watermark->format('Y-m-d'))->toBe('2026-07-01');
});

it('stops at the page ceiling and says so', function (): void {
    $run = ($this->pull)(
        $this->connection,
        'orders',
        fn (): PullPage => new PullPage(items: [new stdClass], hasMore: true, cursor: 'next'),
        fn (array $items) => null,
        maxPages: 3,
    );

    expect($run->stats['pages'])->toBe(3)
        ->and($run->stats['truncated'])->toBeTrue()
        ->and(SyncCursor::query()->sole()->cursor)->toBe('next');
});

it('is not tied to any marketplace shape', function (): void {
    $order = new OrderData(
        remoteId: 'TY-1',
        remoteOrderNumber: 'TY-1',
        status: CanonicalOrderStatus::Created,
        externalStatus: 'Created',
        placedAt: new DateTimeImmutable('2026-08-01 09:00:00'),
    );

    $run = ($this->pull)(
        $this->connection,
        'orders',
        fn (): PullPage => new PullPage([$order]),
        fn (array $items) => null,
    );

    expect($run->stats['items'])->toBe(1);
});
