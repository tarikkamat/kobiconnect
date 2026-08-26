<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalog;

use App\Enums\DynamicCategoryField;
use App\Enums\DynamicCategoryMatchType;
use App\Enums\DynamicCategoryOperator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DynamicCategoryRequest extends FormRequest
{
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug($this->string('name')->toString()),
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
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('dynamic_categories', 'slug')->ignore($this->route('dynamicCategory') ?? $this->route('dynamic_category')),
            ],
            'match_type' => ['required', Rule::enum(DynamicCategoryMatchType::class)],
            'description' => ['nullable', 'string', 'max:1000'],
            'conditions' => ['nullable', 'array'],
            'conditions.*.field' => ['required', Rule::enum(DynamicCategoryField::class)],
            'conditions.*.operator' => ['required', Rule::enum(DynamicCategoryOperator::class)],
            'conditions.*.value' => ['nullable'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.unique' => 'Bu isimde bir dinamik kategori zaten var.',
        ];
    }
}
