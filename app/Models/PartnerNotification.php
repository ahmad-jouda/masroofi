<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Services\WebPushService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

class PartnerNotification extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'actor_id',
        'type',
        'title',
        'message',
        'icon',
        'is_read',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public static function notifyPartner(int $workspaceId, int $actorId, string $type, string $title, string $message, string $icon = '🔔'): ?self
    {
        $notif = self::create([
            'workspace_id' => $workspaceId,
            'actor_id' => $actorId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'icon' => $icon,
            'is_read' => false,
        ]);

        try {
            $targetUrl = match ($type) {
                'grocery_add', 'grocery_bought', 'grocery_delete' => '/#tab-grocery',
                'debt_repaid', 'debt_added' => '/#tab-debts',
                'goal_fed' => '/#tab-goals',
                default => '/#tab-dashboard',
            };

            WebPushService::sendNotificationToPartner(
                workspaceId: $workspaceId,
                actorId: $actorId,
                title: $title,
                message: $message,
                icon: asset('icons/icon-192.png'),
                url: $targetUrl,
                tag: "partner-{$type}-{$notif->id}"
            );
        } catch (\Throwable $e) {
            Log::info('WebPush dispatch skipped: '.$e->getMessage());
        }

        return $notif;
    }
}
