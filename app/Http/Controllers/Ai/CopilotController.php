<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ai;

use App\Ai\Agents\KobiConnectCopilotAgent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CopilotController extends Controller
{
    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'conversation_id' => ['nullable', 'string', 'max:36'],
        ]);

        $user = Auth::user();
        $agent = new KobiConnectCopilotAgent;

        if (! empty($validated['conversation_id'])) {
            $response = $agent->continue($validated['conversation_id'], as: $user)->prompt($validated['message']);
            $conversationId = $validated['conversation_id'];
        } else {
            $response = $agent->forUser($user)->prompt($validated['message']);
            $conversationId = $response->conversationId ?? null;
        }

        return response()->json([
            'conversation_id' => $conversationId,
            'response' => $response->text,
        ]);
    }

    public function conversations(): JsonResponse
    {
        $user = Auth::user();
        $table = config('ai.conversations.tables.conversations', 'agent_conversations');

        if (! DB::getSchemaBuilder()->hasTable($table)) {
            return response()->json(['conversations' => []]);
        }

        $conversations = DB::table($table)
            ->where('participant_id', $user?->id)
            ->latest('updated_at')
            ->take(20)
            ->get();

        return response()->json([
            'conversations' => $conversations,
        ]);
    }
}
