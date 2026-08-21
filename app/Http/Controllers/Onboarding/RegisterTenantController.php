<?php

declare(strict_types=1);

namespace App\Http\Controllers\Onboarding;

use App\Actions\Onboarding\RegisterTenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\RegisterTenantRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Central kayit ekrani. Uygulamada kullanici yaratabilecek TEK central akis
 * budur — geri kalan her sey tenant path'i altinda yasar (BACKEND-PLAN.md §4.1).
 */
final class RegisterTenantController extends Controller
{
    public function create(): Response
    {
        // Plan sectirmiyoruz — kayit formu kisa kalsin. Herkes en ucuz halka
        // acik planla baslar (RegisterTenantRequest::defaultPlanCode).
        return Inertia::render('onboarding/register', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function store(RegisterTenantRequest $request, RegisterTenant $registerTenant): RedirectResponse
    {
        /** @var array{company: string, name: string, email: string, password: string, plan: string} $input */
        $input = $request->safe()->only(['company', 'name', 'email', 'password', 'plan']);

        [$owner, $tenant] = $registerTenant($input);

        // Tek host = tek session cookie. ScopeSessions session'a `_tenant_id`
        // damgasi basar; ziyaretci daha once baska bir tenant'ta gezindiyse o
        // damga hala duruyor olur ve yeni panelde 403'e cevrilir. Oturumu
        // sifirdan kurmak hem bunu hem de session fixation'i cozer.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Kullanici nesnesi tenant baglaminda cozuldu; login() yalnizca
        // session'a id yazar, bu yuzden central baglamda cagrilmasi guvenli.
        Auth::login($owner);

        return redirect()->route('dashboard', ['tenant' => $tenant->getTenantKey()]);
    }
}
