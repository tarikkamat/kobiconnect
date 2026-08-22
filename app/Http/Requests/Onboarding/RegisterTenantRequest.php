<?php

declare(strict_types=1);

namespace App\Http\Requests\Onboarding;

use App\Concerns\PasswordValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class RegisterTenantRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            // Benzersizlik kontrolu yok: tenant semasi yepyeni, icinde kullanici
            // yok. Ayni e-posta baska bir tenant'ta bulunabilir, bu normaldir.
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => $this->passwordRules(),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
        ]);
    }
}
