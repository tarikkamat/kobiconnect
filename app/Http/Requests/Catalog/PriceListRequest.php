<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalog;

use App\Enums\AdjustmentType;
use App\Enums\PriceListType;
use App\Enums\PriceRuleField;
use App\Enums\RoundingMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PriceListRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(PriceListType::class)],
            'source_currency' => ['nullable', 'string', 'size:3'],
            'target_currency' => ['required', 'string', 'size:3'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0.000001'],
            'rounding_method' => ['nullable', Rule::enum(RoundingMethod::class)],
            'is_active' => ['boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
            'rules' => ['nullable', 'array'],
            'rules.*.field' => ['required', Rule::enum(PriceRuleField::class)],
            'rules.*.condition_value' => ['nullable'],
            'rules.*.adjustment_type' => ['required', Rule::enum(AdjustmentType::class)],
            'rules.*.adjustment_value' => ['required', 'numeric'],
            'rules.*.position' => ['nullable', 'integer'],
        ];
    }
}
