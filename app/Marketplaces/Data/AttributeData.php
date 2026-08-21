<?php

namespace App\Marketplaces\Data;

/**
 * A remote category attribute together with the flags that drive local
 * pre-validation before a product is pushed.
 */
final readonly class AttributeData
{
    /**
     * @param  list<AttributeValueData>  $values
     */
    public function __construct(
        public string $remoteId,
        public string $name,
        public bool $isRequired = false,
        public bool $allowsCustomValue = false,
        public bool $allowsMultipleValues = false,
        public bool $isVarianter = false,
        public bool $isSlicer = false,
        public array $values = [],
        /**
         * Ham pazaryeri tipi (`string`|`integer`|`enum`|`media`|`video`).
         *
         * Yerel on-dogrulama (BACKEND-PLAN §7.5) bunsuz calisamaz: `media`
         * tipinde zorunlu bir alani serbest metin sanip gecer, kullanici hatayi
         * ancak saatler sonra "MISSING_INFO" olarak gorur.
         *
         * Pazaryerleri farkli sozlukler kullanir; kanonik bayraklar
         * (isRequired/allowsCustomValue/...) turetilmis hali, bu ise hamdir.
         */
        public ?string $type = null,
    ) {}
}
