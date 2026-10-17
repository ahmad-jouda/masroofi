<?php

namespace App\Http\Controllers;

use App\Models\GroceryItem;
use App\Models\PartnerNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerNotificationController extends Controller
{
    /**
     * Poll for recent partner notifications and badge counters.
     */
    public function poll(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $workspaceId = $request->user()->workspace_id;

        // Unread partner notifications for current user (where someone else was the actor)
        $unreadQuery = PartnerNotification::with('actor')
            ->where('workspace_id', $workspaceId)
            ->where('actor_id', '!=', $userId)
            ->where('is_read', false);

        $unreadCount = $unreadQuery->count();

        // Recent notifications (up to 15) for the dropdown
        $recentNotifications = PartnerNotification::with('actor')
            ->where('workspace_id', $workspaceId)
            ->orderBy('created_at', 'desc')
            ->limit(15)
            ->get()
            ->map(function ($notif) use ($userId) {
                return [
                    'id' => $notif->id,
                    'type' => $notif->type,
                    'title' => $notif->title,
                    'message' => $notif->message,
                    'icon' => $notif->icon,
                    'is_read' => (bool) $notif->is_read,
                    'is_mine' => $notif->actor_id === $userId,
                    'actor_name' => $notif->actor?->name ?? 'الشريك',
                    'created_at_human' => $notif->created_at->diffForHumans(),
                    'created_at_iso' => $notif->created_at->toIso8601String(),
                ];
            });

        // Unbought grocery items count for navbar badge
        $pendingGroceryCount = GroceryItem::where('workspace_id', $workspaceId)
            ->active()
            ->where('is_bought', false)
            ->count();

        // Check if there are newly arrived notifications since the client's last_checked timestamp
        $lastChecked = $request->query('last_checked');
        $newNotifications = [];
        if ($lastChecked) {
            $newNotifications = PartnerNotification::with('actor')
                ->where('workspace_id', $workspaceId)
                ->where('actor_id', '!=', $userId)
                ->where('created_at', '>', $lastChecked)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(fn ($n) => [
                    'id' => $n->id,
                    'title' => $n->title,
                    'message' => $n->message,
                    'icon' => $n->icon,
                    'actor_name' => $n->actor?->name ?? 'الشريك',
                ]);
        }

        return response()->json([
            'success' => true,
            'unread_count' => $unreadCount,
            'pending_grocery_count' => $pendingGroceryCount,
            'notifications' => $recentNotifications,
            'new_notifications' => $newNotifications,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /**
     * Mark all partner notifications as read for current user.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $workspaceId = $request->user()->workspace_id;

        PartnerNotification::where('workspace_id', $workspaceId)
            ->where('actor_id', '!=', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديد جميع الإشعارات كمقروءة ✔️',
        ]);
    }
}
