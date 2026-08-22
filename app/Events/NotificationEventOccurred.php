<?php

declare(strict_types=1);

namespace App\Events;

use App\Notifications\NotificationEvent;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * §11.2 olay haritasinin tasiyicisi. Lisans olaylari disindaki her bildirim
 * olayi bununla dagitilir:
 *
 *     NotificationEventOccurred::dispatch(NotificationEvent::SyncFailed, [
 *         'connection_id' => $connection->getKey(),
 *         'connection' => $connection->name,
 *         'reason' => $exception->getMessage(),
 *     ]);
 *
 * ponytail: olay basina AYRI bir sinif yok. On bes olayin tek farki metin,
 * hedef kitle ve susturma penceresidir; ucu de NotificationEvent enum'unda
 * duruyor. Ayri siniflar on iki dosya ve sifir davranis eklerdi. Bir olay
 * gercekten kendine ait davranis kazanirsa (ornegin bir modeli tasimasi
 * gerekirse) o zaman kendi sinifina cikarilir.
 *
 * Bugun BAGLI olan tetikleyiciler:
 *  - StockCriticalLow → App\Observers\InventoryItemObserver
 *
 * ponytail: asagidakilerin bugun karsiligi yok; olay tanimi hazir, tetikleyici
 * ilgili modulle birlikte gelecek. Baglanacaklari TEK satir soyle:
 *
 *  - OrderReceived / OrderCancelled / OrderLineUnmatched
 *      → App\Actions\Orders\ImportOrders::persist() sonu (rapora bakin)
 *  - SyncFailed
 *      → App\Jobs\Sync\DrainChannelOperations::returnToPending()/reject()
 *  - ProductApproved / ProductRejected
 *      → App\Actions\Sync\ApplyBatchResult, item bazli sonuc okunurken
 *  - ConnectionCredentialsInvalid
 *      → App\Actions\Channels\CheckConnectionHealth::handle(), 401 dalinda
 *  - WebhookDeliveryFailing / WebhookDeactivated
 *      → App\Support\Sync\WebhookIngest ve webhook saglik yonetimi (§8.3)
 *  - ClaimOpened / QuestionReceived
 *      → iade ve soru modulleri (SupportsClaims / SupportsQuestions cekimi)
 */
final class NotificationEventOccurred
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $payload  Kuyruk payload'ina serilesir: model degil, id ve metin gecer.
     */
    public function __construct(
        public readonly NotificationEvent $event,
        public readonly array $payload = [],
    ) {}
}
