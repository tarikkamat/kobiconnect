<?php

namespace App\Marketplaces\Support;

use App\Marketplaces\Data\MappingContext;

/**
 * Translates between a remote payload and a canonical DTO.
 *
 * Implementations are pure: every mapping they need is handed to them through
 * the MappingContext, so they are round-trip testable without a database.
 *
 * @template TCanonical of object
 */
interface Mapper
{
    /**
     * @param  array<string, mixed>  $remote
     * @return TCanonical
     */
    public function toCanonical(array $remote): object;

    /**
     * @param  TCanonical  $canonical
     * @return array<string, mixed>
     */
    public function toRemote(object $canonical, MappingContext $context): array;
}
