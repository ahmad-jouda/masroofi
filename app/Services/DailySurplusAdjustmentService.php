<?php

namespace App\Services;

use App\Models\Debt;
use App\Models\DebtHarvestLog;
use App\Models\GoalHarvestLog;
use App\Models\SavingGoal;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DailySurplusAdjustmentService
{
    /**
     * Recalculate daily surplus for a past date when an expense was added, edited, or removed.
     * Adjusts the harvested amounts for debts and saving goals according to their original ratios.
     *
     * @return array|null Returns details of adjustments or null if no adjustment was needed.
     */
    public static function recalculateForDate(string|Carbon $date): ?array
    {
        $dateObj = Carbon::parse($date)->startOfDay();
        $dateString = $dateObj->toDateString();

        // Only past days (strictly before today) can have their surplus harvested and adjusted
        if ($dateObj->greaterThanOrEqualTo(Carbon::today())) {
            return null;
        }

        $debtLogs = DebtHarvestLog::whereDate('date', $dateString)->get();
        $goalLogs = GoalHarvestLog::whereDate('date', $dateString)->get();

        // If no harvest logs exist for this past date, nothing has been distributed yet
        if ($debtLogs->isEmpty() && $goalLogs->isEmpty()) {
            return null;
        }

        // Determine baseline day allowance from existing logs (or fallback to calculated safe daily)
        $dayAllowance = (float) ($debtLogs->first()?->day_allowance ?? $goalLogs->first()?->day_allowance ?? 0);
        if ($dayAllowance <= 0) {
            $dayAllowance = DebtSurplusHarvestService::calculateCurrentSafeDaily();
        }

        // Calculate actual expense transactions on that day
        $actualDaySpent = (float) Transaction::where('type', 'expense')
            ->whereDate('date', $dateString)
            ->sum('amount');

        // New surplus equation
        $newSurplus = max(0, round($dayAllowance - $actualDaySpent, 2));

        $debtAdjustments = [];
        $goalAdjustments = [];
        $totalDebtNewHarvest = 0.0;

        DB::transaction(function () use (
            $debtLogs, $goalLogs, $dateObj, $dateString, $dayAllowance, $actualDaySpent, $newSurplus,
            &$debtAdjustments, &$goalAdjustments, &$totalDebtNewHarvest
        ) {
            // 1. Adjust Debts
            foreach ($debtLogs as $log) {
                $debt = Debt::find($log->debt_id);
                if (! $debt) {
                    continue;
                }

                $oldHarvest = (float) $log->harvested_amount;
                $harvestRatio = (float) ($debt->harvest_ratio ?: 0.50);

                if ($newSurplus > 0) {
                    $targetHarvest = round($newSurplus * $harvestRatio, 2);
                    if ($debt->harvest_max_daily && $debt->harvest_max_daily > 0) {
                        $targetHarvest = min($targetHarvest, (float) $debt->harvest_max_daily);
                    }
                    $newHarvest = $targetHarvest;
                } else {
                    $newHarvest = 0.00;
                }

                $totalDebtNewHarvest += $newHarvest;
                $diff = round($newHarvest - $oldHarvest, 2);

                if (abs($diff) > 0.001) {
                    if ($diff < 0) {
                        // Surplus decreased -> deduct from debt surplus_harvested
                        $deduct = abs($diff);
                        $debt->update([
                            'surplus_harvested' => max(0, round((float) $debt->surplus_harvested - $deduct, 2)),
                        ]);
                    } else {
                        // Surplus increased -> increment surplus_harvested
                        $debt->increment('surplus_harvested', $diff);
                    }

                    $debtAdjustments[] = [
                        'debt_id' => $debt->id,
                        'debt_name' => $debt->name,
                        'old_harvest' => $oldHarvest,
                        'new_harvest' => $newHarvest,
                        'difference' => $diff,
                    ];
                }

                // Update DebtHarvestLog
                $debtNote = $newHarvest > 0
                    ? "فائض معدّل ليوم {$dateObj->format('Y/m/d')}: المسموح {$dayAllowance} ₪، صُرف {$actualDaySpent} ₪، الفائض {$newSurplus} ₪ ورُحل {$newHarvest} ₪ للحصالة"
                    : "يوم {$dateObj->format('Y/m/d')}: صُرفت اليومية بالكامل أو تجاوزت ({$actualDaySpent} ₪) بعد تسجيل مصروف متأخر - عُدّل الفائض لـ (0 ₪)";

                $log->update([
                    'day_allowance' => $dayAllowance,
                    'day_spent' => $actualDaySpent,
                    'surplus_amount' => $newSurplus,
                    'harvested_amount' => $newHarvest,
                    'note' => $debtNote,
                ]);
            }

            // 2. Adjust Saving Goals
            $availableSurplusForGoals = max(0, round($newSurplus - $totalDebtNewHarvest, 2));

            foreach ($goalLogs as $log) {
                $goal = SavingGoal::find($log->goal_id);
                if (! $goal) {
                    continue;
                }

                $oldHarvestILS = (float) $log->harvested_amount;
                $oldHarvestGoalCurr = (float) $log->harvested_in_goal_currency;
                $goalCurrency = strtoupper($goal->currency ?? $log->currency ?? 'ILS');
                $rate = (float) ($log->exchange_rate ?: CurrencyService::getRate('ILS', $goalCurrency));

                if ($availableSurplusForGoals > 0) {
                    $harvestRatio = (float) ($goal->harvest_ratio ?: 0.50);
                    $rawHarvestILS = round($newSurplus * $harvestRatio, 2);
                    $newHarvestILS = min($rawHarvestILS, $availableSurplusForGoals);
                    if ($goal->harvest_max_daily && $goal->harvest_max_daily > 0) {
                        $newHarvestILS = min($newHarvestILS, (float) $goal->harvest_max_daily);
                    }
                    $newHarvestGoalCurr = $goalCurrency === 'ILS'
                        ? $newHarvestILS
                        : round($newHarvestILS * $rate, 2);
                } else {
                    $newHarvestILS = 0.00;
                    $newHarvestGoalCurr = 0.00;
                }

                $diffILS = round($newHarvestILS - $oldHarvestILS, 2);
                $diffGoalCurr = round($newHarvestGoalCurr - $oldHarvestGoalCurr, 2);

                if (abs($diffILS) > 0.001 || abs($diffGoalCurr) > 0.001) {
                    if ($diffGoalCurr < 0) {
                        // Deduct from goal
                        $deductGoal = abs($diffGoalCurr);
                        $goal->update([
                            'current_amount' => max(0, round((float) $goal->current_amount - $deductGoal, 2)),
                        ]);
                    } else {
                        // Add to goal
                        $goal->increment('current_amount', $diffGoalCurr);
                    }

                    // Manage linked savings Transaction
                    $savingsTx = null;
                    if ($log->transaction_id) {
                        $savingsTx = Transaction::find($log->transaction_id);
                    }
                    if (! $savingsTx) {
                        $savingsTx = Transaction::where('goal_id', $goal->id)
                            ->where('type', 'savings')
                            ->whereDate('date', $dateString)
                            ->first();
                    }

                    if ($newHarvestILS > 0) {
                        if ($savingsTx) {
                            $savingsTx->update([
                                'amount' => $newHarvestILS,
                                'note' => "اقتطاع تلقائي (معدّل) من فائض يوم {$dateObj->format('Y/m/d')} ({$goal->harvest_ratio_percent}%): {$goal->name} (+{$newHarvestGoalCurr} {$goalCurrency})",
                            ]);
                        } else {
                            $savingsTx = Transaction::create([
                                'workspace_id' => $goal->workspace_id ?? 1,
                                'user_id' => 1,
                                'type' => 'savings',
                                'amount' => $newHarvestILS,
                                'goal_id' => $goal->id,
                                'date' => $dateString,
                                'payment_method' => 'transfer',
                                'note' => "اقتطاع تلقائي (معدّل) من فائض يوم {$dateObj->format('Y/m/d')} ({$goal->harvest_ratio_percent}%): {$goal->name} (+{$newHarvestGoalCurr} {$goalCurrency})",
                            ]);
                        }
                        $log->transaction_id = $savingsTx->id;
                    } else {
                        // If harvest reached 0, delete the savings transaction
                        if ($savingsTx) {
                            $savingsTx->delete();
                        }
                        $log->transaction_id = null;
                    }

                    $goalAdjustments[] = [
                        'goal_id' => $goal->id,
                        'goal_name' => $goal->name,
                        'currency' => $goalCurrency,
                        'old_harvest_ils' => $oldHarvestILS,
                        'new_harvest_ils' => $newHarvestILS,
                        'diff_ils' => $diffILS,
                        'old_harvest_curr' => $oldHarvestGoalCurr,
                        'new_harvest_curr' => $newHarvestGoalCurr,
                        'diff_curr' => $diffGoalCurr,
                    ];
                }

                $goalNote = $newHarvestILS > 0
                    ? "فائض معدّل ليوم {$dateObj->format('Y/m/d')}: المسموح {$dayAllowance} ₪، صُرف {$actualDaySpent} ₪، الفائض {$newSurplus} ₪ ورُحل +{$newHarvestGoalCurr} {$goalCurrency} ({$newHarvestILS} ₪) للحصالة"
                    : "يوم {$dateObj->format('Y/m/d')}: تم استهلاك الفائض بالكامل بعد تسجيل مصروف متأخر - عُدّل الاقتطاع لـ (0 {$goalCurrency})";

                $log->update([
                    'day_allowance' => $dayAllowance,
                    'day_spent' => $actualDaySpent,
                    'surplus_amount' => $newSurplus,
                    'harvested_amount' => $newHarvestILS,
                    'harvested_in_goal_currency' => $newHarvestGoalCurr,
                    'note' => $goalNote,
                    'transaction_id' => $log->transaction_id,
                ]);
            }
        });

        if (empty($debtAdjustments) && empty($goalAdjustments)) {
            return null;
        }

        return [
            'date' => $dateString,
            'day_allowance' => $dayAllowance,
            'actual_day_spent' => $actualDaySpent,
            'new_surplus' => $newSurplus,
            'debt_adjustments' => $debtAdjustments,
            'goal_adjustments' => $goalAdjustments,
        ];
    }

    /**
     * Format a concise, clear human-readable Arabic feedback message.
     */
    public static function formatAdjustmentSummary(array $adjustment): string
    {
        $dateFormatted = Carbon::parse($adjustment['date'])->format('Y/m/d');
        $parts = [];

        foreach ($adjustment['debt_adjustments'] as $da) {
            $diff = $da['difference'];
            $name = $da['debt_name'];
            if ($diff < 0) {
                $abs = abs($diff);
                $parts[] = "خُصم {$abs} ₪ من حصالة دين [{$name}]";
            } elseif ($diff > 0) {
                $parts[] = "أُضيف {$diff} ₪ لحصالة دين [{$name}]";
            }
        }

        foreach ($adjustment['goal_adjustments'] as $ga) {
            $diffCurr = $ga['diff_curr'];
            $curr = $ga['currency'];
            $name = $ga['goal_name'];
            if ($diffCurr < 0) {
                $abs = abs($diffCurr);
                $parts[] = "خُصم {$abs} {$curr} من حصالة [{$name}]";
            } elseif ($diffCurr > 0) {
                $parts[] = "أُضيف {$diffCurr} {$curr} لحصالة [{$name}]";
            }
        }

        if (empty($parts)) {
            return '';
        }

        $details = implode(' و ', $parts);

        return "⚡ تم تعديل فائض يوم {$dateFormatted} تلقائياً: {$details} حسب النسبة المحددة.";
    }
}
