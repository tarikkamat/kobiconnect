<?php

declare(strict_types=1);

namespace App\Marketplaces\Contracts;

use App\Marketplaces\Data\MatchProposalData;
use App\Marketplaces\Data\PullPage;
use App\Marketplaces\Data\PushResult;

/**
 * Pazaryeri, urunlerimizi kendi katalogundaki kayitlarla eslestirmeyi ONERIR ve
 * satisa acilmadan once bizim onayimizi bekler.
 *
 * Opsiyonel bir ozellik degildir: onerileri islemeyen bir entegrasyonda
 * urunler kalici olarak "karar bekliyor" durumunda kalir ve hicbiri satilmaz.
 */
interface SupportsCatalogMatching
{
    /**
     * @return PullPage<MatchProposalData>
     */
    public function pendingMatchProposals(?string $cursor = null): PullPage;

    /**
     * @param  list<string>  $references
     */
    public function approveMatches(array $references): PushResult;

    /**
     * @param  list<string>  $references
     */
    public function rejectMatches(array $references): PushResult;
}
