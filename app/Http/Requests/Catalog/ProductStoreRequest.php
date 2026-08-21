<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalog;

use App\Enums\ProductStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductStoreRequest extends FormRequest
{
    /**
     * Bir urun EN AZ bir varyantla dogar: stok, fiyat ve pazaryeri listelemesi
     * varyanta baglidir, varyantsiz urun hicbir yere gonderilemez.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'status' => ['required', Rule::enum(ProductStatus::class)],

            'variants' => ['required', 'array', 'min:1', 'max:50'],
            // `distinct` ayni formdaki tekrari yakalar, `unique` veritabanindakini.
            'variants.*.sku' => ['required', 'string', 'max:255', 'distinct', Rule::unique('product_variants', 'sku')],
            'variants.*.barcode' => ['nullable', 'string', 'max:40', 'distinct', Rule::unique('product_variants', 'barcode')],
            'variants.*.list_price' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'variants.*.on_hand' => ['nullable', 'integer', 'min:0', 'max:100000000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'ürün adı',
            'variants' => 'varyantlar',
            'variants.*.sku' => 'SKU',
            'variants.*.barcode' => 'barkod',
            'variants.*.list_price' => 'liste fiyatı',
            'variants.*.on_hand' => 'stok',
        ];
    }
}
