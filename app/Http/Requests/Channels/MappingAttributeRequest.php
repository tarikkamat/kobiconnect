<?php

declare(strict_types=1);

namespace App\Http\Requests\Channels;

use App\Marketplaces\Data\AttributeData;
use App\Models\ChannelAttributeMapping;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Throwable;

/**
 * Sihirbaz 2. adim: pazaryeri kategorisinin ozellikleri → kendi ozelliklerimiz.
 *
 * Bayraklar (`required`, `allowCustom`, `allowMultipleAttributeValues`,
 * `varianter`, `slicer`) ISTEKTEN OKUNMAZ. Onlar pazaryerinin gercegidir ve
 * kayit sirasinda referans kataloğundan kopyalanir; istemciden gelseydi bir
 * tarayici konsolu zorunlu bir alani istege bagli yapabilirdi.
 */
class MappingAttributeRequest extends MappingRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'attributes' => ['nullable', 'array'],
            'attributes.*.remote_attribute_id' => ['required', 'string', 'max:64'],
            'attributes.*.attribute_id' => ['required', 'integer', Rule::exists('attributes', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'attributes.*.attribute_id.exists' => 'Seçilen özellik kataloğunuzda yok.',
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

                if ($this->remoteCategoryId() === null) {
                    $validator->errors()->add('attributes', 'Önce kategori eşlemesini tamamlayın.');

                    return;
                }

                $remote = $this->remoteAttributes();
                $selected = $this->selection();

                if ($remote === [] && $selected !== []) {
                    $validator->errors()->add(
                        'attributes',
                        'Pazaryeri özellik listesi şu an okunamıyor, eşleme doğrulanamadı. Birkaç dakika sonra tekrar deneyin.',
                    );

                    return;
                }

                foreach (array_keys($selected) as $remoteId) {
                    if (! isset($remote[$remoteId])) {
                        $validator->errors()->add(
                            'attributes',
                            'Bu kategoride bulunmayan bir özellik gönderildi ('.$remoteId.').',
                        );

                        return;
                    }
                }

                $varianters = array_filter(
                    array_keys($selected),
                    static fn (string $remoteId): bool => $remote[$remoteId]->isVarianter,
                );

                // TRENDYOL.md §9.7: her kategoride TAM OLARAK BIR varianter.
                // Sifir eslemeye izin verilir (yarim birakilmis taslak), ikiye
                // izin verilmez — o durumda gonderim kalem duzeyinde reddedilir.
                if (count($varianters) > 1) {
                    $validator->errors()->add(
                        'attributes',
                        'Kategori başına yalnızca bir varyant belirleyici özellik eşlenebilir; '
                        .count($varianters).' tane seçilmiş.',
                    );
                }

                if ($this->changesLockedFlags($remote, $selected)) {
                    $validator->errors()->add(
                        'attributes',
                        'Bu kategoride onaylanmış listeleme var. Varyant belirleyici ve ayrı ürün kartı açan '
                        .'özellikler onaydan sonra değiştirilemez.',
                    );
                }
            },
        ];
    }

    /**
     * Gonderilen esleme: pazaryeri ozellik id'si → kendi ozellik id'miz.
     *
     * @return array<string, int>
     */
    public function selection(): array
    {
        $selection = [];

        /** @var list<array{remote_attribute_id: string, attribute_id: int|string}> $rows */
        $rows = $this->validated()['attributes'] ?? [];

        foreach ($rows as $row) {
            $selection[(string) $row['remote_attribute_id']] = (int) $row['attribute_id'];
        }

        return $selection;
    }

    /**
     * Pazaryeri ozellikleri, uzak id'ye gore anahtarlanmis. Cache'li referans
     * veri; kayit adiminda ikinci bir HTTP istegi anlamina gelmez.
     *
     * @return array<string, AttributeData>
     */
    public function remoteAttributes(): array
    {
        $remoteCategoryId = $this->remoteCategoryId();

        if ($remoteCategoryId === null) {
            return [];
        }

        $indexed = [];

        try {
            $attributes = $this->catalog()->attributes($this->connection(), $remoteCategoryId);
        } catch (Throwable) {
            return [];
        }

        foreach ($attributes as $attribute) {
            $indexed[$attribute->remoteId] = $attribute;
        }

        return $indexed;
    }

    /**
     * `slicer` ve `varianter` degerleri onaydan sonra degistirilemez
     * (TRENDYOL.md §9.3, §9.7): bu bayraklari tasiyan satirlarin kumesi
     * degisiyorsa kayit reddedilir.
     *
     * @param  array<string, AttributeData>  $remote
     * @param  array<string, int>  $selected
     */
    private function changesLockedFlags(array $remote, array $selected): bool
    {
        if (! $this->validation()->hasApprovedListings($this->connection(), $this->category())) {
            return false;
        }

        $incoming = [];

        foreach ($selected as $remoteId => $attributeId) {
            if ($remote[$remoteId]->isVarianter || $remote[$remoteId]->isSlicer) {
                $incoming[$remoteId] = $attributeId;
            }
        }

        $stored = ChannelAttributeMapping::query()
            ->where('connection_id', $this->connection()->getKey())
            ->where('remote_category_id', $this->remoteCategoryId())
            ->where(fn ($query) => $query->where('is_varianter', true)->orWhere('is_slicer', true))
            ->pluck('attribute_id', 'remote_attribute_id')
            ->map(intval(...))
            ->all();

        ksort($incoming);
        ksort($stored);

        return $incoming !== $stored;
    }
}
