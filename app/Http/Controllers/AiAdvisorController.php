<?php

namespace App\Http\Controllers;

use App\Models\AiChatMessage;
use App\Services\FinancialAdvisorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiAdvisorController extends Controller
{
    /**
     * Get recent AI chat history and current financial snapshot.
     */
    public function getMessages(): JsonResponse
    {
        $workspaceId = auth()->user()?->workspace_id ?? 1;

        $messages = AiChatMessage::where('workspace_id', $workspaceId)
            ->latest('id')
            ->take(30)
            ->get()
            ->reverse()
            ->values();

        $snapshot = FinancialAdvisorService::getFinancialSnapshot($workspaceId);

        return response()->json([
            'success' => true,
            'messages' => $messages,
            'snapshot' => [
                'currency_symbol' => $snapshot['currency_symbol'],
                'currency_name' => $snapshot['currency_name'],
                'app_spendable_balance' => $snapshot['app_spendable_balance'],
                'safe_daily' => $snapshot['safe_daily'],
                'days_remaining' => $snapshot['days_remaining'],
                'today_remaining' => $snapshot['today_remaining'],
            ],
        ]);
    }

    /**
     * Send a question to the AI Financial Advisor.
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|min:1|max:1000',
        ]);

        $workspaceId = auth()->user()?->workspace_id ?? 1;
        $userId = auth()->id();
        $userText = trim($request->input('message'));

        // 1. Store User Message
        $userMsg = AiChatMessage::create([
            'workspace_id' => $workspaceId,
            'user_id' => $userId,
            'role' => 'user',
            'message' => $userText,
        ]);

        // 2. Query AI Financial Advisor Engine
        $advice = FinancialAdvisorService::ask($workspaceId, $userId, $userText);

        // 3. Store Assistant Message
        $assistantMsg = AiChatMessage::create([
            'workspace_id' => $workspaceId,
            'user_id' => null,
            'role' => 'assistant',
            'message' => $advice['message'],
            'verdict' => $advice['verdict'],
            'context_data' => $advice['context_data'] ?? null,
        ]);

        $snapshot = FinancialAdvisorService::getFinancialSnapshot($workspaceId);

        return response()->json([
            'success' => true,
            'user_message' => $userMsg,
            'assistant_message' => $assistantMsg,
            'snapshot' => [
                'currency_symbol' => $snapshot['currency_symbol'],
                'currency_name' => $snapshot['currency_name'],
                'app_spendable_balance' => $snapshot['app_spendable_balance'],
                'safe_daily' => $snapshot['safe_daily'],
                'days_remaining' => $snapshot['days_remaining'],
            ],
        ]);
    }

    /**
     * Clear AI chat conversation history for the current workspace.
     */
    public function clearChat(): JsonResponse
    {
        $workspaceId = auth()->user()?->workspace_id ?? 1;

        AiChatMessage::where('workspace_id', $workspaceId)->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم مسح سجل المحادثة بنجاح.',
        ]);
    }
}
