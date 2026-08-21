<?php

namespace Tests\Fixtures\Trendyol;

use JsonException;

/**
 * The response bodies in this directory are copied verbatim out of TRENDYOL.md,
 * which quotes Trendyol's own documentation. Nothing here is invented.
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
            throw new JsonException("Missing Trendyol fixture [{$name}].");
        }

        /** @var array<array-key, mixed> $decoded */
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
