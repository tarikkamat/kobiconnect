<?php

declare(strict_types=1);

namespace App\Http\Requests\Onboarding;

use App\Concerns\PasswordValidationRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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
            // Plan kayit ekraninda SORULMAZ; en ucuz halka acik plan atanir
            // (bkz. prepareForValidation). Kural emniyet agi olarak duruyor.
            'plan' => [
                'required',
                'string',
                Rule::exists('central.plans', 'code')->where('is_public', true),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'plan' => $this->defaultPlanCode(),
        ]);
    }

    /**
     * Kayit sirasinda plan sectirmiyoruz: herkes en ucuz halka acik planla
     * baslar, yukseltme faturalama ekranindan yapilir.
     */
    private function defaultPlanCode(): ?string
    {
        return DB::connection('central')->table('plans')
            ->where('is_public', true)
            ->orderBy('price')
            ->value('code');
    }
}
