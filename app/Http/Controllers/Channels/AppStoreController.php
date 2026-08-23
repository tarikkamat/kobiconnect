<?php

declare(strict_types=1);

namespace App\Http\Controllers\Channels;

use App\Enums\ConnectionStatus;
use App\Http\Controllers\Controller;
use App\Models\ChannelConnection;
use App\Support\AppCatalog;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Uygulama magazasi — tek ekran vitrin.
 *
 * Vitrin `config/apps.php` + surucu kaydindan gelir (App\Support\AppCatalog).
 * Kart uzerinden mevcut baglantilar yonetilebilir veya yeni baglanti acilabilir.
 * Kimlik bilgileri prop'a guvenli sekilde (sirlar filtrelenerek) iletilir.
 */
class AppStoreController extends Controller
{
    public function __construct(private readonly AppCatalog $catalog) {}

    public function index(): Response
    {
        Gate::authorize('viewAny', ChannelConnection::class);

        $connections = ChannelConnection::query()
            ->latest('id')
            ->get()
            ->groupBy('marketplace');

        return Inertia::render('channels/apps/index', [
            'apps' => array_map(
                function (array $app) use ($connections): array {
                    $appConnections = ($connections->get($app['code']) ?? collect())->map(function (ChannelConnection $conn) use ($app): array {
                        $rawCreds = (array) ($conn->credentials?->toArray() ?? []);
                        $nonSecrets = [];
                        $hasSecrets = false;

                        $secretFieldNames = collect($app['fields'] ?? [])
                            ->where('type', 'secret')
                            ->pluck('name')
                            ->all();

                        foreach ($rawCreds as $key => $val) {
                            if (in_array($key, $secretFieldNames, true)) {
                                if (! empty($val)) {
                                    $hasSecrets = true;
                                }
                            } else {
                                $nonSecrets[$key] = $val;
                            }
                        }

                        $statusLabel = match ($conn->status) {
                            ConnectionStatus::Active => 'Aktif',
                            ConnectionStatus::Paused => 'Duraklatıldı',
                            ConnectionStatus::Error => 'Hata',
                        };

                        return [
                            'id' => $conn->id,
                            'name' => $conn->name,
                            'marketplace' => $conn->marketplace,
                            'marketplaceLabel' => $app['name'],
                            'status' => $conn->status->value,
                            'statusLabel' => $statusLabel,
                            'capabilities' => $app['capabilities'] ?? [],
                            'lastHealthCheckAt' => $conn->last_health_check_at?->diffForHumans() ?? $conn->last_health_check_at?->format('d.m.Y H:i'),
                            'lastHealthError' => is_array($conn->settings) ? ($conn->settings['last_health_error'] ?? null) : null,
                            'webhookUrl' => config('marketplaces.webhook_base_url')."/{$conn->marketplace}/{$conn->webhook_token}",
                            'sellerId' => (string) ($conn->external_seller_id ?? ''),
                            'credentials' => [
                                'values' => $nonSecrets,
                                'secretsStored' => $hasSecrets,
                            ],
                        ];
                    })->values()->all();

                    return [
                        ...$app,
                        'installed' => count($appConnections),
                        'connections' => $appConnections,
                    ];
                },
                $this->catalog->all(),
            ),
            'categories' => array_map(
                static fn (string $value, string $label): array => ['value' => $value, 'label' => $label],
                array_keys($this->catalog->categories()),
                array_values($this->catalog->categories()),
            ),
        ]);
    }
}
