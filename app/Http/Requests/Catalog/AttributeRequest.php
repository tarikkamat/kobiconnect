<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalog;

use App\Enums\AttributeType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AttributeRequest extends FormRequest
{
    /**
     * Kod verilmemişse isimden türetilir, boolean alanlar normalize edilir.
     */
    protected function prepareForValidation(): void
    {
        $code = $this->string('code')->trim()->toString();
        if ($code === '') {
            $code = Str::slug($this->string('name')->toString());
        }

        $this->merge([
            'code' => $code,
            'is_variant_defining' => $this->boolean('is_variant_defining'),
            'type' => $this->input('type') ?? AttributeType::Select->value,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('attributes', 'code')->ignore($this->route('attribute')),
            ],
            'type' => ['required', Rule::enum(AttributeType::class)],
            'is_variant_defining' => ['boolean'],
            'values' => ['nullable', 'array'],
            'values.*' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.unique' => 'Bu koda sahip bir nitelik zaten var.',
        ];
    }
}
