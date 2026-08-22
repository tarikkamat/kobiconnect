<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
            ],
            // Yetkiler UI'da aksiyonu gizlemek icin degil, DEVRE DISI birakmak
            // icin kullanilir — BACKEND-PLAN.md §4.3.
            // `?->` sart: bu middleware central route'larda da calisir ve orada
            // users/roles tablosu yoktur.
            'permissions' => $user?->getAllPermissions()->pluck('name')->all() ?? [],
            'roles' => $user?->getRoleNames()->all() ?? [],
            // Kisiye ozel tablo kolon gorunurlugu; bos dizi yerine obje ki
            // istemcide Record<string, ...> olarak tip guvenli okunsun.
            'tablePreferences' => (object) ($user?->table_preferences ?? []),
            'tenant' => tenant() === null ? null : [
                'id' => tenant('id'),
                'host' => $request->getHost(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
