<?php

declare(strict_types=1);

namespace App\Http\Controllers\Channels;

use App\Actions\Sync\Ai\HealFailedOperation;
use App\Http\Controllers\Controller;
use App\Models\ChannelOperation;
use Illuminate\Http\JsonResponse;

class AiSelfHealingController extends Controller
{
    public function heal(ChannelOperation $operation, HealFailedOperation $healer): JsonResponse
    {
        $result = $healer($operation);

        return response()->json($result);
    }
}
