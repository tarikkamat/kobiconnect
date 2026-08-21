<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WarehouseRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $warehouse = $this->route('warehouse');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('warehouses', 'code')->ignore($warehouse),
            ],
            'is_default' => ['boolean'],
            // Adres serbest metin: kargo entegrasyonu gelene kadar yapisal bir
            // adres modeline ihtiyac yok.
            'address.line' => ['nullable', 'string', 'max:255'],
            'address.city' => ['nullable', 'string', 'max:100'],
            'address.district' => ['nullable', 'string', 'max:100'],
            'address.postal_code' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'depo adı',
            'code' => 'depo kodu',
            'address.line' => 'adres',
            'address.city' => 'il',
            'address.district' => 'ilçe',
            'address.postal_code' => 'posta kodu',
        ];
    }
}
