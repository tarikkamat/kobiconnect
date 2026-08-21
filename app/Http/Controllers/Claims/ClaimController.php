<?php

declare(strict_types=1);

namespace App\Http\Controllers\Claims;

use App\Http\Controllers\Controller;
use App\Marketplaces\Data\Enums\CanonicalClaimStatus;
use App\Models\ChannelConnection;
use App\Models\Claim;
use App\Models\ClaimItem;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Number;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * İade (claim) listesi ve detayı — bu faz yalnızca OKUMA.
 *
 * Onay/red BİLEREK yok: her ikisi de pazaryerine yazma demektir, outbox
 * motoruna bağlıdır ve MVP kapsamı dışındadır (BACKEND-PLAN §7.2). Burada
 * amaç görünürlük: hangi talep açık, hangisi aksiyon bekliyor.
 *
 * KVKK: `claims.raw` şifrelidir ve Inertia prop'una ASLA girmez; müşteri
 * bilgisi sipariş ekranındaki maskeli hâliyle görülür (BACKEND-PLAN §13).
 */
class ClaimController extends Controller
{
    /**
     * Arayuz metinleri Turkce, kanonik enum degerleri degil — FRONTEND-PLAN §7.
     *
     * @var array<string, string>
     */
    private const array STATUS_LABELS = [
        'created' => 'Açıldı',
        'waiting_action' => 'Aksiyon bekliyor',
        'under_review' => 'İnceleniyor',
        'accepted' => 'Kabul edildi',
        'rejected' => 'Reddedildi',
        'cancelled' => 'İptal edildi',
        'unresolved' => 'Çözülmedi',
    ];

    public function index(Request $request): Response
    {
        Gate::authorize('orders.view');

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(CanonicalClaimStatus::class)],
            'connection' => ['nullable', 'integer'],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? null;
        $connection = isset($filters['connection']) ? (int) $filters['connection'] : null;

        $claims = Claim::query()
            ->with(['order:id,remote_order_number,connection_id', 'order.connection:id,name'])
            ->withCount('items')
            ->withSum('items', 'quantity')
            ->when($search !== '', fn (Builder $query) => $query->where(
                fn (Builder $inner) => $inner
                    ->where('remote_claim_id', 'like', "%{$search}%")
                    ->orWhereHas(
                        'order',
                        fn (Builder $order) => $order->where('remote_order_number', 'like', "%{$search}%"),
                    ),
            ))
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status))
            ->when($connection !== null, fn (Builder $query) => $query->whereHas(
                'order',
                fn (Builder $order) => $order->where('connection_id', $connection),
            ))
            ->orderByDesc('opened_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Claim $claim): array => [
                'id' => $claim->getKey(),
                'remoteClaimId' => $claim->remote_claim_id,
                'orderId' => $claim->order_id,
                'orderNumber' => $claim->order->remote_order_number,
                'connection' => $claim->order->connection?->name,
                'status' => $claim->status->value,
                'statusLabel' => $this->statusLabel($claim->status->value),
                'externalStatus' => $claim->external_status,
                'reason' => $claim->reason,
                'itemCount' => (int) $claim->getAttribute('items_count'),
                'quantity' => (int) $claim->getAttribute('items_sum_quantity'),
                'openedAt' => $this->dateTime($claim->opened_at),
            ]);

        return Inertia::render('claims/index', [
            'claims' => Inertia::scroll($claims),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'connection' => $connection,
            ],
            'statuses' => $this->statusOptions(),
            'connections' => ChannelConnection::query()->orderBy('name')->get(['id', 'name']),
            // Operatorun ilk bakacagi sayi: aksiyon bekleyen talep.
            'actionableTotal' => Claim::query()
                ->where('status', CanonicalClaimStatus::WaitingAction)
                ->count(),
        ]);
    }

    public function show(Claim $claim): Response
    {
        Gate::authorize('orders.view');

        $claim->load([
            'order:id,remote_order_number,remote_id,connection_id,currency,placed_at',
            'order.connection:id,name',
            'items.orderLine:id,sku,barcode,quantity,unit_price',
        ]);

        return Inertia::render('claims/show', [
            'claim' => [
                'id' => $claim->getKey(),
                'remoteClaimId' => $claim->remote_claim_id,
                'status' => $claim->status->value,
                'statusLabel' => $this->statusLabel($claim->status->value),
                'externalStatus' => $claim->external_status,
                'reason' => $claim->reason,
                'openedAt' => $this->dateTime($claim->opened_at),
            ],
            'order' => [
                'id' => $claim->order_id,
                'orderNumber' => $claim->order->remote_order_number,
                'packageId' => $claim->order->remote_id,
                'connection' => $claim->order->connection?->name,
                'placedAt' => $this->dateTime($claim->order->placed_at),
            ],
            'items' => $claim->items
                ->map(fn (ClaimItem $item): array => [
                    'id' => $item->getKey(),
                    'remoteItemId' => $item->remote_item_id,
                    'quantity' => $item->quantity,
                    'status' => $item->status->value,
                    'statusLabel' => $this->statusLabel($item->status->value),
                    'externalStatus' => $item->external_status,
                    'reason' => $item->reason,
                    // Siparis satiri bulunamamis olabilir; talep yine de gorunur.
                    'sku' => $item->orderLine?->sku,
                    'barcode' => $item->orderLine?->barcode,
                    'unitPrice' => $item->orderLine === null
                        ? null
                        : (string) Number::currency((float) $item->orderLine->unit_price, $claim->order->currency, 'tr'),
                ])
                ->all(),
        ]);
    }

    /**
     * Bilinmeyen bir kanonik deger etikete katlanmaz, ham hâliyle gosterilir.
     */
    private function statusLabel(string $status): string
    {
        return self::STATUS_LABELS[$status] ?? $status;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return array_map(
            fn (CanonicalClaimStatus $status): array => [
                'value' => $status->value,
                'label' => $this->statusLabel($status->value),
            ],
            CanonicalClaimStatus::cases(),
        );
    }

    /**
     * Tarihler sunucuda bicimlenir, Europe/Istanbul — FRONTEND-PLAN §7.
     */
    private function dateTime(DateTimeInterface|string|null $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // Model cast'i CarbonImmutable dondurur, iliski uzerinden gelen deger
        // ise ham string olabilir; tek bir yerde normalize ediyoruz.
        return Carbon::parse($value)->timezone('Europe/Istanbul')->format('d.m.Y H:i');
    }
}
