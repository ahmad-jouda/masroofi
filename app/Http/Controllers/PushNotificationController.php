<?php

namespace App\Http\Controllers;

use App\Models\DebtHarvestLog;
use App\Models\GoalHarvestLog;
use App\Models\PartnerNotification;
use App\Models\Transaction;
use App\Models\UserSetting;
use App\Services\WebPushService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushNotificationController extends Controller
{
    /**
     * Return public VAPID key for frontend subscription.
     */
    public function publicKey(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'public_key' => WebPushService::getPublicKey(),
        ]);
    }

    /**
     * Store Web Push subscription from client phone/browser.
     */
    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => 'required|string',
            'keys' => 'required|array',
            'keys.p256dh' => 'required|string',
            'keys.auth' => 'required|string',
            'content_encoding' => 'nullable|string',
        ]);

        $user = $request->user();

        $sub = WebPushService::subscribe($user, $validated, $request->userAgent());

        // Send a welcome confirmation push
        try {
            WebPushService::sendNotificationToUser(
                $user,
                '🔔 تم تفعيل إشعارات مصروفي برو',
                'ستصلك تنبيهات فورية بكل ما يتم تسجيله في ميزانية العائلة لحظة بلحظة 🚀',
                asset('icons/icon-192.png'),
                '/#tab-dashboard',
                'welcome-push'
            );
        } catch (\Throwable $e) {
            // Keep going even if test dispatch has delay
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تفعيل إشعارات الهاتف بنجاح 🔔',
            'subscription_id' => $sub->id,
        ]);
    }

    /**
     * Unsubscribe a client device.
     */
    public function unsubscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => 'required|string',
        ]);

        WebPushService::unsubscribe($request->user(), $validated['endpoint']);

        return response()->json([
            'success' => true,
            'message' => 'تم إلغاء تفعيل الإشعارات لهذا الجهاز',
        ]);
    }

    /**
     * Send an instant test push notification to user's registered devices.
     */
    public function sendTestPush(Request $request): JsonResponse
    {
        $user = $request->user();
        $subsCount = $user->pushSubscriptions()->count();

        if ($subsCount === 0) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على أي هاتف أو متصفح مسجّل للإشعارات بعد. يرجى الضغط أولاً على «تفعيل إشعارات الهاتف» والسماح بالإذن.',
            ], 422);
        }

        $sent = WebPushService::sendNotificationToUser(
            $user,
            '🔔 تجربة إشعار مصروفي برو 🚀',
            'مبروك! تم استلام الإشعار بنجاح. هاتفك الآن مربوط ومستعد لتلقي تنبيهات المصاريف والمقاضي والفوائض لحظياً.',
            asset('icons/icon-192.png'),
            '/#tab-dashboard',
            'test-push-'.time()
        );

        return response()->json([
            'success' => true,
            'sent_count' => $sent,
            'message' => "تم إرسال إشعار تجريبي فوري بنجاح إلى {$sent} من أجهزتك 📲",
        ]);
    }

    /**
     * Trigger an evening surplus reminder for the current user if daily allowance remains.
     */
    public function checkEveningSurplusReminder(Request $request): JsonResponse
    {
        $user = $request->user();
        $workspaceId = $user->workspace_id;
        $today = Carbon::today()->toDateString();

        // Check if user already harvested surplus today
        $debtHarvestedToday = DebtHarvestLog::whereHas('debt', fn ($q) => $q->where('workspace_id', $workspaceId))
            ->whereDate('date', $today)
            ->exists();

        $goalHarvestedToday = GoalHarvestLog::whereHas('goal', fn ($q) => $q->where('workspace_id', $workspaceId))
            ->whereDate('date', $today)
            ->exists();

        if ($debtHarvestedToday || $goalHarvestedToday) {
            return response()->json([
                'success' => false,
                'reason' => 'surplus_already_harvested',
                'message' => 'تم حصاد فائض اليوم بالفعل.',
            ]);
        }

        // Check if reminder was already sent today
        $reminderAlreadySent = PartnerNotification::where('workspace_id', $workspaceId)
            ->where('actor_id', $user->id)
            ->where('type', 'evening_surplus_reminder')
            ->whereDate('created_at', $today)
            ->exists();

        if ($reminderAlreadySent) {
            return response()->json([
                'success' => false,
                'reason' => 'reminder_already_sent_today',
            ]);
        }

        // Calculate today's spent vs safe daily
        $todaySpent = (float) Transaction::where('workspace_id', $workspaceId)
            ->where('type', 'expense')
            ->whereDate('date', $today)
            ->sum('amount');

        $settings = UserSetting::where('workspace_id', $workspaceId)->first();
        $currency = $settings->currency ?? 'ILS';

        // Calculate a safe estimate or take remaining
        $todayRemaining = max(0, round(75.0 - $todaySpent, 0)); // baseline fallback or calculated

        if ($todayRemaining < 10) {
            return response()->json([
                'success' => false,
                'reason' => 'no_significant_surplus',
            ]);
        }

        $title = '✨ تنبيه مسائي: حصاد الفائض اليومي';
        $message = "تبقّى في مخصصك اليوم حوالي {$todayRemaining} {$currency}! هل تود إرسالها الآن لحصالة الأهداف أو سداد الديون؟";

        // Save in notification center
        $notif = PartnerNotification::create([
            'workspace_id' => $workspaceId,
            'actor_id' => $user->id,
            'type' => 'evening_surplus_reminder',
            'title' => $title,
            'message' => $message,
            'icon' => '✨',
            'is_read' => false,
        ]);

        // Send Push to user
        WebPushService::sendNotificationToUser(
            $user,
            $title,
            $message,
            asset('icons/icon-192.png'),
            '/#tab-dashboard',
            'evening-surplus-'.$today
        );

        return response()->json([
            'success' => true,
            'message' => 'تم إرسال تنبيه الفائض المسائي بنجاح ✨',
            'notification' => $notif,
        ]);
    }
}
