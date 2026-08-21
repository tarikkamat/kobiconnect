<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalog;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Toplu fiyat/stok degisikligi. Ayni kurallar hem onizleme hem uygulama
 * ucunda gecerli — onizlemede gecen bir istek uygulamada patlamamali.
 */
class ProductBulkEditRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_ids' => ['required', 'array', 'min:1', 'max:500'],
            'product_ids.*' => ['integer', Rule::exists('products', 'id')],
            'field' => ['required', 'in:price,stock'],
            // set: mutlak deger · percent: yuzde degisim · amount: sabit degisim.
            // percent/amount NEGATIF olabilir (indirim / stok dusumu).
            'mode' => ['required', 'in:set,percent,amount'],
            'value' => ['required', 'numeric', 'between:-1000000,1000000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'product_ids' => 'seçili ürünler',
            'field' => 'alan',
            'mode' => 'değişim türü',
            'value' => 'değer',
        ];
    }
}
