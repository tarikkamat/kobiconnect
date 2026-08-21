<?php

namespace App\Http\Middleware;

use App\Models\License;
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
        $license = tenant() === null ? null : License::where('tenant_id', tenant('id'))->first();

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
            'tenant' => tenant() === null ? null : [
                'id' => tenant('id'),
                'host' => $request->getHost(),
            ],
            // Ham enum PAYLASILMAZ: grace period'da `status` hala `active`
            // doner, dolayisiyla arayuz grace durumunu hicbir zaman goremezdi.
            'license' => $license === null ? null : [
                'status' => match (true) {
                    $license->inGracePeriod() => 'grace',
                    ! $license->hasAccess() => 'expired',
                    default => $license->status->value,
                },
                'endsAt' => $license->ends_at?->toIso8601String(),
                'graceDaysLeft' => $license->inGracePeriod() && $license->grace_until !== null
                    ? (int) ceil(now()->diffInDays($license->grace_until, false))
                    : null,
                'readOnly' => $license->isReadOnly(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
