<?php

declare(strict_types=1);

namespace App\Http\Controllers\Onboarding;

use App\Actions\Onboarding\LocateTenantByEmail;
use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\CentralLoginRequest;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ortak giris ekrani: app.kobiconnect.com/login. Kullanicilar tenant semasinda
 * yasar (BACKEND-PLAN.md §4.1) ama musteriden panel adresini bilmesini
 * beklemiyoruz — e-posta + parola hangi tenant oldugunu da soyler.
 *
 * Fortify'in /{tenant}/login route'u yerinde kalir: 2FA dogrulamasi ve derin
 * baglantilar oradan yurur.
 */
final class LoginController extends Controller
{
    public function __construct(private readonly LocateTenantByEmail $locateTenant) {}

    public function create(Request $request): Response
    {
        return Inertia::render('onboarding/login', [
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(CentralLoginRequest $request): RedirectResponse
    {
        $email = (string) $request->validated('email');
        $password = (string) $request->validated('password');
        $remember = $request->boolean('remember');

        $match = ($this->locateTenant)(
            $email,
            fn (User $user): bool => Hash::check($password, (string) $user->password),
        );

        if ($match === null) {
            event(new Failed(config('fortify.guard'), null, ['email' => $email]));

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        [$tenant, $user] = $match;

        // Tek host = tek session cookie. ScopeSessions session'a `_tenant_id`
        // damgasi basar; ziyaretci daha once baska bir tenant'ta gezindiyse o
        // damga hala duruyor olur ve yeni panelde 403'e cevrilir. Oturumu
        // sifirdan kurmak hem bunu hem de session fixation'i cozer.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // 2FA merkezi giriste ATLANMAZ: Fortify'in bekledigi session
        // anahtarlarini yazip dogrulamayi tenant'in challenge ekranina devrediyoruz
        // (bkz. Fortify\Actions\RedirectIfTwoFactorAuthenticatable).
        if ($user->two_factor_secret !== null && $user->two_factor_confirmed_at !== null) {
            $request->session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => $remember,
            ]);

            return redirect()->route('two-factor.login', ['tenant' => $tenant->getTenantKey()]);
        }

        // Tenant baglaminda: remember=true ise SessionGuard remember_token'i
        // kullaniciya YAZAR, bu da tenant semasina gitmek zorundadir.
        $tenant->run(fn () => Auth::login($user, $remember));

        return redirect()->route('dashboard', ['tenant' => $tenant->getTenantKey()]);
    }
}
