<?php

declare(strict_types=1);

namespace App\Http\Controllers\Logistics;

use App\Actions\Logistics\Ai\AuditCarrierDesiOvercharges;
use App\Actions\Logistics\Ai\RouteOrderShipment;
use App\Actions\Logistics\Ai\ScoreOrderReturnRisk;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiLogisticsController extends Controller
{
    public function auditDesi(AuditCarrierDesiOvercharges $auditor): JsonResponse
    {
        $result = $auditor();

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
