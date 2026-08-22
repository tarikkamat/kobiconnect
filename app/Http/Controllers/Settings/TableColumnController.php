<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Tablo kolon görünürlüğü — kişiye özel tercih, ekran değil.
 *
 * İstemcideki kolon seçici her değişiklikte buraya yazar; okuma tarafı yoktur,
 * tercihler `HandleInertiaRequests` ile her sayfada paylaşılır. Tercih KİŞİYE
 * özeldir; başkasının tercihini gören veya değiştiren bir yol yoktur, bu
 * yüzden Policy de yoktur (bkz. NotificationPreferenceController).
 */
class TableColumnController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        /** @var array{table: string, hidden?: list<string>} $input */
        $input = $request->validate([
            'table' => ['required', 'string', 'max:64'],
            'hidden' => ['nullable', 'array', 'max:64'],
            'hidden.*' => ['string', 'max:64'],
        ]);

        $preferences = is_array($user->table_preferences) ? $user->table_preferences : [];
        $hidden = array_values(array_unique($input['hidden'] ?? []));

        if ($hidden === []) {
            // "Hepsi gorunur" varsayilandir; anahtari saklamak bosuna.
            unset($preferences[$input['table']]);
        } else {
            $preferences[$input['table']] = ['hidden' => $hidden];
        }

        $user->forceFill(['table_preferences' => $preferences])->save();

        return back();
    }
}
