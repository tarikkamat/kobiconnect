<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\License;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Panel route'larinin lisans kapisi — BACKEND-PLAN.md §3.2.
 *
 * - Lisans yok / askida / grace de bitmis  → 402, hicbir sey yapilamaz.
 * - Grace period icinde                    → salt-okunur: okuma serbest, yazma 402.
 *
 * Kota kontrolu burada *yapilmaz*; o karar Action seviyesindedir (CheckQuota).
 */
final class EnsureLicenseIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenant();

        if ($tenant === null) {
            return $next($request);
        }

        $license = License::query()->where('tenant_id', $tenant->getTenantKey())->first();

        if ($license === null || ! $license->hasAccess()) {
            abort(402, 'Aboneliğiniz aktif değil. Devam etmek için lütfen planınızı yenileyin.');
        }

        if ($license->isReadOnly() && ! $request->isMethodSafe()) {
            abort(402, 'Ödeme bekleniyor: hesabınız salt-okunur modda. Verileriniz duruyor, ödemenizi tamamladığınızda kaldığınız yerden devam edeceksiniz.');
        }

        return $next($request);
    }
}
