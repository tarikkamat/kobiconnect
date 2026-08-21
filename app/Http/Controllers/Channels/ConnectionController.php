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
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Pazaryeri baglantilarinin yonetimi — kimlik bilgisi, saglik durumu, webhook
 * token'i. Vitrin ve okuma tarafi AppStoreController'dadir; burasi yalnizca
 * yazma islemleridir. Kimlik bilgileri sifreli kolonda durur ve arayuze
 * HIC DONMEZ.
 */
class ConnectionController extends Controller
{
    public function __construct(private readonly AppCatalog $catalog) {}

    public function store(ConnectionRequest $request, CheckConnectionHealth $health): RedirectResponse
    {
        Gate::authorize('create', ChannelConnection::class);

        $marketplace = (string) $request->validated('marketplace');

        // Lisans kapisi: kilitli bir uygulama magazada gorunur ama kurulamaz.
        // Kontrol BURADA, cunku arayuzdeki kilit yalnizca bir gostergedir.
        abort_unless(
            $this->catalog->isInstallable($marketplace) && $this->catalog->entitled($marketplace),
            403,
            'Bu uygulama planınıza dahil değil.',
        );

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
