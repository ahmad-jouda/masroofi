<?php

namespace App\Services;

use App\Models\Debt;
use App\Models\DebtHarvestLog;
use App\Models\GoalHarvestLog;
use App\Models\GroceryItem;
use App\Models\SavingGoal;
use App\Models\Transaction;
use App\Models\UserSetting;
use Carbon\Carbon;

class GamificationService
{
    /**
     * Compute full gamification state: streaks, achievements, medals, and celebrations.
     *
     * @return array<string, mixed>
     */
    public static function getGamificationData(
        int $workspaceId,
        float $safeDaily,
        float $todayBaseAllowance,
        float $todayExpenses
    ): array {
        $streakData = self::calculateStreak($workspaceId, $safeDaily, $todayBaseAllowance, $todayExpenses);
        $achievementsData = self::getAchievements($workspaceId, $streakData['current_streak']);

        return array_merge($streakData, $achievementsData);
    }

    /**
     * Calculate consecutive streak days where daily spending <= daily allowed budget limit.
     *
     * @return array<string, mixed>
     */
    public static function calculateStreak(
        int $workspaceId,
        float $safeDaily,
        float $todayBaseAllowance,
        float $todayExpenses
    ): array {
        $today = Carbon::today();
        $fallbackAllowance = $safeDaily > 0 ? $safeDaily : ($todayBaseAllowance > 0 ? $todayBaseAllowance : 50.0);

        // Check today's adherence
        $isTodaySafe = ($todayExpenses <= $todayBaseAllowance);
        $currentStreak = 0;

        if ($isTodaySafe) {
            // Today has not exceeded, so today counts towards the active streak
            $currentStreak = 1;

            // Iterate backwards from yesterday to find consecutive safe days
            $checkDate = $today->copy()->subDay();
            $maxLookbackDays = 60;

            for ($i = 0; $i < $maxLookbackDays; $i++) {
                $dateStr = $checkDate->toDateString();

                // Find historical allowance for this date if recorded in harvest logs
                $recordedAllowance = DebtHarvestLog::whereDate('date', $dateStr)->value('day_allowance')
                    ?? GoalHarvestLog::whereDate('date', $dateStr)->value('day_allowance');

                $dayAllowance = $recordedAllowance ? (float) $recordedAllowance : $fallbackAllowance;

                $daySpent = (float) Transaction::where('workspace_id', $workspaceId)
                    ->where('type', 'expense')
                    ->whereDate('date', $dateStr)
                    ->sum('amount');

                if ($daySpent <= $dayAllowance) {
                    $currentStreak++;
                    $checkDate->subDay();
                } else {
                    // Streak broken on this day
                    break;
                }
            }
        } else {
            // Today exceeded the daily limit -> active streak reset to 0
            $currentStreak = 0;
        }

        // Generate Arabic streak label
        $streakDaysLabel = match (true) {
            $currentStreak === 1 => 'يوم متتالي',
            $currentStreak === 2 => 'يومان متتاليان',
            $currentStreak >= 3 && $currentStreak <= 10 => "{$currentStreak} أيام متتالية",
            default => "{$currentStreak} يوماً متتالياً",
        };

        $streakTitle = $currentStreak > 0
            ? "🔥 {$streakDaysLabel} دون تجاوز اليومية المسموحة!"
            : '🔥 ابدأ سلسلتك اليوم! التزم باليومية المسموحة';

        return [
            'current_streak' => $currentStreak,
            'streak_days_label' => $streakDaysLabel,
            'streak_title' => $streakTitle,
            'is_today_safe' => $isTodaySafe,
            'today_expenses' => $todayExpenses,
            'today_allowance' => $todayBaseAllowance,
        ];
    }

    /**
     * Compute medals and milestone achievements.
     *
     * @return array<string, mixed>
     */
    public static function getAchievements(int $workspaceId, int $currentStreak): array
    {
        $medals = [];
        $celebrations = [];

        // 1. Saving Goals Achievements (الاقتراب من إكمال هدف أو إكماله بالكامل)
        $goals = SavingGoal::where('workspace_id', $workspaceId)->get();

        foreach ($goals as $goal) {
            $progress = (int) $goal->progress_percent;
            $target = (float) $goal->target_amount;
            $current = (float) $goal->current_amount;

            if ($target > 0 && $progress >= 100) {
                $medals[] = [
                    'id' => "goal_complete_{$goal->id}",
                    'category' => 'goal',
                    'icon' => '🏆',
                    'title' => "مُحقق هدف: {$goal->name}",
                    'description' => "تهانينا! حققت هدف تحويش ({$goal->name}) بالكامل بمبلغ ".number_format($current, 0).' ₪.',
                    'unlocked' => true,
                    'level' => 'gold',
                    'progress' => 100,
                    'badge' => 'مكتمل 100% 🎯',
                ];
                $celebrations[] = [
                    'icon' => '🏆',
                    'title' => "إنجاز عظيم! اكتمل هدف: {$goal->name}",
                    'message' => 'لقد وصلت إلى كامل المبلغ المستهدف! استمتع بثمرة انضباطك المالي.',
                ];
            } elseif ($target > 0 && $progress >= 80) {
                $medals[] = [
                    'id' => "goal_near_{$goal->id}",
                    'category' => 'goal',
                    'icon' => '🎯',
                    'title' => "قناص هدف: {$goal->name}",
                    'description' => "أنت قاب قوسين أو أدنى! تم إنجاز {$progress}% من هدف ({$goal->name}) المالي.",
                    'unlocked' => true,
                    'level' => 'silver',
                    'progress' => $progress,
                    'badge' => "على وشك الاكتمال ({$progress}%)",
                ];
                $celebrations[] = [
                    'icon' => '🎯',
                    'title' => "اقتربت جداً من هدف: {$goal->name}!",
                    'message' => "أنجزت {$progress}% من الهدف، بقي القليل جداً لتقفل الحصالة بنجاح!",
                ];
            }
        }

        // 2. Debt Clearance Achievements (تصفية دين بالكامل أو الاقتراب من التصفية)
        $debts = Debt::where('workspace_id', $workspaceId)->with('records')->get();

        foreach ($debts as $debt) {
            $isCleared = ($debt->status === 'settled' || $debt->is_harvest_complete || ((float) $debt->total_debt <= 0 && $debt->records->isNotEmpty()));
            $harvestProgress = (int) $debt->harvest_progress_percent;

            if ($isCleared) {
                $medals[] = [
                    'id' => "debt_settled_{$debt->id}",
                    'category' => 'debt',
                    'icon' => '🕊️',
                    'title' => "متحرر من دين: {$debt->name}",
                    'description' => "إسقاط وتصفية دين ({$debt->name}) بالكامل! خطوة عملاقة نحو الحرية والراحة المالية.",
                    'unlocked' => true,
                    'level' => 'platinum',
                    'progress' => 100,
                    'badge' => 'دين مصفى بالكامل 🎉',
                ];
                $celebrations[] = [
                    'icon' => '🕊️',
                    'title' => "حرية مالية! تم تصفية دين: {$debt->name}",
                    'message' => 'أسقطت هذا الدين ورفعت عبئه المالي عن كاهلك بنجاح.',
                ];
            } elseif ($harvestProgress >= 75 && ! $debt->is_harvest_complete) {
                $medals[] = [
                    'id' => "debt_near_{$debt->id}",
                    'category' => 'debt',
                    'icon' => '⏳',
                    'title' => "وشيك التصفية: {$debt->name}",
                    'description' => "تم تجميع {$harvestProgress}% من قيمة الدين عبر حصاد الفائض التلقائي!",
                    'unlocked' => true,
                    'level' => 'bronze',
                    'progress' => $harvestProgress,
                    'badge' => "مصفى بنسبة {$harvestProgress}%",
                ];
            }
        }

        // 3. Streak Milestones Medals (أوسمة سلسلة الالتزام اليومي)
        $streakTiers = [
            [
                'days' => 3,
                'id' => 'streak_3',
                'icon' => '🥉',
                'title' => 'وسام بداية الانضباط',
                'description' => 'الالتزام باليومية المسموحة لـ 3 أيام متتالية دون أي تجاوز.',
                'level' => 'bronze',
            ],
            [
                'days' => 7,
                'id' => 'streak_7',
                'icon' => '🥈',
                'title' => 'وسام أسبوع الانضباط الحديدي',
                'description' => 'أسبوع كامل (7 أيام) من السيطرة الحكيمة على المصاريف اليومية.',
                'level' => 'silver',
            ],
            [
                'days' => 14,
                'id' => 'streak_14',
                'icon' => '🥇',
                'title' => 'وسام الالتزام الذهبي',
                'description' => '14 يوماً متتالية من الالتزام المالي الصارم ونمو الفائض.',
                'level' => 'gold',
            ],
            [
                'days' => 30,
                'id' => 'streak_30',
                'icon' => '💎',
                'title' => 'وسام أسطورة التوفير',
                'description' => 'شهر كامل (30 يوماً) دون تجاوز اليومية! عقلية مالية استثنائية.',
                'level' => 'diamond',
            ],
        ];

        foreach ($streakTiers as $tier) {
            $isUnlocked = ($currentStreak >= $tier['days']);
            $progressPercent = min(100, (int) round(($currentStreak / $tier['days']) * 100));

            $medals[] = [
                'id' => $tier['id'],
                'category' => 'streak',
                'icon' => $tier['icon'],
                'title' => $tier['title'],
                'description' => $tier['description'],
                'unlocked' => $isUnlocked,
                'level' => $tier['level'],
                'progress' => $progressPercent,
                'badge' => $isUnlocked ? "محقّق ({$tier['days']} أيام) 🔥" : "التقدم: {$currentStreak}/{$tier['days']} أيام",
            ];

            if ($isUnlocked && $currentStreak === $tier['days']) {
                $celebrations[] = [
                    'icon' => $tier['icon'],
                    'title' => "فتح وسام جديد: {$tier['title']}!",
                    'message' => "واصلت سلسلة الالتزام لـ {$tier['days']} أيام متتالية دون تجاوز اليومية!",
                ];
            }
        }

        // 4. Smart Grocery Medal (المتسوق الذكي)
        $boughtGroceriesCount = GroceryItem::where('workspace_id', $workspaceId)
            ->where('is_bought', true)
            ->count();

        $medals[] = [
            'id' => 'smart_grocery',
            'category' => 'lifestyle',
            'icon' => '🛒',
            'title' => 'وسام المتسوق الذكي',
            'description' => 'استخدام قائمة المقاضي الذكية وضبط مشتريات البيت بنقرة واحدة.',
            'unlocked' => ($boughtGroceriesCount > 0),
            'level' => 'silver',
            'progress' => ($boughtGroceriesCount > 0) ? 100 : 0,
            'badge' => ($boughtGroceriesCount > 0) ? "نشط ({$boughtGroceriesCount} مشتريات) ✨" : 'قيد الانتظار',
        ];

        // 5. Budget Shield Medal (حارس الميزانية)
        $settings = UserSetting::where('workspace_id', $workspaceId)->first();
        $medals[] = [
            'id' => 'budget_shield',
            'category' => 'shield',
            'icon' => '🛡️',
            'title' => 'وسام درع الميزانية',
            'description' => 'تحديد دورة الراتب والتنظيم التلقائي للأظرف المالية والأهداف.',
            'unlocked' => ($settings && (float) $settings->salary > 0),
            'level' => 'gold',
            'progress' => 100,
            'badge' => 'درع نشط 🛡️',
        ];

        $unlockedCount = count(array_filter($medals, fn ($m) => $m['unlocked']));
        $totalMedalsCount = count($medals);

        return [
            'medals' => $medals,
            'unlocked_medals_count' => $unlockedCount,
            'total_medals_count' => $totalMedalsCount,
            'celebrations' => $celebrations,
            'has_celebration' => ! empty($celebrations),
            'top_celebration' => ! empty($celebrations) ? $celebrations[0] : null,
        ];
    }
}
