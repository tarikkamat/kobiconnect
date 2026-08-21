<?php

declare(strict_types=1);

namespace App\Http\Controllers\Onboarding;

use App\Actions\Onboarding\LocateTenantByEmail;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Actions\CompletePasswordReset;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

/**
 * Parola sifirlama da ortak: baglanti app.kobiconnect.com/reset-password/{token}
 * adresine gider, panel adresi sorulmaz. Token'lar tenant semasindaki
 * `password_reset_tokens` tablosunda yasar, bu yuzden her adimda once
 * e-postadan tenant bulunur, is o tenant'in baglaminda yapilir.
 */
final class PasswordResetController extends Controller
{
    public function __construct(private readonly LocateTenantByEmail $locateTenant) {}

    public function create(Request $request): Response
    {
        return Inertia::render('onboarding/forgot-password', [
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $email = $this->normalizedEmail($request);

        $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);

        $match = ($this->locateTenant)($email);

        if ($match !== null) {
            [$tenant] = $match;

            $tenant->run(fn () => $this->broker()->sendResetLink(['email' => $email]));
        }

        // Kullanici numaralandirmasina kapali: e-posta kayitli olsun ya da
        // olmasin ayni yanit doner, bilgi yalnizca posta kutusuna gider.
        return redirect()->route('central.password.request')->with(
            'status',
            'Bu e-posta ile kayitli bir hesap varsa sifirlama baglantisini gonderdik. Gelen kutunuzu kontrol edin.',
        );
    }

    public function edit(Request $request, string $token): Response
    {
        return Inertia::render('onboarding/reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
            'passwordRules' => PasswordRule::defaults()->toPasswordRulesString(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $email = $this->normalizedEmail($request);

        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            // Ayrintili parola kurallari ResetUserPassword icinde — tek kaynak.
            'password' => ['required', 'string'],
        ]);

        $match = ($this->locateTenant)($email);

        // Token dogrulamasi ve parolanin yazilmasi tenant semasinda olmali.
        $status = $match === null
            ? Password::INVALID_USER
            : $match[0]->run(fn (): string => $this->broker()->reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function ($user) use ($request): void {
                    app(ResetsUserPasswords::class)->reset($user, $request->all());

                    app(CompletePasswordReset::class)($this->guard(), $user);
                },
            ));

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        return redirect()->route('central.login')->with(
            'status',
            'Parolaniz guncellendi, yeni parolanizla giris yapabilirsiniz.',
        );
    }

    private function guard(): StatefulGuard
    {
        /** @var StatefulGuard $guard */
        $guard = auth()->guard(config('fortify.guard'));

        return $guard;
    }

    private function broker(): PasswordBroker
    {
        return Password::broker(config('fortify.passwords'));
    }

    private function normalizedEmail(Request $request): string
    {
        $email = mb_strtolower(trim((string) $request->input('email')));

        $request->merge(['email' => $email]);

        return $email;
    }
}
