<?php

declare(strict_types=1);

namespace App\Marketplaces\Data;

/**
 * Pazaryeri, gonderdigimiz urunun kendi katalogundaki mevcut bir kayitla
 * ayni oldugunu DUSUNUYOR ve bizim kararimizi bekliyor.
 *
 * Bu bir urun durumu degil, bir GELEN KUTUSU kaydidir: kendi yasam dongusu,
 * kendi satici aksiyonu (onayla/reddet) ve kendi ekrani vardir. `ProductData`
 * icindeki bir alana katlanamaz — HEPSIBURADA.md §10 K1.
 *
 * Satici karar verene kadar urun satilabilir hale GELMEZ; islenmezse
 * "Eslesen" kuyrugunda takilir ve sessizce hicbir sey satilmaz.
 */
final readonly class MatchProposalData
{
    /**
     * @param  string  $reference  bizim tarafimizdaki dayanak (genelde merchantSku)
     * @param  list<string>  $proposedImages
     * @param  list<AttributeValueData>  $proposedAttributes
     */
    public function __construct(
        public string $reference,
        public string $proposedRemoteId,
        public ?string $proposedName = null,
        public ?string $proposedBrand = null,
        public array $proposedImages = [],
        public array $proposedAttributes = [],
        public ?string $proposedCategoryName = null,
    ) {}
}
