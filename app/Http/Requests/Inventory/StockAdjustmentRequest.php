<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Varyant x depo hucresinin duzenlenebilir alanlari.
 *
 * `available` BILEREK `prohibited`: veritabaninda `on_hand - reserved` uzerine
 * kurulu generated column'dur ve tek dogruluk kaynagi odur. Istemci yine de
 * yollarsa sessizce yok saymak yerine sebebini soyleyerek reddediyoruz.
 *
 * `on_hand` degisimi bir depo operasyonudur: `reason` zorunludur ve iz birakir
 * (bkz. StockController::update). `reserved` ve `safety_stock` gerekce
 * istemez — biri sistemsel rezervasyon, digeri bir esik ayaridir.
 */
class StockAdjustmentRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'on_hand' => ['nullable', 'integer', 'min:0', 'max:99999999'],
            'reserved' => ['nullable', 'integer', 'min:0', 'max:99999999'],
            'safety_stock' => ['nullable', 'integer', 'min:0', 'max:99999999'],
            'reason' => ['required_with:on_hand', 'nullable', 'string', 'max:255'],
            'available' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'available.prohibited' => 'Kullanılabilir stok düzenlenemez: veritabanında "eldeki − rezerve" olarak hesaplanır.',
            'reason.required_with' => 'Stok düzeltmesi için bir sebep girin; düzeltme iz bırakır.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'on_hand' => 'eldeki stok',
            'reserved' => 'rezerve stok',
            'safety_stock' => 'güvenlik stoğu',
            'reason' => 'sebep',
        ];
    }
}
