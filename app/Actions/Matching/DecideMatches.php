<?php

declare(strict_types=1);

namespace App\Actions\Matching;

use App\Marketplaces\Contracts\SupportsCatalogMatching;
use App\Marketplaces\Data\Enums\CanonicalListingStatus;
use App\Models\ChannelConnection;
use App\Models\ChannelListing;
use App\Models\ProductVariant;
use App\Support\Sync\ConnectionDriver;
use Illuminate\Support\Facades\DB;

/**
 * Saticinin "onayla" / "reddet" kararini pazaryerine gonderir ve SONUCU YEREL
 * OLARAK KAYDEDER.
 *
 * Yerel kayit sus degil, akisin sarti: `approve-prematch` / `reject-prematch`
 * ne bir is kimligi ne de poll ucu doner (HEPSIBURADA.md §3 H4, ucuncu idiom).
 * Karari yazmazsak ayni oneri gelen kutusunda durmaya devam eder ve satici ayni
 * urunu tekrar tekrar onaylar.
 *
 * Karar sonrasi kanonik durum:
 * - onay  → `PendingApproval` (top artik pazaryerinde; satisa acmasini bekliyoruz)
 * - red   → `Rejected` + `error` icinde REDDIN BIZDEN geldigi notu; pazaryeri
 *           `REJECTED` degerini iki farkli anlamda kullaniyor (§5.1) ve o ayrim
 *           yalnizca burada saklanabiliyor.
 */
final class DecideMatches
{
    /**
     * @var array{code: string, message: string}
     */
    private const array REJECTED_NOTE = [
        'code' => 'match_rejected',
        'message' => 'Pazaryerinin eşleşme önerisi reddedildi. Ürün kendi katalog kaydımız olarak ilerliyor.',
    ];

    /**
     * @var array{code: string, message: string}
     */
    private const array DEGRADED_NOTE = [
        'code' => 'degraded_zero_price',
        'message' => 'Pazaryeri kararı kabul etti ama listelemeyi 0 fiyat / 0 stok ile açtı. Ürün yayında görünür, satılamaz.',
    ];

    public function __construct(private readonly ConnectionDriver $drivers) {}

    /**
     * @param  list<string>  $references
     * @return array{ok: bool, message: string}
     */
    public function __invoke(ChannelConnection $connection, array $references, bool $approve): array
    {
        $driver = $this->drivers->for($connection);

        if (! $driver instanceof SupportsCatalogMatching) {
            return ['ok' => false, 'message' => 'Bu pazaryeri ön eşleşme akışı sunmuyor.'];
        }

        $result = $approve
            ? $driver->approveMatches($references)
            : $driver->rejectMatches($references);

        if (! $result->accepted) {
            return [
                'ok' => false,
                'message' => $result->failureReason ?? 'Pazaryeri kararı kabul etmedi, hiçbir öneri işlenmedi.',
            ];
        }

        // Kalem sonucu donmeyen pazaryerinde (HB) `itemResults` bostur ve
        // gonderilenlerin hepsi karara baglanmis sayilir — 200 "kabul edildi"
        // demektir. Kalem sonucu donen bir surucude yalnizca gecenler yazilir.
        $failed = $result->failedReferences();
        $degraded = $result->degradedReferences();
        $clean = array_values(array_diff($references, $failed, $degraded));

        $status = $approve ? CanonicalListingStatus::PendingApproval : CanonicalListingStatus::Rejected;
        $note = $approve ? null : self::REJECTED_NOTE;

        $this->record($connection, $clean, $status, $note);
        $this->record($connection, $degraded, $status, self::DEGRADED_NOTE);

        return ['ok' => true, 'message' => $this->summary($approve, count($clean), $degraded, $failed)];
    }

    /**
     * Karari, referansin isaret ettigi varyantin listelemesine yazar.
     *
     * Referans normalize edilmis SKU'dur (HEPSIBURADA.md §10.3 M20: pazaryeri
     * `merchantSku`'yu sessizce buyuk harfe cevirir ve bosluk kabul etmez), bu
     * yuzden eslesme de normalize hali uzerinden kurulur — aksi halde "abc-1"
     * SKU'lu urun hicbir zaman bulunamaz ve karar sessizce kaybolur.
     *
     * @param  list<string>  $references
     * @param  array{code: string, message: string}|null  $note
     */
    private function record(
        ChannelConnection $connection,
        array $references,
        CanonicalListingStatus $status,
        ?array $note,
    ): void {
        if ($references === []) {
            return;
        }

        ChannelListing::query()
            ->where('connection_id', $connection->getKey())
            ->whereIn('variant_id', ProductVariant::query()
                ->whereIn(DB::raw("upper(replace(sku, ' ', ''))"), $references)
                ->select('id'))
            // Toplu update model cast'lerinden GECMEZ; jsonb kolona dizi
            // veremeyecegimiz icin kodlama burada elle yapiliyor.
            ->update([
                'remote_status' => $status->value,
                'error' => $note === null ? null : json_encode($note, JSON_UNESCAPED_UNICODE),
            ]);
    }

    /**
     * @param  list<string>  $degraded
     * @param  list<string>  $failed
     */
    private function summary(bool $approve, int $clean, array $degraded, array $failed): string
    {
        $parts = [$approve
            ? "{$clean} öneri onaylandı; ürünler pazaryeri tarafında satışa açılıyor."
            : "{$clean} öneri reddedildi; ürünler kendi katalog kaydımız olarak ilerliyor."];

        if ($degraded !== []) {
            $parts[] = count($degraded).' tanesi 0 fiyat / 0 stok ile açıldı ve satılamaz — '
                .'Kanal Listelemeleri ekranından fiyatı düzeltin.';
        }

        if ($failed !== []) {
            $parts[] = count($failed).' öneri pazaryeri tarafından işlenemedi, gelen kutusunda kaldı.';
        }

        return implode(' ', $parts);
    }
}
