<?php

declare(strict_types=1);

namespace Tests\Fixtures\Hepsiburada;

use JsonException;

/**
 * `measured-*.json` dosyalari Hepsiburada SIT ortamina yapilan GERCEK GET
 * cagrilarinin yanitlaridir — dokumandan kopyalanmis ya da uydurulmus degil.
 *
 * Bu, Trendyol fixture'larindan onemli bir fark: orada dokumanin dogru oldugunu
 * VARSAYIYORUZ, burada olctuk. Dokuman ile olcum celistiginde olcum kazanir.
 */
final class Fixture
{
    /**
     * @return array<array-key, mixed>
     *
     * @throws JsonException
     */
    public static function json(string $name): array
    {
        $contents = file_get_contents(__DIR__."/{$name}.json");

        if ($contents === false) {
            throw new JsonException("Hepsiburada fixture bulunamadi [{$name}].");
        }

        /** @var array<array-key, mixed> $decoded */
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
