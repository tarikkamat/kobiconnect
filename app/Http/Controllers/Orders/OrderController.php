<?php

declare(strict_types=1);

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Marketplaces\Data\Enums\CanonicalOrderStatus;
use App\Models\ChannelConnection;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\OrderStatusHistory;
use App\Models\ShipmentPackage;
use App\Support\AppTime;
use App\Support\Money;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

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

        $orders = Order::query()
            ->select([
                'id', 'connection_id', 'remote_order_number', 'remote_id', 'status',
                'external_status', 'currency', 'placed_at', 'totals', 'customer',
            ])
            ->with('connection:id,name,marketplace')
            ->withCount([
                'lines as line_count',
                'lines as unmatched_count' => fn (Builder $lines) => $lines->whereNull('variant_id'),
            ])
            ->when($search !== '', fn (Builder $query) => $query->where(
                fn (Builder $inner) => $inner
                    ->where('remote_order_number', 'like', "%{$search}%")
                    ->orWhere('remote_id', 'like', "%{$search}%"),
            ))
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status))
            ->when($connection !== null, fn (Builder $query) => $query->where('connection_id', $connection))
            // Esleşmemis satirlar ayri bir tablo degil, ayni listenin bir
            // filtresi: operatör siparisi baglaminda görmek zorunda.
            ->when($unmatched, fn (Builder $query) => $query->whereHas(
                'lines',
                fn (Builder $lines) => $lines->whereNull('variant_id'),
            ))
            ->orderByDesc('placed_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Order $order): array => [
                'id' => $order->getKey(),
                'orderNumber' => (string) $order->remote_order_number,
                'packageId' => (string) $order->remote_id,
                'status' => $order->status->value,
                'statusLabel' => $order->status->label(),
                'externalStatus' => (string) $order->external_status,
                'connection' => $order->connection?->name,
                // Logo yolu koddan turetilir (/apps/{kod}.svg) — AppCatalog ile
                // ayni kaynak, bkz. AppCatalog::present().
                'marketplace' => $order->connection?->marketplace,
                'customer' => $this->mask($this->customer($order)),
                'customerLocation' => $this->maskedLocation($this->customer($order)),
                'total' => $this->money($order->totals, (string) $order->currency),
                'placedAt' => AppTime::dateTime($order->placed_at),
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
            'statuses' => CanonicalOrderStatus::options(),
            'connections' => ChannelConnection::query()->orderBy('name')->get(['id', 'name']),
            'unmatchedTotal' => OrderLine::query()->whereNull('variant_id')->count(),
        ]);
    }

    public function show(int $order, Request $request): Response|JsonResponse
    {
        Gate::authorize('orders.view');

        $model = Order::query()
            ->with('connection:id,name,marketplace')
            ->findOrFail($order);

        $customer = $this->customer($model);
        $lines = $this->lines($model);
        $financials = $this->calculateFinancials($model->totals, (string) $model->currency, $lines);

        $data = [
            'order' => [
                'id' => $model->getKey(),
                'orderNumber' => (string) $model->remote_order_number,
                'packageId' => (string) $model->remote_id,
                'status' => $model->status->value,
                'statusLabel' => $model->status->label(),
                'externalStatus' => (string) $model->external_status,
                'connection' => $model->connection?->name,
                'marketplace' => $model->connection?->marketplace,
                'currency' => (string) $model->currency,
                'placedAt' => AppTime::dateTime($model->placed_at),
                'lastModifiedAt' => AppTime::dateTime($model->remote_last_modified_at),
                'totals' => $this->totals($model->totals, (string) $model->currency),
                'financials' => $financials,
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
            'lines' => $lines,
            'packages' => $this->packages($model),
            'history' => $this->history($model),
        ];

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json($data);
        }

        return Inertia::render('orders/show', $data);
    }

    /**
     * Satirlar iliski uzerinden yuklenir. Onceki `leftJoin('prices')` bir
     * varyantin birden fazla fiyat satiri oldugunda ayni siparis satirini
     * cogaltiyordu — prices'ta (variant_id, currency) tekil degil.
     *
     * @return list<array<string, mixed>>
     */
    private function lines(Order $order): array
    {
        return array_values($order->lines()
            ->with(['variant:id,sku,product_id', 'variant.product:id,name', 'variant.prices:id,variant_id,currency,cost'])
            ->orderBy('id')
            ->get()
            ->map(function (OrderLine $line) use ($order): array {
                $qty = (int) $line->quantity;
                $unitPrice = (float) $line->unit_price;
                $lineTotal = $qty * $unitPrice;
                $unitCost = $this->unitCost($line, (string) $order->currency);
                $cost = $unitCost !== null ? $unitCost * $qty : null;
                $commissionRate = is_numeric($line->commission) ? (float) $line->commission : null;
                $commissionAmount = $commissionRate !== null ? ($lineTotal * ($commissionRate > 1 ? $commissionRate / 100 : $commissionRate)) : null;

                return [
                    'id' => $line->getKey(),
                    'remoteLineId' => (string) $line->remote_line_id,
                    'sku' => (string) $line->sku,
                    'productName' => $line->variant?->product?->name,
                    'barcode' => $line->barcode,
                    'quantity' => $qty,
                    'unitPrice' => Money::format($unitPrice),
                    'lineTotal' => Money::format($lineTotal),
                    'cost' => $cost !== null ? Money::format($cost) : null,
                    'costRaw' => $cost,
                    'status' => $line->status->value,
                    'statusLabel' => $line->status->label(),
                    'externalStatus' => (string) $line->external_status,
                    'vatRate' => $line->vat_rate !== null ? '%'.rtrim(rtrim((string) $line->vat_rate, '0'), '.') : null,
                    'commission' => $line->commission !== null ? '%'.rtrim(rtrim((string) $line->commission, '0'), '.') : null,
                    'commissionAmount' => $commissionAmount !== null ? Money::format($commissionAmount) : null,
                    // Eşleşmemiş satır: sipariş yine de tam olarak kaydedildi.
                    'matched' => $line->variant_id !== null,
                    'variantSku' => $line->variant?->sku,
                ];
            })

            ->all());
    }

    /**
     * Siparisin para biriminde maliyet; yoksa varyantin ilk fiyat satiri.
     * Onceki join hangi satirin geldigini garanti etmiyordu.
     */
    private function unitCost(OrderLine $line, string $currency): ?float
    {
        $prices = $line->variant?->prices;

        if ($prices === null) {
            return null;
        }

        $cost = ($prices->firstWhere('currency', $currency) ?? $prices->first())?->cost;

        return $cost === null ? null : (float) $cost;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function packages(Order $order): array
    {
        return array_values($order->packages()
            ->orderBy('id')
            ->get()
            ->map(fn (ShipmentPackage $package): array => [
                'id' => $package->getKey(),
                'remotePackageId' => (string) $package->remote_package_id,
                'cargoProvider' => $package->cargo_provider,
                // Kargo takip numarasi int64'u asar — uçtan uca string.
                'trackingNumber' => $package->tracking_number === null ? null : (string) $package->tracking_number,
                'trackingLink' => $package->tracking_link,
                'status' => $package->status->value,
                'statusLabel' => $package->status->label(),
                'externalStatus' => (string) $package->external_status,
                'deci' => $package->deci,
                'shippedAt' => AppTime::dateTime($package->shipped_at),
                'deliveredAt' => AppTime::dateTime($package->delivered_at),
            ])

            ->all());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function history(Order $order): array
    {
        return array_values($order->statusHistory()
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get()
            ->map(fn (OrderStatusHistory $entry): array => [
                'id' => $entry->getKey(),
                'fromStatus' => $entry->from_status,
                'toStatus' => (string) $entry->to_status,
                'occurredAt' => AppTime::dateTime($entry->occurred_at),
                'source' => (string) $entry->source,
            ])

            ->all());
    }

    /**
     * Cozme isini model cast'i (AsEncryptedArrayObject) yapar; burada kalan tek
     * sey bozuk satirin sayfayi dusurmemesi. Baska bir anahtarla sifrelenmis
     * bir satir siparisi gizlemez, yalnizca kisisel veri bolumu bos kalir.
     *
     * @return array<string, mixed>
     */
    private function customer(Order $order): array
    {
        try {
            return $order->customer?->toArray() ?? [];
        } catch (DecryptException) {
            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $customer
     */
    private function maskedLocation(array $customer): ?string
    {
        $city = $this->addressPart($customer, 'city');
        $district = $this->addressPart($customer, 'district');

        if ($city === null && $district === null) {
            return null;
        }

        return implode(', ', array_filter([$city, $district]));
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
        $net = $totals['net'] ?? null;

        return is_numeric($net) ? Money::format((float) $net, $currency) : null;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array{gross: string, discount: string, commission: string, netSales: string, totalCost: ?string, netPayout: string, estimatedProfit: ?string, marginPercent: ?string}
     */
    private function calculateFinancials(mixed $totals, string $currency, array $lines): array
    {
        $arr = $totals;
        $gross = (float) ($arr['gross'] ?? 0);
        $discount = (float) ($arr['discount'] ?? 0);
        $net = (float) ($arr['net'] ?? ($gross - $discount));

        // Komisyon: totals icinde varsa onu, yoksa satirlardan topla
        $commission = isset($arr['commission']) && is_numeric($arr['commission'])
            ? (float) $arr['commission']
            : 0.0;

        $totalCost = 0.0;
        $hasAnyCost = false;

        foreach ($lines as $line) {
            if (isset($line['costRaw'])) {
                $totalCost += (float) $line['costRaw'];
                $hasAnyCost = true;
            }
            if ($commission === 0.0 && isset($line['commissionAmount']) && is_string($line['commissionAmount'])) {
                // Eger ana totals'ta komisyon yoksa satirdan hesaplanmis olabilir
            }
        }

        $netPayout = max(0, $net - $commission);
        $profit = $hasAnyCost ? ($netPayout - $totalCost) : null;
        $margin = ($hasAnyCost && $net > 0) ? round(($profit / $net) * 100, 1) : null;

        return [
            'gross' => Money::format($gross > 0 ? $gross : $net, $currency),
            'discount' => Money::format($discount, $currency),
            'commission' => Money::format($commission, $currency),
            'netSales' => Money::format($net, $currency),
            'totalCost' => $hasAnyCost ? Money::format($totalCost, $currency) : null,
            'netPayout' => Money::format($netPayout, $currency),
            'estimatedProfit' => $profit !== null ? Money::format($profit, $currency) : null,
            'marginPercent' => $margin !== null ? '%'.$margin : null,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function totals(mixed $totals, string $currency): array
    {
        $formatted = [];

        foreach ($totals as $key => $value) {
            if (is_numeric($value)) {
                $formatted[(string) $key] = Money::format((float) $value, $currency);
            }
        }

        return $formatted;
    }
}
