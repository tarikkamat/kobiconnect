<?php

declare(strict_types=1);

namespace App\Http\Controllers\Team;

use App\Actions\Team\InviteUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Team\InviteUserRequest;
use App\Http\Requests\Team\RoleAssignmentRequest;
use App\Models\License;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * Ekip & roller — BACKEND-PLAN.md §4.3.
 *
 * Iki kural sunucuda zorlanir:
 *  1. Koltuk limiti lisanstan gelir (`seats.max`) ve kota kapisi Action
 *     seviyesindedir (§3.2) — bkz. App\Actions\Team\InviteUser.
 *  2. Son Sahip'in rolu alinamaz. Tenant sahipsiz kalirsa faturalama, plan
 *     yukseltme ve tenant silme yetkisi olan kimse kalmaz; bu durumdan
 *     arayuzden donus yoktur.
 */
class TeamController extends Controller
{
    private const string OWNER_ROLE = 'Sahip';

    public function __construct(private readonly InviteUser $inviteUser) {}

    public function index(): Response
    {
        Gate::authorize('viewAny', User::class);

        $license = $this->license();
        $current = request()->user();

        return Inertia::render('settings/team/index', [
            'members' => User::query()
                ->with('roles:id,name')
                ->orderBy('name')
                ->get()
                ->map(fn (User $user): array => [
                    'id' => $user->getKey(),
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->roles->pluck('name')->all(),
                    // Rolu olmayan kullanici "devre disi": hicbir sey yapamaz
                    // ve koltuk tuketmez.
                    'active' => $user->roles->isNotEmpty(),
                    'isSelf' => $current !== null && $user->is($current),
                    'joinedAt' => $user->created_at?->timezone('Europe/Istanbul')->format('d.m.Y'),
                ])
                ->all(),
            'roles' => Role::query()->orderBy('id')->pluck('name')->all(),
            'ownerRole' => self::OWNER_ROLE,
            'ownerCount' => $this->ownerCount(),
            'seats' => [
                // ponytail: sayac degil, canli sayim — `usage_counters` koltuk
                // satiri kayit akisinda artirilmadigi icin ekranda 0 gosterirdi.
                // Kota kapisi (InviteUser) sayaci gerceğe esitleyip oyle sorar.
                'used' => InviteUser::occupiedSeats(),
                'max' => $license?->limit(InviteUser::SEAT_METRIC),
            ],
        ]);
    }

    public function store(InviteUserRequest $request): RedirectResponse
    {
        Gate::authorize('create', User::class);

        $license = $this->license();

        if ($license === null) {
            throw ValidationException::withMessages([
                'email' => 'Aktif bir lisans olmadan kullanıcı eklenemez.',
            ]);
        }

        ($this->inviteUser)(
            $license,
            (string) $request->validated('name'),
            (string) $request->validated('email'),
            (string) $request->validated('role'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Davet gönderildi. Kullanıcı e-postasındaki bağlantıdan şifresini belirleyecek.',
        ]);

        return back();
    }

    public function update(RoleAssignmentRequest $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $role = (string) $request->validated('role');

        $this->guardLastOwner($user, $role);

        $user->syncRoles([$role]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Rol güncellendi.']);

        return back();
    }

    /**
     * Kullaniciyi devre disi birakir.
     *
     * ponytail: satir SILINMEZ, rolleri kaldirilir. Rolsuz kullanicinin hicbir
     * izni yoktur, her Policy onu reddeder ve koltugu serbest kalir; islem geri
     * alinabilir (rol atayarak). Girisin de engellenmesi gerekirse
     * `users.disabled_at` sutunu + auth middleware'inde bir kontrol gerekir —
     * ikisi de bu is kumesinin disindaki dosyalarda (rapora bakin).
     */
    public function destroy(User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);

        $this->guardLastOwner($user, null);

        $user->syncRoles([]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Kullanıcının erişimi kaldırıldı. Rol atayarak geri açabilirsiniz.',
        ]);

        return back();
    }

    /**
     * Tenant sahipsiz kalamaz. 403 degil dogrulama hatasi doner: bu bir yetki
     * eksikligi degil, sistemin izin vermedigi bir son durum.
     */
    private function guardLastOwner(User $user, ?string $newRole): void
    {
        if ($newRole === self::OWNER_ROLE || ! $user->hasRole(self::OWNER_ROLE)) {
            return;
        }

        if ($this->ownerCount() > 1) {
            return;
        }

        throw ValidationException::withMessages([
            'role' => 'Son "Sahip" rolü kaldırılamaz: hesabın sahipsiz kalması faturalama ve plan yönetimini kalıcı olarak kilitler. Önce başka bir kullanıcıyı Sahip yapın.',
        ]);
    }

    private function ownerCount(): int
    {
        return User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', self::OWNER_ROLE))
            ->count();
    }

    private function license(): ?License
    {
        $tenant = tenant();

        return $tenant === null
            ? null
            : License::query()->with('plan')->where('tenant_id', $tenant->getTenantKey())->first();
    }
}
