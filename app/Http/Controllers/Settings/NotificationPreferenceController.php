<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Notifications\NotificationChannel;
use App\Notifications\NotificationEvent;
use App\Notifications\NotificationPreferences;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Olay x kanal matrisi — BACKEND-PLAN.md §11.3.
 *
 * Tercih KISIYE ozeldir; baskasinin tercihini goren veya degistiren bir yol
 * yoktur, bu yuzden Policy de yoktur.
 */
class NotificationPreferenceController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();
        abort_if($user === null, 403);

        return Inertia::render('settings/notifications', [
            'events' => array_map(static fn (NotificationEvent $event): array => [
                'value' => $event->value,
                'label' => $event->label(),
                'group' => $event->group(),
            ], NotificationEvent::cases()),
            'channels' => array_map(static fn (NotificationChannel $channel): array => [
                'value' => $channel->value,
                'label' => $channel->label(),
                'available' => $channel->isAvailable(),
            ], NotificationChannel::cases()),
            'preferences' => NotificationPreferences::matrixFor($user),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        // Formdan yalnizca ISARETLI kutular gelir; bir olay hic gelmiyorsa
        // "hicbir kanaldan haber verme" demektir. Bu yuzden matris
        // NotificationPreferences::save() icinde olaylarin TAMAMI uzerinden
        // yeniden yazilir, gelen kismi veri uzerinden degil.
        $input = $request->validate([
            'preferences' => ['nullable', 'array'],
            'preferences.*' => ['array'],
            'preferences.*.*' => ['in:on,1,true'],
        ]);

        /** @var array<string, array<string, mixed>> $submitted */
        $submitted = $input['preferences'] ?? [];

        $matrix = [];

        foreach (NotificationEvent::cases() as $event) {
            $matrix[$event->value] = (array_map(
                'strval',
                array_keys($submitted[$event->value] ?? []),
            ));
        }

        NotificationPreferences::save($user, $matrix);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Bildirim tercihleri güncellendi.']);

        return back();
    }
}
