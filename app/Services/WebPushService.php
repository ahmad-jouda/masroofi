<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushService
{
    /**
     * Retrieve the public VAPID key to share with frontend client for subscription.
     */
    public static function getPublicKey(): string
    {
        return (string) config('services.webpush.vapid_public_key', 'BMqew5VNSqT4zM3FihrpaIFTFPe0_cEd5elg5RlTxMuA3d7-6NCFvT3P_0ayY_6OB1_QMinLXKIPz9U7rHtpTPc');
    }

    /**
     * Store or update a Web Push subscription for a user.
     */
    public static function subscribe(User $user, array $subscriptionData, ?string $userAgent = null): PushSubscription
    {
        $endpoint = (string) ($subscriptionData['endpoint'] ?? '');
        $p256dh = (string) ($subscriptionData['keys']['p256dh'] ?? ($subscriptionData['public_key'] ?? ''));
        $auth = (string) ($subscriptionData['keys']['auth'] ?? ($subscriptionData['auth_token'] ?? ''));
        $encoding = (string) ($subscriptionData['content_encoding'] ?? 'aes128gcm');

        if (empty($endpoint) || empty($p256dh) || empty($auth)) {
            throw new \InvalidArgumentException('Invalid Web Push subscription payload.');
        }

        $hash = hash('sha256', $endpoint);

        return PushSubscription::updateOrCreate(
            [
                'user_id' => $user->id,
                'endpoint_hash' => $hash,
            ],
            [
                'endpoint' => $endpoint,
                'public_key' => $p256dh,
                'auth_token' => $auth,
                'content_encoding' => $encoding,
                'user_agent' => $userAgent ?? request()->userAgent(),
            ]
        );
    }

    /**
     * Remove a Web Push subscription.
     */
    public static function unsubscribe(User $user, string $endpoint): bool
    {
        $hash = hash('sha256', $endpoint);

        return (bool) PushSubscription::where('user_id', $user->id)
            ->where('endpoint_hash', $hash)
            ->delete();
    }

    /**
     * Send Web Push notification to a specific user across all registered devices.
     */
    public static function sendNotificationToUser(
        User|int $user,
        string $title,
        string $message,
        ?string $icon = null,
        ?string $url = null,
        ?string $tag = null,
        array $extra = []
    ): int {
        $userId = $user instanceof User ? $user->id : (int) $user;
        $subscriptions = PushSubscription::where('user_id', $userId)->get();

        if ($subscriptions->isEmpty()) {
            return 0;
        }

        return self::dispatchToSubscriptions($subscriptions, $title, $message, $icon, $url, $tag, $extra);
    }

    /**
     * Send Web Push notification to all partners in the workspace (excluding the actor).
     */
    public static function sendNotificationToPartner(
        int $workspaceId,
        int $actorId,
        string $title,
        string $message,
        ?string $icon = null,
        ?string $url = null,
        ?string $tag = null,
        array $extra = []
    ): int {
        $subscriptions = PushSubscription::whereHas('user', function ($q) use ($workspaceId, $actorId) {
            $q->where('workspace_id', $workspaceId)
                ->where('id', '!=', $actorId);
        })->get();

        if ($subscriptions->isEmpty()) {
            return 0;
        }

        return self::dispatchToSubscriptions($subscriptions, $title, $message, $icon, $url, $tag, $extra);
    }

    /**
     * Internal dispatcher using Minishlink WebPush client.
     *
     * @param  Collection<int, PushSubscription>  $subscriptions
     */
    protected static function dispatchToSubscriptions(
        $subscriptions,
        string $title,
        string $message,
        ?string $icon = null,
        ?string $url = null,
        ?string $tag = null,
        array $extra = []
    ): int {
        if (! class_exists(WebPush::class)) {
            Log::warning('WebPush class not found. Skipping push dispatch.');

            return 0;
        }

        $auth = [
            'VAPID' => [
                'subject' => config('services.webpush.vapid_subject', config('app.url', 'mailto:admin@masroofi.com')),
                'publicKey' => config('services.webpush.vapid_public_key', 'BMqew5VNSqT4zM3FihrpaIFTFPe0_cEd5elg5RlTxMuA3d7-6NCFvT3P_0ayY_6OB1_QMinLXKIPz9U7rHtpTPc'),
                'privateKey' => config('services.webpush.vapid_private_key', 'PhoY8vVPg9YNRoRPC_VsSPbNeFipdEpgW5V9T-d37P0'),
            ],
        ];

        try {
            $webPush = new WebPush($auth);
            $webPush->setReuseVAPIDHeaders(true);

            $payload = json_encode([
                'title' => $title,
                'body' => $message,
                'icon' => $icon ?: asset('icons/icon-192.png'),
                'badge' => asset('icons/icon-192.png'),
                'url' => $url ?: '/',
                'tag' => $tag ?: 'masroofi-'.time(),
                'timestamp' => now()->timestamp * 1000,
                'extra' => $extra,
            ], JSON_UNESCAPED_UNICODE);

            $expiredIds = [];
            $sentSuccessCount = 0;

            foreach ($subscriptions as $subRecord) {
                try {
                    $subObj = Subscription::create([
                        'endpoint' => $subRecord->endpoint,
                        'publicKey' => $subRecord->public_key,
                        'authToken' => $subRecord->auth_token,
                        'contentEncoding' => $subRecord->content_encoding ?: 'aes128gcm',
                    ]);

                    $report = $webPush->sendOneNotification($subObj, $payload);

                    if ($report->isSuccess()) {
                        $sentSuccessCount++;
                    } elseif ($report->isSubscriptionExpired()) {
                        $expiredIds[] = $subRecord->id;
                    }
                } catch (\Throwable $e) {
                    Log::info('Failed delivering push to sub '.$subRecord->id.': '.$e->getMessage());
                }
            }

            // Clean up invalid or expired endpoints automatically
            if (! empty($expiredIds)) {
                PushSubscription::whereIn('id', $expiredIds)->delete();
            }

            return $sentSuccessCount;
        } catch (\Throwable $e) {
            Log::error('WebPush service dispatch error: '.$e->getMessage());

            return 0;
        }
    }
}
