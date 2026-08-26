<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Notifications\NotificationEvent;
use App\Support\AppTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bildirim merkezi — BACKEND-PLAN.md §11.3, FRONTEND-PLAN.md §1.
 *
 * Bildirim KISIYE ozeldir: her sorgu `$request->user()` uzerinden gider, ekip
 * arkadasinin bildirimi hicbir yoldan gorulemez.
 *
 * ponytail: zil sayaci paylasilan prop'a KONMADI (FRONTEND-PLAN §2 "agir olan
 * hicbir sey buraya konmaz"). Zil bileseni `feed`'i kendi cekiyor — sayfa
 * basina bir XHR, sifir ek yuk her Inertia yanitinda. Gercek zamanli websocket
 * de yok: sayfa gecisinde tazelenmesi bir KOBI paneli icin fazlasiyla yeterli.
 */
class NotificationController extends Controller
{
    /**
     * Zil acilir listesinde gosterilecek en fazla kayit.
     */
    private const int FEED_LIMIT = 8;

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $filters = $request->validate([
            'unread' => ['nullable', 'boolean'],
            'event' => ['nullable', 'string'],
        ]);

        $unreadOnly = (bool) ($filters['unread'] ?? false);
        $event = NotificationEvent::tryFrom((string) ($filters['event'] ?? ''));

        $notifications = $user->notifications()
            ->when($unreadOnly, fn ($query) => $query->whereNull('read_at'))
            ->when($event !== null, fn ($query) => $query->where('data->event', $event?->value))
            ->paginate(25)
            ->withQueryString()
            ->through($this->present(...));

        return Inertia::render('notifications/index', [
            // <InfiniteScroll> icin normalize edilmis sayfalama; bildirim
            // gecmisi uzundur ve sayfa numarasi tiklamaya degmez.
            'notifications' => Inertia::scroll($notifications),
            'filters' => ['unread' => $unreadOnly, 'event' => $event?->value],
            'unreadCount' => $user->unreadNotifications()->count(),
            'events' => array_map(
                static fn (NotificationEvent $case): array => ['value' => $case->value, 'label' => $case->label()],
                NotificationEvent::cases(),
            ),
        ]);
    }

    /**
     * Panel zilini besleyen JSON ucu.
     */
    public function feed(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        return response()->json([
            'unreadCount' => $user->unreadNotifications()->count(),
            'items' => $user->notifications()
                ->latest()
                ->limit(self::FEED_LIMIT)
                ->get()
                ->map($this->present(...))
                ->all(),
        ]);
    }

    /**
     * `id` verilirse tek bildirimi, verilmezse tumunu okundu isaretler.
     */
    public function read(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $id = $request->validate(['id' => ['nullable', 'uuid']])['id'] ?? null;

        $user->unreadNotifications()
            ->when($id !== null, fn ($query) => $query->whereKey($id))
            ->update(['read_at' => now()]);

        $unreadCount = $user->unreadNotifications()->count();

        return $request->hasHeader('X-Inertia')
            ? back()
            : response()->json(['unreadCount' => $unreadCount]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(DatabaseNotification $notification): array
    {
        /** @var array<string, mixed> $data */
        $data = $notification->data;
        $event = NotificationEvent::tryFrom((string) ($data['event'] ?? ''));

        return [
            'id' => $notification->getKey(),
            'event' => $event?->value,
            'eventLabel' => $event?->label(),
            'group' => $event?->group(),
            'title' => (string) ($data['title'] ?? ''),
            'body' => (string) ($data['body'] ?? ''),
            'url' => is_string($data['url'] ?? null) ? $data['url'] : null,
            'read' => $notification->read_at !== null,
            'createdAt' => AppTime::dateTime($notification->created_at),
        ];
    }
}
