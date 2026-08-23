<?php

declare(strict_types=1);

namespace App\Http\Controllers\Logistics;

use App\Actions\Logistics\Ai\AuditCarrierDesiOvercharges;
use App\Actions\Logistics\Ai\RouteOrderShipment;
use App\Actions\Logistics\Ai\ScoreOrderReturnRisk;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ShipmentPackage;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiLogisticsController extends Controller
{
    public function auditDesi(Request $request, AuditCarrierDesiOvercharges $auditor): JsonResponse
    {
        $from = $request->filled('from') ? CarbonImmutable::parse((string) $request->input('from'))->startOfDay() : null;
        $to = $request->filled('to') ? CarbonImmutable::parse((string) $request->input('to'))->endOfDay() : null;
        $connectionId = $request->filled('connection') ? (int) $request->input('connection') : null;

        $query = ShipmentPackage::with(['order.lines.variant', 'order.connection']);
        if ($from && $to) {
            $query->whereHas('order', function ($q) use ($from, $to, $connectionId) {
                $q->whereBetween('placed_at', [$from, $to])
                    ->when($connectionId !== null, fn ($sq) => $sq->where('connection_id', $connectionId));
            });
        } elseif ($connectionId !== null) {
            $query->whereHas('order', fn ($q) => $q->where('connection_id', $connectionId));
        }

        $packages = $query->latest('id')->take(50)->get();
        $result = $auditor($packages);

        return response()->json([
            'success' => true,
            'audit' => $result,
        ]);
    }

    public function scoreRisk(Order $order, ScoreOrderReturnRisk $scorer): JsonResponse
    {
        $order->loadMissing(['lines.variant', 'customer']);
        $result = $scorer($order);

        return response()->json([
            'success' => true,
            'risk_assessment' => $result,
        ]);
    }

    public function routeCarrier(Request $request, Order $order, RouteOrderShipment $router): JsonResponse
    {
        $validated = $request->validate([
            'package_desi' => ['nullable', 'numeric', 'min:0.1'],
        ]);

        $result = $router($order, (float) ($validated['package_desi'] ?? 1.5));

        return response()->json([
            'success' => true,
            'routing' => $result,
        ]);
    }
}
