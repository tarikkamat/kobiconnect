<?php

declare(strict_types=1);

namespace App\Http\Controllers\Channels;

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
 * Detay sayfasi BILEREK yok: karta tiklamak dogrudan kurulum cekmecesini acar.
 * Kurulu baglantilarin listesi de BURADA YOK; kart yalnizca kac baglanti
 * kurulu oldugunu bilir. Kimlik bilgileri prop'a HIC girmez.
 */
class AppStoreController extends Controller
{
    public function __construct(private readonly AppCatalog $catalog) {}

    public function index(): Response
    {
        Gate::authorize('viewAny', ChannelConnection::class);

        $installed = ChannelConnection::query()
            ->selectRaw('marketplace, count(*) as total')
            ->groupBy('marketplace')
            ->pluck('total', 'marketplace');

        return Inertia::render('channels/apps/index', [
            'apps' => array_map(
                static fn (array $app): array => [
                    ...$app,
                    'installed' => (int) ($installed[$app['code']] ?? 0),
                ],
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
