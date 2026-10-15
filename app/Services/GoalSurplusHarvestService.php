<?php

namespace App\Services;

use App\Models\DebtHarvestLog;
use App\Models\GoalHarvestLog;
use App\Models\SavingGoal;
use App\Models\Transaction;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class GoalSurplusHarvestService
{
    /**
     * Process past completed days (from plan start date up to yesterday)
     * and automatically harvest daily surpluses into the saving goal.
     */
    public static function processActiveHarvests(?float $safeDaily = null): void
    {
        $dailyAllowance = ($safeDaily !== null && $safeDaily > 0)
            ? $safeDaily
            : DebtSurplusHarvestService::calculateCurrentSafeDaily();

        $activeGoals = SavingGoal::where('is_harvest_active', true)->get();

        foreach ($activeGoals as $goal) {
            self::processGoal($goal, $dailyAllowance);
        }
    }

    public static function processGoal(SavingGoal $goal, ?float $safeDaily = null): void
    {
        if (! $goal->is_harvest_active || (float) $goal->current_amount >= (float) $goal->target_amount) {
            return;
        }

        $startDate = $goal->harvest_started_at
            ? Carbon::parse($goal->harvest_started_at)->startOfDay()
            : Carbon::yesterday()->startOfDay();
        $endDate = Carbon::yesterday()->startOfDay();

        // If plan started in the future or today, nothing to process for past days yet
        if ($startDate->greaterThan($endDate)) {
            return;
        }

        $period = CarbonPeriod::create($startDate, $endDate);
        $dayAllowance = ($safeDaily !== null && $safeDaily > 0)
            ? $safeDaily
            : DebtSurplusHarvestService::calculateCurrentSafeDaily();

        $goalCurrency = strtoupper($goal->currency ?? 'ILS');
        $rate = CurrencyService::getRate('ILS', $goalCurrency);

        foreach ($period as $date) {
            $dateString = $date->toDateString();

            // Check if already processed
            $existingLog = GoalHarvestLog::where('goal_id', $goal->id)
                ->whereDate('date', $dateString)
                ->first();

            if ($existingLog) {
                // Self-healing check: if actual transactions on that day changed since harvest was logged, adjust!
                $currentSpent = (float) Transaction::where('type', 'expense')
                    ->whereDate('date', $dateString)
                    ->sum('amount');
                if (abs((float) $existingLog->day_spent - $currentSpent) > 0.001) {
                    DailySurplusAdjustmentService::recalculateForDate($dateString);
                }

                continue;
            }

            $remainingNeededInGoalCurrency = max(0, (float) $goal->target_amount - (float) $goal->current_amount);
            if ($remainingNeededInGoalCurrency <= 0) {
                break;
            }

            // Sum of actual expenses on that day
            $daySpent = (float) Transaction::where('type', 'expense')
                ->whereDate('date', $dateString)
                ->sum('amount');

            // Daily harvest equation: Surplus = Allowance - Actual Spent
            $surplus = max(0, $dayAllowance - $daySpent);

            if ($surplus > 0) {
                // Safeguard against over-harvesting if debts already took a portion
                $alreadyHarvestedForDebt = (float) DebtHarvestLog::whereDate('date', $dateString)->sum('harvested_amount');
                $availableSurplusForGoal = max(0, $surplus - $alreadyHarvestedForDebt);

                $harvestRatio = (float) ($goal->harvest_ratio ?: 0.50);
                $rawHarvestILS = round($surplus * $harvestRatio, 2);
                $rawHarvestILS = min($rawHarvestILS, $availableSurplusForGoal);

                if ($goal->harvest_max_daily && $goal->harvest_max_daily > 0) {
                    $rawHarvestILS = min($rawHarvestILS, (float) $goal->harvest_max_daily);
                }

                $convertedGoalAmount = $goalCurrency === 'ILS'
                    ? $rawHarvestILS
                    : round($rawHarvestILS * $rate, 2);

                if ($convertedGoalAmount >= $remainingNeededInGoalCurrency) {
                    $convertedGoalAmount = $remainingNeededInGoalCurrency;
                    $harvestILS = $goalCurrency === 'ILS'
                        ? $convertedGoalAmount
                        : round(CurrencyService::convert($convertedGoalAmount, $goalCurrency, 'ILS'), 2);
                } else {
                    $harvestILS = $rawHarvestILS;
                }

                if ($convertedGoalAmount > 0) {
                    $goal->increment('current_amount', $convertedGoalAmount);

                    $tx = Transaction::create([
                        'workspace_id' => $goal->workspace_id ?? 1,
                        'user_id' => 1,
                        'type' => 'savings',
                        'amount' => $harvestILS,
                        'goal_id' => $goal->id,
                        'date' => $dateString,
                        'payment_method' => 'transfer',
                        'note' => "اقتطاع تلقائي من فائض يوم {$date->format('Y/m/d')} ({$goal->harvest_ratio_percent}%): {$goal->name} (+{$convertedGoalAmount} {$goalCurrency})",
                    ]);

                    GoalHarvestLog::create([
                        'workspace_id' => $goal->workspace_id ?? 1,
                        'goal_id' => $goal->id,
                        'transaction_id' => $tx->id,
                        'date' => $dateString,
                        'day_allowance' => $dayAllowance,
                        'day_spent' => $daySpent,
                        'surplus_amount' => $surplus,
                        'harvested_amount' => $harvestILS,
                        'harvested_in_goal_currency' => $convertedGoalAmount,
                        'currency' => $goalCurrency,
                        'exchange_rate' => $rate,
                        'note' => "فائض يوم {$date->format('Y/m/d')}: المسموح {$dayAllowance} ₪، صُرف {$daySpent} ₪، الفائض {$surplus} ₪ ورُحل +{$convertedGoalAmount} {$goalCurrency} ({$harvestILS} ₪) للحصالة",
                    ]);
                }
            } else {
                // Document 0 harvest for complete transparency
                GoalHarvestLog::create([
                    'workspace_id' => $goal->workspace_id ?? 1,
                    'goal_id' => $goal->id,
                    'date' => $dateString,
                    'day_allowance' => $dayAllowance,
                    'day_spent' => $daySpent,
                    'surplus_amount' => 0.00,
                    'harvested_amount' => 0.00,
                    'harvested_in_goal_currency' => 0.00,
                    'currency' => $goalCurrency,
                    'exchange_rate' => $rate,
                    'note' => "يوم {$date->format('Y/m/d')}: صُرفت اليومية بالكامل أو تجاوزت ({$daySpent} ₪) - لم يُخصم شيء (0 ₪)",
                ]);
            }
        }
    }

    /**
     * Get live forecast for today's in-progress surplus for this goal
     */
    public static function getTodayForecast(SavingGoal $goal, ?float $safeDaily = null): array
    {
        $dayAllowance = ($safeDaily !== null && $safeDaily > 0)
            ? $safeDaily
            : DebtSurplusHarvestService::calculateCurrentSafeDaily();

        $todaySpent = (float) Transaction::where('type', 'expense')
            ->whereDate('date', Carbon::today()->toDateString())
            ->sum('amount');

        $surplus = max(0, $dayAllowance - $todaySpent);
        $harvestRatio = (float) ($goal->harvest_ratio ?: 0.50);
        $rawHarvestILS = round($surplus * $harvestRatio, 2);

        if ($goal->harvest_max_daily && $goal->harvest_max_daily > 0) {
            $rawHarvestILS = min($rawHarvestILS, (float) $goal->harvest_max_daily);
        }

        $goalCurrency = strtoupper($goal->currency ?? 'ILS');
        $rate = CurrencyService::getRate('ILS', $goalCurrency);
        $expectedConverted = $goalCurrency === 'ILS'
            ? $rawHarvestILS
            : round($rawHarvestILS * $rate, 2);

        $remainingNeeded = max(0, (float) $goal->target_amount - (float) $goal->current_amount);
        $expectedConverted = min($expectedConverted, $remainingNeeded);

        return [
            'allowance' => $dayAllowance,
            'spent' => $todaySpent,
            'surplus' => $surplus,
            'expected_harvest_ils' => $rawHarvestILS,
            'expected_harvest_goal' => $expectedConverted,
            'currency' => $goalCurrency,
        ];
    }
}
