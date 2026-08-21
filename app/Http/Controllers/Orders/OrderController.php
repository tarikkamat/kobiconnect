<?php

declare(strict_types=1);

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Marketplaces\Data\Enums\CanonicalOrderStatus;
use App\Models\ChannelConnection;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Number;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use stdClass;

/**
 * Sipariş listesi ve detayı — bu faz yalnızca OKUMA.
 *
 * Filtreler kanonik durumlar üzerindedir, pazaryerinin ham durumları üzerinde
 * değil: operatör "Kargoda" arar, "Shipped" değil. Ham durum satırın yanında
 * bilgi olarak durur, çünkü bilinmeyen bir pazaryeri durumu asla bir varsayılana
 * katlanmaz (TRENDYOL.md §5).
 *
 * KVKK: `orders.customer` ve `orders.raw` şifrelidir ve ham hâlleriyle ASLA
 * Inertia prop'una girmez (TRENDYOL.md §11, BACKEND-PLAN §13). Buradan çıkan tek
 * kişisel veri maskelenmiş ad, maskelenmiş telefon/e-posta ve il/ilçe düzeyinde
 * adrestir; TCKN hiçbir koşulda taşınmaz.
 */
class OrderController extends Controller
{
    /**
     * Arayuz metinleri Turkce, kanonik enum degerleri degil — FRONTEND-PLAN §7.
     *
     * @var array<string, string>
     */
    private const array STATUS_LABELS = [
        'pending_payment' => 'Ödeme bekleniyor',
        'created' => 'Gönderime hazır',
        'picking' => 'Hazırlanıyor',
        'invoiced' => 'Faturalandı',
        'shipped' => 'Kargoda',
        'at_collection_point' => 'Teslimat noktasında',
        'delivered' => 'Teslim edildi',
        'undelivered' => 'Teslim edilemedi',
        'unpacked' => 'Paket bölündü',
        'unsupplied' => 'Tedarik edilemedi',
        'cancelled' => 'İptal edildi',
        'returned' => 'İade edildi',
        'unknown' => 'Bilinmeyen durum',
    ];

    public function index(Request $request): Response
    {
        Gate::authorize('orders.view');

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(CanonicalOrderStatus::class)],
            'connection' => ['nullable', 'integer'],
            'unmatched' => ['nullable', 'boolean'],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? null;
        $connection = isset($filters['connection']) ? (int) $filters['connection'] : null;
        $unmatched = (bool) ($filters['unmatched'] ?? false);

        $orders = DB::table('orders')
            ->leftJoin('channel_connections', 'channel_connections.id', '=', 'orders.connection_id')
            ->select([
                'orders.id',
                'orders.remote_order_number',
                'orders.remote_id',
                'orders.status',
                'orders.external_status',
                'orders.currency',
                'orders.placed_at',
                'orders.totals',
                'orders.customer',
                'channel_connections.name as connection_name',
            ])
            ->selectSub($this->lineCount(), 'line_count')
            ->selectSub($this->lineCount()->whereNull('order_lines.variant_id'), 'unmatched_count')
            ->when($search !== '', fn (Builder $query) => $query->where(
                fn (Builder $inner) => $inner
                    ->where('orders.remote_order_number', 'like', "%{$search}%")
                    ->orWhere('orders.remote_id', 'like', "%{$search}%"),
            ))
            ->when($status !== null, fn (Builder $query) => $query->where('orders.status', $status))
            ->when($connection !== null, fn (Builder $query) => $query->where('orders.connection_id', $connection))
            // Esleşmemis satirlar ayri bir tablo degil, ayni listenin bir
            // filtresi: operatör siparisi baglaminda görmek zorunda.
            ->when($unmatched, fn (Builder $query) => $query->whereExists(
                fn (Builder $lines) => $lines->from('order_lines')
                    ->whereColumn('order_lines.order_id', 'orders.id')
                    ->whereNull('order_lines.variant_id'),
            ))
            ->orderByDesc('orders.placed_at')
            ->orderByDesc('orders.id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (stdClass $order): array => [
                'id' => (int) $order->id,
                'orderNumber' => (string) $order->remote_order_number,
                'packageId' => (string) $order->remote_id,
                'status' => (string) $order->status,
                'statusLabel' => $this->statusLabel((string) $order->status),
                'externalStatus' => (string) $order->external_status,
                'connection' => $order->connection_name,
                'customer' => $this->maskedName($order->customer),
                'total' => $this->money($order->totals, (string) $order->currency),
                'placedAt' => $this->dateTime($order->placed_at),
                'lineCount' => (int) $order->line_count,
                'unmatchedCount' => (int) $order->unmatched_count,
            ]);

        return Inertia::render('orders/index', [
            'orders' => Inertia::scroll($orders),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'connection' => $connection,
                'unmatched' => $unmatched,
            ],
            'statuses' => $this->statusOptions(),
            'connections' => ChannelConnection::query()->orderBy('name')->get(['id', 'name']),
            'unmatchedTotal' => (int) DB::table('order_lines')->whereNull('variant_id')->count(),
        ]);
    }

    public function show(int $order): Response
    {
        Gate::authorize('orders.view');

        $row = DB::table('orders')
            ->leftJoin('channel_connections', 'channel_connections.id', '=', 'orders.connection_id')
            ->where('orders.id', $order)
            ->select(['orders.*', 'channel_connections.name as connection_name'])
            ->first();

        abort_if($row === null, 404);

        $customer = $this->customer($row->customer);

        return Inertia::render('orders/show', [
            'order' => [
                'id' => (int) $row->id,
                'orderNumber' => (string) $row->remote_order_number,
                'packageId' => (string) $row->remote_id,
                'status' => (string) $row->status,
                'statusLabel' => $this->statusLabel((string) $row->status),
                'externalStatus' => (string) $row->external_status,
                'connection' => $row->connection_name,
                'currency' => (string) $row->currency,
                'placedAt' => $this->dateTime($row->placed_at),
                'lastModifiedAt' => $this->dateTime($row->remote_last_modified_at),
                'totals' => $this->totals($row->totals, (string) $row->currency),
                // Yalnizca maskelenmis alanlar; TCKN, tam adres, koordinat ve
                // ham payload bu sinirdan gecmez.
                'customer' => [
                    'name' => $this->mask($customer),
                    'email' => $this->maskEmail($customer['email'] ?? null),
                    'phone' => $this->maskPhone($customer['phone'] ?? null),
                    'city' => $this->addressPart($customer, 'city'),
                    'district' => $this->addressPart($customer, 'district'),
                ],
            ],
            'lines' => $this->lines($order),
            'packages' => $this->packages($order),
            'history' => $this->history($order),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function lines(int $orderId): array
    {
        return array_values(DB::table('order_lines')
            ->leftJoin('product_variants', 'product_variants.id', '=', 'order_lines.variant_id')
            ->where('order_lines.order_id', $orderId)
            ->orderBy('order_lines.id')
            ->select([
                'order_lines.id', 'order_lines.remote_line_id', 'order_lines.sku', 'order_lines.barcode',
                'order_lines.quantity', 'order_lines.unit_price', 'order_lines.status',
                'order_lines.external_status', 'order_lines.vat_rate', 'order_lines.commission',
                'order_lines.variant_id', 'product_variants.sku as variant_sku',
            ])
            ->get()
            ->map(fn (stdClass $line): array => [
                'id' => (int) $line->id,
                'remoteLineId' => (string) $line->remote_line_id,
                'sku' => (string) $line->sku,
                'barcode' => $line->barcode,
                'quantity' => (int) $line->quantity,
                'unitPrice' => (string) Number::currency((float) $line->unit_price, 'TRY', 'tr'),
                'status' => (string) $line->status,
                'statusLabel' => $this->statusLabel((string) $line->status),
                'externalStatus' => (string) $line->external_status,
                'vatRate' => $line->vat_rate,
                'commission' => $line->commission,
                // Eşleşmemiş satır: sipariş yine de tam olarak kaydedildi.
                'matched' => $line->variant_id !== null,
                'variantSku' => $line->variant_sku,
            ])
            ->all());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function packages(int $orderId): array
    {
        return array_values(DB::table('shipment_packages')
            ->where('order_id', $orderId)
            ->orderBy('id')
            ->get()
            ->map(fn (stdClass $package): array => [
                'id' => (int) $package->id,
                'remotePackageId' => (string) $package->remote_package_id,
                'cargoProvider' => $package->cargo_provider,
                // Kargo takip numarasi int64'u asar — uçtan uca string.
                'trackingNumber' => $package->tracking_number === null ? null : (string) $package->tracking_number,
                'trackingLink' => $package->tracking_link,
                'status' => (string) $package->status,
                'statusLabel' => $this->statusLabel((string) $package->status),
                'externalStatus' => (string) $package->external_status,
                'deci' => $package->deci,
                'shippedAt' => $this->dateTime($package->shipped_at),
                'deliveredAt' => $this->dateTime($package->delivered_at),
            ])
            ->all());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function history(int $orderId): array
    {
        return array_values(DB::table('order_status_history')
            ->where('order_id', $orderId)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get()
            ->map(fn (stdClass $entry): array => [
                'id' => (int) $entry->id,
                'fromStatus' => $entry->from_status,
                'toStatus' => (string) $entry->to_status,
                'occurredAt' => $this->dateTime($entry->occurred_at),
                'source' => (string) $entry->source,
            ])
            ->all());
    }

    private function lineCount(): Builder
    {
        return DB::table('order_lines')
            ->selectRaw('count(*)')
            ->whereColumn('order_lines.order_id', 'orders.id');
    }

    /**
     * @return array<string, mixed>
     */
    private function customer(mixed $encrypted): array
    {
        if (! is_string($encrypted) || $encrypted === '') {
            return [];
        }

        try {
            $decoded = json_decode(Crypt::decryptString($encrypted), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            // Bozuk ya da baska bir anahtarla sifrelenmis satir siparisi
            // gizlemez; yalnizca kisisel veri bolumu bos kalir.
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function maskedName(mixed $encrypted): ?string
    {
        return $this->mask($this->customer($encrypted));
    }

    /**
     * "Ayşe Yılmaz" -> "Ayşe Y." Listede ve detayda gorunen tek ad biçimi budur.
     *
     * @param  array<string, mixed>  $customer
     */
    private function mask(array $customer): ?string
    {
        $first = is_string($customer['firstName'] ?? null) ? trim($customer['firstName']) : '';
        $last = is_string($customer['lastName'] ?? null) ? trim($customer['lastName']) : '';

        if ($first === '' && $last === '') {
            return null;
        }

        return trim($first.' '.(mb_substr($last, 0, 1) === '' ? '' : mb_substr($last, 0, 1).'.'));
    }

    private function maskEmail(mixed $email): ?string
    {
        if (! is_string($email) || ! str_contains($email, '@')) {
            return null;
        }

        [$local, $domain] = explode('@', $email, 2);

        // Sabit genislik bilerek: yildiz sayisini yerel kismin uzunluguna
        // baglamak adresin uzunlugunu sizdirir.
        return mb_substr($local, 0, 1).'***@'.$domain;
    }

    private function maskPhone(mixed $phone): ?string
    {
        if (! is_string($phone)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $phone) ?? '';

        return mb_strlen($digits) < 4 ? null : str_repeat('*', mb_strlen($digits) - 4).mb_substr($digits, -4);
    }

    /**
     * @param  array<string, mixed>  $customer
     */
    private function addressPart(array $customer, string $key): ?string
    {
        $address = $customer['shippingAddress'] ?? null;

        if (! is_array($address)) {
            return null;
        }

        $value = $address[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * Para sunucuda bicimlenir — FRONTEND-PLAN §7.
     */
    private function money(mixed $totals, string $currency): ?string
    {
        $net = $this->totalsArray($totals)['net'] ?? null;

        return is_numeric($net) ? (string) Number::currency((float) $net, $currency, 'tr') : null;
    }

    /**
     * @return array<string, string>
     */
    private function totals(mixed $totals, string $currency): array
    {
        $formatted = [];

        foreach ($this->totalsArray($totals) as $key => $value) {
            if (is_numeric($value)) {
                $formatted[(string) $key] = (string) Number::currency((float) $value, $currency, 'tr');
            }
        }

        return $formatted;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function totalsArray(mixed $totals): array
    {
        if (is_array($totals)) {
            return $totals;
        }

        if (! is_string($totals) || $totals === '') {
            return [];
        }

        $decoded = json_decode($totals, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Tarih de sunucuda, Europe/Istanbul — FRONTEND-PLAN §7.
     */
    private function dateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value)->timezone('Europe/Istanbul')->format('d.m.Y H:i');
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
            fn (CanonicalOrderStatus $status): array => [
                'value' => $status->value,
                'label' => $this->statusLabel($status->value),
            ],
            CanonicalOrderStatus::cases(),
        );
    }
}
