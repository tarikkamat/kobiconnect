<?php

declare(strict_types=1);

namespace App\Actions\Mapping;

use Illuminate\Support\Str;

/**
 * Otomatik esleme onerisi — yalnizca isim benzerligi.
 *
 * ponytail: ML yok, ogrenen skorlama yok, es anlamli sozlugu yok. Normalize
 * edilmis isim esitligi vakalarin cogunu zaten yakaliyor ("Kadın Elbise" ↔
 * "Kadin elbise"), gerisi icin stdlib'in similar_text()'i yeterli. Kullanici
 * her oneriyi zaten onayliyor; yanlis oneri sessiz veri bozulmasi degil, bir
 * tik fazladan is demek. Skorlama yetmezse esik degeri degistirilir, model
 * egitilmez.
 */
final class SuggestMapping
{
    /**
     * Bu yuzdenin altindaki benzerlik oneri olarak GOSTERILMEZ: yanlis oneriyi
     * ayiklamak, oneri almamaktan pahalidir.
     */
    private const int THRESHOLD = 70;

    /**
     * Turkce'ye duyarli karsilastirma bicimi: `Str::slug` once transliterasyon
     * yapar (ş→s, ğ→g, ı→i, İ→i) sonra kucuk harfe cevirir, boylece nokta­li /
     * noktasiz I tuzagi karsilastirmanin disinda kalir. Ayirici bos birakilir
     * ki "Cep Telefonu" ile "Cep-Telefonu" ayni sonuca dussun.
     */
    public static function normalize(string $value): string
    {
        return Str::slug($value, '');
    }

    /**
     * Adaylari benzerlige gore siralar; esigin altindakiler dusurulur.
     *
     * @param  array<array-key, string>  $candidates  anahtar => gosterilecek isim
     * @return array<array-key, int> anahtar => 0-100 skor, en iyi basta
     */
    public function rank(string $needle, array $candidates, int $limit = 5): array
    {
        $target = self::normalize($needle);

        if ($target === '') {
            return [];
        }

        $scores = [];

        foreach ($candidates as $key => $label) {
            $candidate = self::normalize($label);

            if ($candidate === '') {
                continue;
            }

            if ($candidate === $target) {
                $scores[$key] = 100;

                continue;
            }

            similar_text($target, $candidate, $percent);

            if ($percent >= self::THRESHOLD) {
                $scores[$key] = (int) round($percent);
            }
        }

        arsort($scores);

        return array_slice($scores, 0, $limit, true);
    }

    /**
     * En iyi tek adayin anahtari, yoksa null.
     *
     * @param  array<array-key, string>  $candidates
     */
    public function best(string $needle, array $candidates): int|string|null
    {
        return array_key_first($this->rank($needle, $candidates, 1));
    }
}
