<?php

namespace App\Services;

use App\Models\Debt;
use App\Models\DebtHarvestLog;
use App\Models\SalarySource;
use App\Models\Transaction;
use App\Models\UserSetting;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class DebtSurplusHarvestService
{
    /**
     * Process past completed days (from plan start date up to yesterday)
     * and harvest daily surpluses into the debt's pot.
     */
    public static function processActiveHarvests(?float $safeDaily = null): void
    {
        $dailyAllowance = ($safeDaily !== null && $safeDaily > 0) ? $safeDaily : self::calculateCurrentSafeDaily();

        $activeDebts = Debt::where('is_harvest_active', true)
            ->where('status', 'active')
            ->where('total_debt', '>', 0)
            ->get();

        foreach ($activeDebts as $debt) {
            self::processDebt($debt, $dailyAllowance);
        }
    }

    public static function processDebt(Debt $debt, ?float $safeDaily = null): void
    {
        if (! $debt->is_harvest_active || $debt->total_debt <= 0 || $debt->is_harvest_complete) {
            return;
        }

        $startDate = $debt->harvest_started_at ? Carbon::parse($debt->harvest_started_at)->startOfDay() : Carbon::yesterday()->startOfDay();
        $endDate = Carbon::yesterday()->startOfDay();

        // If plan started in the future or today, nothing to process for past days yet
        if ($startDate->greaterThan($endDate)) {
            return;
        }

        $period = CarbonPeriod::create($startDate, $endDate);
        $dayAllowance = ($safeDaily !== null && $safeDaily > 0) ? $safeDaily : self::calculateCurrentSafeDaily();

        foreach ($period as $date) {
            $dateString = $date->toDateString();

            // Check if already processed
            $exists = DebtHarvestLog::where('debt_id', $debt->id)
                ->whereDate('date', $dateString)
                ->exists();

            if ($exists) {
                continue;
            }

            $remainingNeeded = max(0, (float) $debt->total_debt - (float) $debt->surplus_harvested);
            if ($remainingNeeded <= 0) {
                break;
            }

            // Sum of actual expenses on that day
            $daySpent = (float) Transaction::where('type', 'expense')
                ->whereDate('date', $dateString)
                ->sum('amount');

            // Daily harvest equation: Surplus = Allowance - Actual Spent
            $surplus = max(0, $dayAllowance - $daySpent);

            if ($surplus > 0) {
                $harvestRatio = (float) ($debt->harvest_ratio ?: 0.50);
                $harvest = round($surplus * $harvestRatio, 2);

                if ($debt->harvest_max_daily && $debt->harvest_max_daily > 0) {
                    $harvest = min($harvest, (float) $debt->harvest_max_daily);
                }

                $harvest = min($harvest, $remainingNeeded);

                if ($harvest > 0) {
                    DebtHarvestLog::create([
                        'debt_id' => $debt->id,
                        'date' => $dateString,
                        'day_allowance' => $dayAllowance,
                        'day_spent' => $daySpent,
                        'surplus_amount' => $surplus,
                        'harvested_amount' => $harvest,
                        'note' => "فائض يوم {$date->format('Y/m/d')}: المسموح {$dayAllowance} ₪، صُرف {$daySpent} ₪، الفائض {$surplus} ₪ ورُحل {$harvest} ₪ للحصالة",
                    ]);

                    $debt->increment('surplus_harvested', $harvest);
                }
            } else {
                // Document 0 harvest so that the day is noted with complete transparency
                DebtHarvestLog::create([
                    'debt_id' => $debt->id,
                    'date' => $dateString,
                    'day_allowance' => $dayAllowance,
                    'day_spent' => $daySpent,
                    'surplus_amount' => 0.00,
                    'harvested_amount' => 0.00,
                    'note' => "يوم {$date->format('Y/m/d')}: صُرفت اليومية بالكامل أو تجاوزت ({$daySpent} ₪) - لم يُخصم شيء (0 ₪)",
                ]);
            }
        }
    }

    /**
     * Get live forecast for today's in-progress surplus
     */
    public static function getTodayForecast(Debt $debt, ?float $safeDaily = null): array
    {
        $dayAllowance = ($safeDaily !== null && $safeDaily > 0) ? $safeDaily : self::calculateCurrentSafeDaily();
        $todaySpent = (float) Transaction::where('type', 'expense')
            ->whereDate('date', Carbon::today()->toDateString())
            ->sum('amount');

        $surplus = max(0, $dayAllowance - $todaySpent);
        $harvestRatio = (float) ($debt->harvest_ratio ?: 0.50);
        $expectedHarvest = round($surplus * $harvestRatio, 2);

        if ($debt->harvest_max_daily && $debt->harvest_max_daily > 0) {
            $expectedHarvest = min($expectedHarvest, (float) $debt->harvest_max_daily);
        }

        $remainingNeeded = max(0, (float) $debt->total_debt - (float) $debt->surplus_harvested);
        $expectedHarvest = min($expectedHarvest, $remainingNeeded);

        return [
            'allowance' => $dayAllowance,
            'spent' => $todaySpent,
            'surplus' => $surplus,
            'expected_harvest' => $expectedHarvest,
        ];
    }

    /**
     * Calculate current safe daily allowance dynamically from user's live budget
     */
    public static function calculateCurrentSafeDaily(): float
    {
        $settings = UserSetting::first();
        $salarySources = SalarySource::where('is_active', true)->get();

        $today = Carbon::today();
        $firstSalaryDay = SalarySource::where('is_active', true)->min('day_of_month');
        $cycleMode = $settings->cycle_mode ?? 'first_salary';

        if ($cycleMode === 'first_salary' && $firstSalaryDay) {
            $salaryDay = (int) $firstSalaryDay;
        } else {
            $salaryDay = (int) ($settings?->salary_day ?: ($firstSalaryDay ?: 1));
        }

        if ($today->day >= $salaryDay) {
            $cycleStart = Carbon::create($today->year, $today->month, min($salaryDay, 28))->startOfDay();
        } else {
            $cycleStart = Carbon::create($today->year, $today->month, min($salaryDay, 28))->subMonth()->startOfDay();
        }
        $cycleEnd = (clone $cycleStart)->addMonthNoOverflow();
        $daysRemaining = max(1, $cycleStart->diffInDays($cycleEnd) - max(0, $cycleStart->diffInDays($today)));

        $totalSalary = $salarySources->isNotEmpty()
            ? (float) $salarySources->sum(fn ($s) => $s->amount_in_shekel)
            : (float) ($settings?->salary ?? 0);
        $appSalariesTotal = (float) $salarySources->where('payment_method', '!=', 'cash')->sum(fn ($s) => $s->amount_in_shekel);

        $appIncome = (float) Transaction::whereNotIn('payment_method', ['cash', 'credit'])->where('type', 'income')->sum('amount');
        $appExpense = (float) Transaction::whereNotIn('payment_method', ['cash', 'credit'])->where('type', 'expense')->sum('amount');
        $appBalance = max(0, $appSalariesTotal + $appIncome - $appExpense);

        $savingsPercent = (float) ($settings?->savings_target_percent ?? 20);
        $targetSavingsAmount = ($totalSalary * $savingsPercent) / 100;
        $totalSavingsTransfers = (float) Transaction::where('type', 'savings')
            ->where('date', '>=', $cycleStart->toDateString())
            ->where('date', '<', $cycleEnd->toDateString())
            ->sum('amount');
        $actualSavedOrReserved = max($targetSavingsAmount, $totalSavingsTransfers);

        $appSpendableBalance = max(0, $appBalance - $actualSavedOrReserved);

        if ($daysRemaining > 0 && $appSpendableBalance > 0) {
            return (float) max(0, (int) round($appSpendableBalance / $daysRemaining));
        }

        return 50.0;
    }
}
