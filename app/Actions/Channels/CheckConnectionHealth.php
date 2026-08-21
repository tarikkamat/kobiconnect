<?php

declare(strict_types=1);

namespace App\Actions\Channels;

use App\Enums\ConnectionStatus;
use App\Marketplaces\Hepsiburada\Exceptions\HepsiburadaApiException;
use App\Marketplaces\Hepsiburada\HepsiburadaClient;
use App\Marketplaces\Hepsiburada\HepsiburadaCredentials;
use App\Marketplaces\Hepsiburada\HepsiburadaService;
use App\Marketplaces\Support\Capability;
use App\Marketplaces\Support\Exceptions\MarketplaceException;
use App\Marketplaces\Support\MarketplaceManager;
use App\Marketplaces\Trendyol\Exceptions\TrendyolApiException;
use App\Marketplaces\Trendyol\TrendyolClient;
use App\Marketplaces\Trendyol\TrendyolCredentials;
use App\Models\ChannelConnection;
use Illuminate\Http\Client\ConnectionException;
use Throwable;

/**
 * Bir baglantinin kimlik bilgilerini pazaryerinde gercekten dener ve sonucu
 * `status` + `last_health_check_at` olarak yazar. Ayni yol hem kaydetmeden
 * sonra hem de "simdi test et" butonundan gecer, boylece saglik durumunun tek
 * bir uretim noktasi olur.
 *
 * Zamanlanmis saglik kontrolu bu fazda YOK (BACKEND-PLAN.md §8.3); geldiginde
 * ayni sinifi cagiran bir job olur.
 */
final class CheckConnectionHealth
{
    public function __construct(
        private readonly MarketplaceManager $marketplaces,
        private readonly TrendyolClient $trendyol,
        private readonly HepsiburadaClient $hepsiburada,
    ) {}

    /**
     * @return array{ok: bool, message: string}
     */
    public function handle(ChannelConnection $connection): array
    {
        $error = $this->probe($connection);

        $connection->update([
            'status' => $error === null ? ConnectionStatus::Active : ConnectionStatus::Error,
            'last_health_check_at' => now(),
            'capabilities' => $this->capabilities($connection->marketplace),
            'settings' => [...($connection->settings ?? []), 'last_health_error' => $error],
        ]);

        return [
            'ok' => $error === null,
            'message' => $error ?? 'Bağlantı doğrulandı.',
        ];
    }

    /**
     * Kullaniciya gosterilecek hata metni, basariliysa null.
     */
    private function probe(ChannelConnection $connection): ?string
    {
        try {
            // ponytail: saglik sondasi pazaryerine ozeldir ve `MarketplaceDriver`
            // sozlesmesinde karsiligi yok — her surucunun ucuz "beni dogrula"
            // cagrisi farkli bir serviste yasiyor. Ucuncu pazaryerinde bu match
            // surucunun kendi `check()` metoduna tasinmali.
            match ($connection->marketplace) {
                'trendyol' => $this->probeTrendyol($connection),
                'hepsiburada' => $this->probeHepsiburada($connection),
                default => throw new MarketplaceException("Unknown marketplace [{$connection->marketplace}]."),
            };

            return null;
        } catch (TrendyolApiException $exception) {
            return $this->explainTrendyol($exception, $connection);
        } catch (HepsiburadaApiException $exception) {
            return $this->explainHepsiburada($exception, $connection);
        } catch (Throwable $exception) {
            // Ag hatasi, bozuk kimlik bilgisi kaydi, bilinmeyen pazaryeri:
            // hicbiri 500 olmamali, hepsi baglantinin durumu olarak yazilmali.
            $label = $this->marketplaceLabel($connection->marketplace);

            return $exception instanceof ConnectionException
                ? $label.'’a ulaşılamadı. Sunucunun internet erişimini kontrol edip tekrar deneyin.'
                : 'Bağlantı denenemedi: '.$exception->getMessage();
        }
    }

    /**
     * Kategori ucunun ILK sayfasi, tek kayit: kimlik dogrulamayi kanitlayan en
     * ucuz cagri. Katalog servisi 200 govdesinde `success: false` de
     * dondurebilir; istemci onu da HepsiburadaApiException'a cevirir.
     *
     * @throws HepsiburadaApiException
     */
    private function probeHepsiburada(ChannelConnection $connection): void
    {
        $credentials = HepsiburadaCredentials::fromArray($connection->credentials->toArray());

        $this->hepsiburada
            ->as($credentials)
            ->get(
                HepsiburadaService::Catalog,
                'getAllCategoriesByParameters',
                '/product/api/categories/get-all-categories',
                ['leaf' => 'true', 'status' => 'ACTIVE', 'page' => 0, 'size' => 1],
            );
    }

    /**
     * HEPSIBURADA.md §2.1: servis anahtarini SATICI uretir ve panelinden bize
     * haber vermeden yenileyebilir — 401 bir hata degil, "yeni anahtar iste"
     * demektir. §2.2: SIT ayri host ve ayri kimlik bilgileri ister.
     */
    private function explainHepsiburada(HepsiburadaApiException $exception, ChannelConnection $connection): string
    {
        $sit = (bool) ($connection->credentials['sit'] ?? false);

        return match (true) {
            $exception->status === 401 => 'Hepsiburada kimlik doğrulamayı reddetti (401). Merchant ID veya Servis Anahtarı hatalı ya da yenilenmiş — satıcı panelinden yeni bir Servis Anahtarı üretip buraya girin.'
                .($sit ? ' Test (SIT) ortamı canlı anahtarları kabul etmez.' : ''),

            $exception->status === 403 => 'Hepsiburada isteği engelledi (403). Entegratör kullanıcı adı boşluksuz ve çıplak olmalı; gönderilen değer "'.(string) ($connection->credentials['integrator_user_agent'] ?? '').'".',

            $exception->status === 429 => 'Hepsiburada istek limiti aşıldı (429). Limit sunucunun çıkış IP’si başınadır, mağaza başına değil; birkaç dakika bekleyip tekrar deneyin.',

            default => "Hepsiburada {$exception->status} döndü: {$exception->getMessage()}",
        };
    }

    private function marketplaceLabel(string $marketplace): string
    {
        try {
            return $this->marketplaces->driver($marketplace)->displayName();
        } catch (MarketplaceException) {
            return $marketplace;
        }
    }

    /**
     * @throws TrendyolApiException
     */
    private function probeTrendyol(ChannelConnection $connection): void
    {
        $credentials = TrendyolCredentials::fromArray($connection->credentials->toArray());

        // ponytail: surucunun `brands()` metodu yerine dogrudan istemci, cunku
        // surucu yanitlari satici id'si TASIMAYAN bir anahtarla cache'liyor —
        // ikinci bir baglanti hic istek atmadan "saglikli" gorunurdu. Saglik
        // kontrolu gercekten cagirmak zorunda. Surucuye baglanti bazli cache
        // anahtari eklenirse burasi `->for($credentials)->brands()` olabilir.
        $this->trendyol
            ->as($credentials)
            ->get('getBrands', 'product/brands', ['page' => 0, 'size' => 1]);
    }

    /**
     * Trendyol'un uc hata zarfi TrendyolApiException'da zaten tekillestirildi;
     * burada yalnizca satiraya ne yapacagini soyleyen kisim var. Pazaryeri hata
     * kodlari CEVRILMEZ, yanina Turkce aciklama eklenir (FRONTEND-PLAN.md §7).
     */
    private function explainTrendyol(TrendyolApiException $exception, ChannelConnection $connection): string
    {
        $stage = (bool) ($connection->credentials['stage'] ?? false);
        $detail = implode(' · ', array_map(
            static fn (array $error): string => trim("{$error['key']}: {$error['message']}", ': '),
            $exception->errors,
        ));

        return match (true) {
            $exception->status === 401 => 'Trendyol kimlik doğrulamayı reddetti (401). API anahtarı veya gizli anahtar hatalı — Satıcı Paneli → Hesap Bilgilerim → Entegrasyon Bilgileri’nden yeniden alın.',

            // TRENDYOL.md §2.3: User-Agent'i olmayan veya bicimi tutmayan
            // istekler 403 ile engellenir; format `{satıcıId} - {entegratör}`.
            $exception->status === 403 => 'Trendyol isteği User-Agent nedeniyle engelledi (403). Satıcı ID ve entegratör adını kontrol edin; gönderilen değer "'.$this->userAgent($connection).'".',

            // TRENDYOL.md §2.8: stage'de 503 kesinti degil, IP izin listesi eksigi.
            $exception->status === 503 && $stage => 'Test (stage) ortamı sunucunuzun IP adresini tanımıyor. Trendyol’un IP izin listesine eklenmesi gerekiyor (0850 258 58 00) — bu bir kesinti değil.',

            $exception->status === 429 => 'Trendyol istek limiti aşıldı (429). Birkaç dakika bekleyip tekrar deneyin.',

            default => "Trendyol {$exception->status} döndü".($detail === '' ? '.' : ": {$detail}"),
        };
    }

    private function userAgent(ChannelConnection $connection): string
    {
        $sellerId = (string) ($connection->credentials['seller_id'] ?? '');
        $integrator = (string) ($connection->credentials['integrator'] ?? '');

        return "{$sellerId} - {$integrator}";
    }

    /**
     * Yetenek anlik goruntusu: surucunun implement ettigi arayuzlerden turer,
     * elle listelenmez. Her saglik kontrolunde tazelenir ki kolon eskimesin.
     *
     * @return list<string>
     */
    private function capabilities(string $marketplace): array
    {
        try {
            return array_map(
                static fn (Capability $capability): string => $capability->value,
                $this->marketplaces->driver($marketplace)->capabilities(),
            );
        } catch (MarketplaceException) {
            return [];
        }
    }
}
