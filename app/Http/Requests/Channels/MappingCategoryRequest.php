<?php

declare(strict_types=1);

namespace App\Http\Requests\Channels;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Validator;
use Throwable;

/**
 * Sihirbaz 1. adim: kendi kategorimiz → pazaryeri kategorisi.
 *
 * Yaprak kontrolu arayuzde de var ama gercek yaptirim BURADA: kural
 * pazaryerinin kurali, tarayicinin degil (TRENDYOL.md §9, BACKEND-PLAN §7.5).
 */
class MappingCategoryRequest extends MappingRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'remote_category_id' => ['required', 'string', 'max:64'],
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

                $remoteId = $this->string('remote_category_id')->toString();

                try {
                    $remote = $this->catalog()->category($this->connection(), $remoteId);
                } catch (Throwable) {
                    // Katalog okunamiyorsa yaprak kontrolu yapilamaz; tahminle
                    // kaydetmektense reddedip sebebini soylemek dogru.
                    $validator->errors()->add(
                        'remote_category_id',
                        'Pazaryeri kataloğu şu an okunamıyor, eşleme doğrulanamadı. Birkaç dakika sonra tekrar deneyin.',
                    );

                    return;
                }

                if ($remote === null) {
                    $validator->errors()->add(
                        'remote_category_id',
                        'Bu kategori pazaryeri kataloğunda bulunamadı. Katalog güncellenmiş olabilir, aramayı tekrarlayın.',
                    );

                    return;
                }

                if (! $remote['isLeaf']) {
                    $validator->errors()->add(
                        'remote_category_id',
                        '"'.$remote['path'].'" bir üst kategori. Pazaryeri yalnızca alt kategorisi olmayan '
                        .'kategorilere ürün kabul ediyor; en alttaki kategoriyi seçin.',
                    );

                    return;
                }

                if ($this->changesLockedCategory($remoteId)) {
                    $validator->errors()->add(
                        'remote_category_id',
                        'Bu kategoride onaylanmış listeleme var. Onaydan sonra pazaryeri kategorisi değiştirilemez; '
                        .'farklı bir kategori gerekiyorsa ürünleri yeni bir kategoriyle yeniden listeleyin.',
                    );
                }
            },
        ];
    }

    /**
     * Onayli listelemesi olan bir kategoride `categoryId` sabittir
     * (TRENDYOL.md §9.3). Ayni degerin yeniden gonderilmesi degisiklik degildir.
     */
    private function changesLockedCategory(string $remoteId): bool
    {
        return $this->remoteCategoryId() !== $remoteId
            && $this->validation()->hasApprovedListings($this->connection(), $this->category());
    }
}
