<?php

namespace App\Services;

use App\Models\DebtHarvestLog;
use App\Models\Envelope;
use App\Models\GoalHarvestLog;
use App\Models\Transaction;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class FinancialForecastService
{
    /**
     * Arabic Month Names Map
     */
    public const ARABIC_MONTHS = [
        1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
        5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
        9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
    ];

    /**
     * Compute Cashflow Runway Alert (رادار التنبؤ بنفاذ الراتب ومعدل الصرف).
     *
     * @return array<string, mixed>
     */
    public static function calculateCashflowRunway(
        int $workspaceId,
        float $appSpendableBalance,
        float $cycleExpenses,
        int $daysElapsed,
        int $daysRemaining,
        Carbon $cycleEnd,
        float $safeDaily
    ): array {
        $today = Carbon::today();

        // 1. Calculate average daily burn rate so far in this cycle
        if ($daysElapsed > 0) {
            $burnRate = round($cycleExpenses / $daysElapsed, 1);
        } else {
            $todayExpenses = (float) Transaction::where('workspace_id', $workspaceId)
                ->where('type', 'expense')
                ->whereDate('date', $today->toDateString())
                ->sum('amount');
            $burnRate = $todayExpenses > 0 ? $todayExpenses : ($safeDaily > 0 ? $safeDaily : 50.0);
        }

        // 2. Projected runway days left until spendable liquidity runs out
        if ($burnRate > 0) {
            $runwayDays = (int) floor($appSpendableBalance / $burnRate);
        } else {
            $runwayDays = max(1, $daysRemaining + 10);
        }

        // 3. Evaluate risk vs safe pacing
        $gapDays = 0;
        $exhaustionDate = null;
        $exhaustionMonth = null;
        $projectedSurplus = 0.0;

        if ($runwayDays < $daysRemaining && $appSpendableBalance > 0) {
            // Early depletion risk detected!
            $gapDays = max(1, $daysRemaining - $runwayDays);
            $exhaustionDate = $today->copy()->addDays($runwayDays);
            $exhaustionMonth = self::ARABIC_MONTHS[$exhaustionDate->month] ?? $exhaustionDate->format('F');

            $status = $gapDays >= 5 ? 'danger' : 'warning';
            $title = '⚠️ رادار التنبؤ المالي: وتيرة صرفك قد تستنزف رصيد التطبيق قبل نهاية الدورة!';

            $gapLabel = match (true) {
                $gapDays === 1 => 'يوم واحد',
                $gapDays === 2 => 'يومان',
                $gapDays >= 3 && $gapDays <= 10 => "{$gapDays} أيام",
                default => "{$gapDays} يوماً",
            };

            $message = "بمعدل صرفك الحالي ({$burnRate} ₪/يوم)، قد ينفذ رصيد التطبيق قبل نهاية الدورة بـ {$gapLabel} (يوم {$exhaustionDate->day} {$exhaustionMonth})! الموصى به خفض اليومية لـ ".(int) round($safeDaily).' ₪ لتفادي العجز والوصول بأمان.';
        } else {
            // Pacing is safe and sustainable
            $status = 'safe';
            $projectedSurplus = max(0, round($appSpendableBalance - ($burnRate * $daysRemaining), 0));
            $title = '🟢 رادار التنبؤ المالي: وتيرة صرفك متزنة ومطمئنة جداً!';
            $message = "بمعدل صرفك الحالي ({$burnRate} ₪/يوم)، ستصل لنهاية الدورة بأمان مع فائض سيولة متوقع قدره ".number_format($projectedSurplus, 0).' ₪ يُمكنك توجيهه للحصالة أو سداد الديون.';
        }

        return [
            'status' => $status,
            'burn_rate' => $burnRate,
            'app_spendable_balance' => $appSpendableBalance,
            'runway_days' => $runwayDays,
            'days_remaining' => $daysRemaining,
            'gap_days' => $gapDays,
            'exhaustion_date' => $exhaustionDate ? $exhaustionDate->format('Y-m-d') : null,
            'exhaustion_day' => $exhaustionDate?->day,
            'exhaustion_month' => $exhaustionMonth,
            'recommended_daily' => (float) round($safeDaily),
            'projected_surplus' => $projectedSurplus,
            'title' => $title,
            'message' => $message,
        ];
    }

    /**
     * Generate "Financial Mirror" monthly review report (تقرير المرآة المالية الشهرية).
     *
     * @return array<string, mixed>
     */
    public static function generateMonthlyMirrorReport(
        int $workspaceId,
        Carbon $cycleStart,
        Carbon $cycleEnd
    ): array {
        $startDateStr = $cycleStart->toDateString();
        $endDateStr = $cycleEnd->toDateString();

        // 1. Current Cycle Expenses
        $currExpenses = (float) Transaction::where('workspace_id', $workspaceId)
            ->where('type', 'expense')
            ->whereBetween('date', [$startDateStr, $endDateStr])
            ->sum('amount');

        // 2. Previous Cycle Expenses for Comparison
        $prevStart = (clone $cycleStart)->subMonthNoOverflow();
        $prevEnd = clone $cycleStart;
        $prevExpenses = (float) Transaction::where('workspace_id', $workspaceId)
            ->where('type', 'expense')
            ->whereBetween('date', [$prevStart->toDateString(), $prevEnd->toDateString()])
            ->sum('amount');

        $diffAmount = round($currExpenses - $prevExpenses, 2);
        $diffPercent = $prevExpenses > 0 ? round(($diffAmount / $prevExpenses) * 100, 1) : 0.0;
        $isSpendingLower = ($diffAmount <= 0);

        // 3. Top Spending Envelope (أكثر ظرف استنزف الميزانية)
        $topEnvelopeData = Transaction::where('workspace_id', $workspaceId)
            ->where('type', 'expense')
            ->whereBetween('date', [$startDateStr, $endDateStr])
            ->whereNotNull('envelope_id')
            ->selectRaw('envelope_id, SUM(amount) as total')
            ->groupBy('envelope_id')
            ->orderByDesc('total')
            ->first();

        $topEnvelope = $topEnvelopeData ? Envelope::find($topEnvelopeData->envelope_id) : null;
        $topEnvelopeSpent = (float) ($topEnvelopeData?->total ?? 0);
        $topEnvelopePercent = $currExpenses > 0 ? (int) round(($topEnvelopeSpent / $currExpenses) * 100) : 0;

        // 4. Surplus Harvested (كم وفّرت من الفوائض)
        $debtHarvested = (float) DebtHarvestLog::whereHas('debt', fn ($q) => $q->where('workspace_id', $workspaceId))
            ->whereBetween('date', [$startDateStr, $endDateStr])
            ->sum('harvested_amount');

        $goalHarvested = (float) GoalHarvestLog::whereHas('goal', fn ($q) => $q->where('workspace_id', $workspaceId))
            ->whereBetween('date', [$startDateStr, $endDateStr])
            ->sum('harvested_amount');

        $manualSavings = (float) Transaction::where('workspace_id', $workspaceId)
            ->where('type', 'savings')
            ->whereBetween('date', [$startDateStr, $endDateStr])
            ->sum('amount');

        $totalSurplusSaved = round($debtHarvested + $goalHarvested + $manualSavings, 2);

        // 5. Hardest Financial Moment (أعلى يوم صرف في الشهر)
        $highestDayData = Transaction::where('workspace_id', $workspaceId)
            ->where('type', 'expense')
            ->whereBetween('date', [$startDateStr, $endDateStr])
            ->selectRaw('date, SUM(amount) as total_spent, count(*) as tx_count')
            ->groupBy('date')
            ->orderByDesc('total_spent')
            ->first();

        $highestDay = null;
        if ($highestDayData) {
            $hDate = Carbon::parse($highestDayData->date);
            $hMonth = self::ARABIC_MONTHS[$hDate->month] ?? $hDate->format('F');
            $biggestTxNote = Transaction::where('workspace_id', $workspaceId)
                ->where('type', 'expense')
                ->whereDate('date', $highestDayData->date)
                ->orderByDesc('amount')
                ->value('note') ?: 'مصاريف مشتريات متعددة';

            $highestDay = [
                'date' => Carbon::parse($highestDayData->date)->toDateString(),
                'formatted_date' => "{$hDate->day} {$hMonth}",
                'amount' => (float) $highestDayData->total_spent,
                'note' => $biggestTxNote,
                'tx_count' => (int) $highestDayData->tx_count,
            ];
        }

        // 6. Best Financial Moments (أجمل لحظات الشهر - Zero Spend Days & Harvests)
        $today = Carbon::today();
        $evaluatedEnd = $cycleEnd->lessThan($today) ? $cycleEnd : $today;
        $period = CarbonPeriod::create($cycleStart, $evaluatedEnd);

        $zeroSpendDaysCount = 0;
        foreach ($period as $day) {
            $daySpent = (float) Transaction::where('workspace_id', $workspaceId)
                ->where('type', 'expense')
                ->whereDate('date', $day->toDateString())
                ->sum('amount');

            if ($daySpent <= 0.001) {
                $zeroSpendDaysCount++;
            }
        }

        // 7. Financial Strengths (نقاط قوتك المالية للشهر)
        $strengths = [];
        if ($isSpendingLower && $prevExpenses > 0) {
            $strengths[] = [
                'icon' => '📉',
                'title' => 'انضباط تنازلي ملهم',
                'desc' => 'أحكمت مصاريفك هذا الشهر بنسبة '.abs($diffPercent).'% مقارنة بالشهر السابق (وفرت '.number_format(abs($diffAmount), 0).' ₪).',
            ];
        }

        if ($totalSurplusSaved > 0) {
            $strengths[] = [
                'icon' => '⚡',
                'title' => 'اقتناص الفوائض الذكية',
                'desc' => 'وفّرت '.number_format($totalSurplusSaved, 0).' ₪ من الفوائض التلقائية ووجّهتها نحو سداد الديون والتحويش.',
            ];
        }

        if ($zeroSpendDaysCount > 0) {
            $strengths[] = [
                'icon' => '✨',
                'title' => "أيام بيضاء دون مصاريف ({$zeroSpendDaysCount} أيام)",
                'desc' => 'عشت أياماً كاملة بصفر مصاريف، مما حافظ على رصيدك ومنح ميزانيتك متنفساً مريحاً.',
            ];
        }

        if ($topEnvelope && $topEnvelope->budget > 0 && $topEnvelopeSpent <= (float) $topEnvelope->budget) {
            $strengths[] = [
                'icon' => '🛡️',
                'title' => "حماية ظرف {$topEnvelope->name}",
                'desc' => 'رغم كونه أكثر الظروف نشاطاً، إلا أنك حافظت عليه ضمن الميزانية المحددة دون تجاوز.',
            ];
        }

        // 8. Financial Health Score (درجة الصحة المالية من 100)
        $score = 80;
        if ($isSpendingLower) {
            $score += 10;
        }
        if ($totalSurplusSaved > 0) {
            $score += 10;
        }
        if ($zeroSpendDaysCount >= 3) {
            $score += 5;
        }
        $score = min(100, max(40, $score));

        $grade = match (true) {
            $score >= 90 => ['label' => 'ممتاز وريادي 🌟', 'class' => 'grade-excellent'],
            $score >= 75 => ['label' => 'جيد جداً ومتزن ✨', 'class' => 'grade-good'],
            default => ['label' => 'يحتاج مزيداً من الانضباط ⚠️', 'class' => 'grade-warning'],
        };

        // 9. Smart AI Insights & Recommendations (توصيات ذكية للشهر القادم)
        $recommendations = [];
        if ($topEnvelope && $topEnvelopePercent >= 40) {
            $recommendations[] = "ظرف ({$topEnvelope->name}) استحوذ على {$topEnvelopePercent}% من إجمالي مصاريفك، جرّب تجزئة مشترياته أسبوعياً لتخفيف العبء.";
        }
        if (! $isSpendingLower && $diffPercent > 15) {
            $recommendations[] = "ارتفع صرفك هذا الشهر بنسبة {$diffPercent}% مقارنة بالدورة الماضية؛ حدد سقفاً ثابتاً للمصاريف الترفيهية مع بداية الراتب القادم.";
        }
        $recommendations[] = 'فعّل خطة حصاد الفائض بنسبة 50% لجميع الديون لتقليص مدة السداد دون أي ضغط مالي.';

        return [
            'curr_expenses' => $currExpenses,
            'prev_expenses' => $prevExpenses,
            'diff_amount' => $diffAmount,
            'diff_percent' => $diffPercent,
            'is_spending_lower' => $isSpendingLower,
            'top_envelope' => $topEnvelope,
            'top_envelope_spent' => $topEnvelopeSpent,
            'top_envelope_percent' => $topEnvelopePercent,
            'total_surplus_saved' => $totalSurplusSaved,
            'debt_harvested' => $debtHarvested,
            'goal_harvested' => $goalHarvested,
            'manual_savings' => $manualSavings,
            'highest_day' => $highestDay,
            'zero_spend_days_count' => $zeroSpendDaysCount,
            'strengths' => $strengths,
            'score' => $score,
            'grade' => $grade,
            'recommendations' => $recommendations,
            'cycle_label' => (self::ARABIC_MONTHS[$cycleStart->month] ?? $cycleStart->format('F')).' '.$cycleStart->year,
        ];
    }
}
