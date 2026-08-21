<?php

declare(strict_types=1);

namespace App\Http\Requests\Channels;

use App\Models\ChannelAttributeMapping;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Sihirbaz 3. adim: kendi ozellik degerlerimiz → pazaryeri degerleri.
 */
class MappingValueRequest extends MappingRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'values' => ['nullable', 'array'],
            'values.*.mapping_id' => ['required', 'integer'],
            'values.*.attribute_value_id' => ['required', 'integer', Rule::exists('attribute_values', 'id')],
            'values.*.remote_value_id' => ['required', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                // `mapping_id` istemciden geliyor: baska bir baglantinin ya da
                // baska bir kategorinin satirina yazilmadigindan emin olunur.
                $allowed = $this->attributeMappingIds();

                foreach ($this->rows() as $row) {
                    if (! in_array((int) $row['mapping_id'], $allowed, true)) {
                        $validator->errors()->add('values', 'Bu kategoriye ait olmayan bir özellik eşlemesi gönderildi.');

                        return;
                    }
                }
            },
        ];
    }

    /**
     * @return list<array{mapping_id: int|string, attribute_value_id: int|string, remote_value_id: string}>
     */
    public function rows(): array
    {
        /** @var list<array{mapping_id: int|string, attribute_value_id: int|string, remote_value_id: string}> $rows */
        $rows = $this->validated()['values'] ?? [];

        return $rows;
    }

    /**
     * @return list<int>
     */
    public function attributeMappingIds(): array
    {
        $remoteCategoryId = $this->remoteCategoryId();

        if ($remoteCategoryId === null) {
            return [];
        }

        return array_values(ChannelAttributeMapping::query()
            ->where('connection_id', $this->connection()->getKey())
            ->where('remote_category_id', $remoteCategoryId)
            ->pluck('id')
            ->map(intval(...))
            ->all());
    }
}
