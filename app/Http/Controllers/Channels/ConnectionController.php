<?php

declare(strict_types=1);

namespace App\Http\Controllers\Channels;

use App\Actions\Channels\CheckConnectionHealth;
use App\Enums\ConnectionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Channels\ConnectionRequest;
use App\Models\ChannelConnection;
use App\Support\AppCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pazaryeri baglantilarinin yonetimi — kimlik bilgisi, saglik durumu, webhook
 * token'i. Vitrin AppStoreController'dadir; liste ve yonetim buradadir.
 * Kimlik bilgileri sifreli kolonda durur ve arayuze HIC DONMEZ.
 */
class ConnectionController extends Controller
{
    public function __construct(private readonly AppCatalog $catalog) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ChannelConnection::class);

        $marketplaceFilter = $request->query('marketplace');
        $statusFilter = $request->query('status');
        $search = $request->query('search');

        $query = ChannelConnection::query()->latest();

        if (is_string($marketplaceFilter) && $marketplaceFilter !== '' && $marketplaceFilter !== 'all') {
            $query->where('marketplace', $marketplaceFilter);
        }

        if (is_string($statusFilter) && $statusFilter !== '' && $statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        if (is_string($search) && trim($search) !== '') {
            $term = '%'.trim($search).'%';
            $query->where(function ($q) use ($term): void {
                $q->where('name', 'ilike', $term)
                    ->orWhereRaw("credentials->>'seller_id' ilike ?", [$term])
                    ->orWhereRaw("credentials->>'merchant_id' ilike ?", [$term]);
            });
        }

        $connections = $query->get()->map(function (ChannelConnection $connection): array {
            $app = $this->catalog->find($connection->marketplace);
            $fields = $app['fields'] ?? [];
            $secretKeys = array_column(
                array_filter($fields, static fn (array $f): bool => $f['type'] === 'secret'),
                'name',
            );

            $rawCredentials = $connection->credentials->toArray();
            $safeValues = [];
            $secretsStored = [];

            foreach ($rawCredentials as $k => $v) {
                if (in_array($k, $secretKeys, true)) {
                    $secretsStored[$k] = ! empty($v);
                } else {
                    $safeValues[$k] = $v;
                }
            }

            return [
                'id' => $connection->id,
                'name' => $connection->name,
                'marketplace' => $connection->marketplace,
                'marketplaceLabel' => $app['name'] ?? $connection->marketplace,
                'logo' => $app['logo'] ?? null,
                'logoScale' => $app['logoScale'] ?? 1,
                'logoDarkInvert' => $app['logoDarkInvert'] ?? false,
                'status' => $connection->status->value,
                'statusLabel' => $connection->status->label(),
                'sellerId' => $connection->credentials['seller_id'] ?? $connection->credentials['merchant_id'] ?? null,
                'lastHealthCheckAt' => $connection->last_health_check_at?->diffForHumans(),
                'lastHealthError' => $connection->settings['last_health_error'] ?? null,
                'credentials' => [
                    'values' => $safeValues,
                    'secretsStored' => $secretsStored,
                ],
                'createdAt' => $connection->created_at?->format('d.m.Y H:i'),
            ];
        });

        $marketplaces = array_values(array_map(
            static fn (array $app): array => [
                'value' => $app['code'],
                'label' => $app['name'],
                'logo' => $app['logo'],
                'logoScale' => $app['logoScale'],
                'logoDarkInvert' => $app['logoDarkInvert'],
                'capabilities' => $app['capabilities'],
                'fields' => $app['fields'],
                'available' => $app['available'],
            ],
            array_filter($this->catalog->all(), static fn (array $app): bool => $app['available']),
        ));

        $statuses = array_map(
            static fn (ConnectionStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            ConnectionStatus::cases(),
        );

        return Inertia::render('channels/connections/index', [
            'connections' => $connections,
            'marketplaces' => $marketplaces,
            'statuses' => $statuses,
            'filters' => [
                'marketplace' => $marketplaceFilter,
                'status' => $statusFilter,
                'search' => $search,
            ],
        ]);
    }

    public function store(ConnectionRequest $request, CheckConnectionHealth $health): RedirectResponse
    {
        Gate::authorize('create', ChannelConnection::class);

        // Surucusu olmayan uygulama kurulamaz; ConnectionRequest de ayni
        // listeye bakar, bu kontrol o kural atlanirsa diye burada.
        abort_unless($this->catalog->isInstallable((string) $request->validated('marketplace')), 403);

        $connection = ChannelConnection::create([
            ...$request->connectionAttributes(),
            // Tahmin edilemez, baglanti basina, iptal edilebilir opak token —
            // BACKEND-PLAN.md §2.2. Trendyol'a kaydi bu fazda YAPILMAZ.
            'webhook_token' => Str::random(48),
            'status' => ConnectionStatus::Paused,
        ]);

        $result = $health->handle($connection);

        Inertia::flash('toast', $result['ok']
            ? ['type' => 'success', 'message' => 'Bağlantı eklendi ve doğrulandı.']
            : ['type' => 'error', 'message' => 'Bağlantı eklendi ama doğrulanamadı: '.$result['message']]);

        return back();
    }

    public function update(ConnectionRequest $request, ChannelConnection $connection, CheckConnectionHealth $health): RedirectResponse
    {
        Gate::authorize('update', $connection);

        $connection->update($request->connectionAttributes());

        $result = $health->handle($connection);

        Inertia::flash('toast', $result['ok']
            ? ['type' => 'success', 'message' => 'Bağlantı güncellendi ve doğrulandı.']
            : ['type' => 'error', 'message' => 'Bağlantı güncellendi ama doğrulanamadı: '.$result['message']]);

        return back();
    }

    /**
     * Elle "şimdi test et". Zamanlanmis saglik kontrolu bu fazda yok.
     */
    public function health(ChannelConnection $connection, CheckConnectionHealth $check): RedirectResponse
    {
        Gate::authorize('update', $connection);

        $result = $check->handle($connection);

        Inertia::flash('toast', [
            'type' => $result['ok'] ? 'success' : 'error',
            'message' => $result['message'],
        ]);

        return back();
    }

    public function destroy(ChannelConnection $connection): RedirectResponse
    {
        Gate::authorize('delete', $connection);

        $connection->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Bağlantı silindi.']);

        return back();
    }
}
