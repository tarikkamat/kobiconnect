<?php

declare(strict_types=1);

namespace App\Http\Requests\Channels;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Sihirbaz 4. adim: kendi markalarimiz → pazaryeri markalari.
 *
 * Marka eslemesi baglanti kapsamlidir (kategori kapsamli degil); sihirbaz
 * yalnizca o kategorideki urunlerin markalarini gosterir ki liste yonetilebilir
 * kalsin. Marka YARATMA bu fazda kapsam disi — eslesme bulunamazsa kullaniciya
 * durum anlatilir, sessizce yaklasik bir marka secilmez.
 */
class MappingBrandRequest extends MappingRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'brands' => ['nullable', 'array'],
            'brands.*.brand_id' => ['required', 'integer', Rule::exists('brands', 'id')],
            'brands.*.remote_brand_id' => ['required', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'brands.*.brand_id.exists' => 'Seçilen marka kataloğunuzda yok.',
        ];
    }

    /**
     * @return list<array{brand_id: int|string, remote_brand_id: string}>
     */
    public function rows(): array
    {
        /** @var list<array{brand_id: int|string, remote_brand_id: string}> $rows */
        $rows = $this->validated()['brands'] ?? [];

        return $rows;
    }
}
