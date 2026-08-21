<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Actions\Team\InviteUser;
use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\UsageCounter;
use Illuminate\Http\Request;
use Illuminate\Support\Number;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lisans & kullanim — FRONTEND-PLAN §5.
 *
 * Bu ekran `license` middleware'i ile KORUNMAZ (bkz. routes/tenant/settings.php).
 * Suresi dolmus musteri panelin geri kalanindan 402 alir; buraya ulasamazsa
 * plani yenilemenin yolunu goremez ve hesabi kendi kendine kilitlenir. Bu
 * yuzden burada `$license` null veya suresi dolmus olabilir ve her ikisi de
 * normal durumdur.
 *
 * Odeme/faturalama entegrasyonu bu fazda YOK: plan yukseltme bir iletisim
 * baglantisidir.
 */
class LicenseController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const array STATUS_LABELS = [
        'active' => 'Aktif',
        'suspended' => 'Askıya alındı',
        'expired' => 'Süresi doldu',
        'cancelled' => 'İptal edildi',
    ];

    /**
     * Kota gostergeleri — %80 sarı, %100 kırmızı (FRONTEND-PLAN §5).
     *
     * @var array<string, string>
     */
    private const array QUOTA_LABELS = [
        'products.max' => 'Ürün',
        'orders.per_month' => 'Aylık sipariş',
        'channels.max' => 'Pazaryeri kanalı',
        'seats.max' => 'Kullanıcı',
    ];

    public function index(Request $request): Response
    {
        // Faturalama yalnizca Sahip'te (§4.3). Policy yok: tek bir izin
        // kontrolu icin bir dosya acmaya degmez.
        abort_unless((bool) $request->user()?->can('billing.manage'), 403);

        $tenant = tenant();
        $license = $tenant === null
            ? null
            : License::query()->with('plan')->where('tenant_id', $tenant->getTenantKey())->first();

        return Inertia::render('settings/license', [
            'license' => $license === null ? null : $this->licensePayload($license),
            'quotas' => $this->quotas($license),
            // Odeme entegrasyonu yok; plan yukseltme bir iletisim cagrisidir.
            'contactEmail' => (string) config('mail.from.address'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function licensePayload(License $license): array
    {
        $graceUntil = $license->grace_until;

        return [
            'plan' => $license->plan->name,
            'planCode' => $license->plan->code,
            'price' => (string) Number::currency((float) $license->plan->price, 'TRY', 'tr'),
            'billingPeriod' => $license->plan->billing_period->value,
            'status' => $license->status->value,
            'statusLabel' => self::STATUS_LABELS[$license->status->value],
            // Tarihler sunucuda, Europe/Istanbul — FRONTEND-PLAN §7.
            'startsAt' => $license->starts_at->timezone('Europe/Istanbul')->format('d.m.Y'),
            'endsAt' => $license->ends_at?->timezone('Europe/Istanbul')->format('d.m.Y'),
            'graceUntil' => $graceUntil?->timezone('Europe/Istanbul')->format('d.m.Y'),
            'inGracePeriod' => $license->inGracePeriod(),
            'graceDaysLeft' => $license->inGracePeriod() && $graceUntil !== null
                ? (int) ceil(now()->diffInDays($graceUntil, false))
                : null,
            'readOnly' => $license->isReadOnly(),
            'hasAccess' => $license->hasAccess(),
        ];
    }

    /**
     * @return list<array{key: string, label: string, used: int, max: int|null, ratio: float|null, level: string}>
     */
    private function quotas(?License $license): array
    {
        $quotas = [];

        foreach (self::QUOTA_LABELS as $metric => $label) {
            $max = $license?->limit($metric);
            $used = $this->usage($license, $metric);
            $ratio = $max === null || $max === 0 ? null : $used / $max;

            $quotas[] = [
                'key' => $metric,
                'label' => $label,
                'used' => $used,
                'max' => $max,
                'ratio' => $ratio,
                // Esik sunucuda belirlenir; iki yerde yuzde mantigi tutulmaz.
                'level' => match (true) {
                    $ratio === null => 'unlimited',
                    $ratio >= 1.0 => 'critical',
                    $ratio >= 0.8 => 'warning',
                    default => 'ok',
                },
            ];
        }

        return $quotas;
    }

    private function usage(?License $license, string $metric): int
    {
        // Koltuk canli sayilir: `usage_counters` koltuk satiri kayit akisinda
        // artirilmiyor, tek dogruluk kaynagi `users` tablosu (bkz. InviteUser).
        if ($metric === InviteUser::SEAT_METRIC) {
            return InviteUser::occupiedSeats();
        }

        return $license === null ? 0 : UsageCounter::valueFor($license->tenant_id, $metric);
    }
}
