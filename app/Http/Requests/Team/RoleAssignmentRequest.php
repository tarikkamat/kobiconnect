<?php

declare(strict_types=1);

namespace App\Http\Requests\Team;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/**
 * Kullanici basina TEK rol. Plandaki bes rol birbirini kapsayan katmanlardir
 * (§4.3); coklu rol ihtiyaci cikarsa burasi diziye acilir.
 */
class RoleAssignmentRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', 'string', Rule::in(Role::query()->pluck('name')->all())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['role' => 'rol'];
    }
}
