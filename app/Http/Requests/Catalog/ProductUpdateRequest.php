<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalog;

use App\Enums\ProductStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductUpdateRequest extends FormRequest
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
            'description' => ['nullable', 'string', 'max:20000'],
            'brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'status' => ['required', Rule::enum(ProductStatus::class)],

            'variants' => ['nullable', 'array', 'max:50'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.sku' => ['required_with:variants', 'string', 'max:255'],
            'variants.*.barcode' => ['nullable', 'string', 'max:40'],
            'variants.*.list_price' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'variants.*.on_hand' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'variants.*.attributes' => ['nullable', 'array'],
            'variants.*.image_url' => ['nullable', 'string', 'max:2048'],

            'images' => ['nullable', 'array', 'max:20'],
            'images.*.url' => ['required', 'string', 'max:2048'],
            'images.*.position' => ['nullable', 'integer', 'min:0'],

            'channel_ids' => ['nullable', 'array'],
            'channel_ids.*' => ['integer', Rule::exists('channel_connections', 'id')],
        ];
    }
}
