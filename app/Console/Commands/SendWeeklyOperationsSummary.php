<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ConnectionStatus;
use App\Mail\Digest\WeeklyOperationsSummary;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use stdClass;

final class SendWeeklyOperationsSummary extends Command
{
    protected $signature = 'email:weekly-ops-summary';

    protected $description = 'Haftalık operasyon özetini yetkili kullanıcılara gönderir.';

    public function handle(): int
    {
        // Kimlikleri geciyoruz, modelleri degil — SyncCommand ile ayni idiom.
        $tenants = Tenant::query()->pluck('id')->map(strval(...));

        tenancy()->runForMultiple($tenants, function (): void {
            rescue(fn () => $this->sendForCurrentTenant());
        });

        return self::SUCCESS;
    }

    private function sendForCurrentTenant(): void
    {
        $connectionsRaw = DB::table('channel_connections')->get();

        if ($connectionsRaw->isEmpty()) {
            return;
        }

        $connections = array_values($connectionsRaw->map(function (stdClass $conn): array {
            $statusEnum = ConnectionStatus::tryFrom($conn->status) ?? ConnectionStatus::Active;

            return [
                'name' => (string) $conn->name,
                'marketplace' => (string) $conn->marketplace,
                'status' => $statusEnum->label(),
            ];
        })->all());

        $lastWeekStart = CarbonImmutable::now('Europe/Istanbul')->subWeek()->startOfWeek();

        $failedSyncs = DB::table('sync_runs')
            ->where('started_at', '>=', $lastWeekStart->utc())
            ->where('status', 'failed')
            ->count();

        $rejectedProducts = DB::table('channel_operations')
            ->where('created_at', '>=', $lastWeekStart->utc())
            ->where('status', 'failed')
            ->where('entity_type', 'product')
            ->count();

        $webhookIssues = 0; // placeholder

        $data = [
            'connections' => $connections,
            'failedSyncs' => $failedSyncs,
            'rejectedProducts' => $rejectedProducts,
            'webhookIssues' => $webhookIssues,
        ];

        $recipients = User::query()
            ->whereHas('roles.permissions', function ($query) {
                $query->where('name', 'channels.manage');
            })
            ->get();

        foreach ($recipients as $user) {
            Mail::to($user)->queue(new WeeklyOperationsSummary($data));
        }
    }
}
