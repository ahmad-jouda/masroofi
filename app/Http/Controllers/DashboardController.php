<?php

namespace App\Http\Controllers;

use App\Models\Debt;
use App\Models\Envelope;
use App\Models\GroceryItem;
use App\Models\SalarySource;
use App\Models\SavingGoal;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\UserSetting;
use App\Models\Wishlist;
use App\Services\CurrencyService;
use App\Services\DebtSurplusHarvestService;
use App\Services\FinancialForecastService;
use App\Services\GamificationService;
use App\Services\GoalSurplusHarvestService;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $workspaceId = auth()->user()?->workspace_id ?? 1;
        $settings = UserSetting::firstOrCreate(
            ['workspace_id' => $workspaceId],
            [
                'salary' => 7500.00,
                'salary_day' => 27,
                'work_hours_per_week' => 40,
                'savings_target_percent' => 20,
                'currency' => 'SAR',
                'theme' => 'dark',
                'sound_enabled' => true,
                'saved_from_impulse' => 280.00,
            ]
        );

        $envelopes = Envelope::with('transactions')->get();
        try {
            $goals = SavingGoal::with('harvestLogs')->get();
        } catch (\Throwable $e) {
            $goals = SavingGoal::all();
        }
        $recentTransactions = Transaction::with(['envelope', 'goal', 'user'])->latest('date')->latest('id')->take(6)->get();
        $allTransactions = Transaction::with(['envelope', 'goal', 'user'])->latest('date')->latest('id')->get();
        $wishlist = Wishlist::latest()->get();
        $subscriptions = Subscription::all();
        $salarySources = SalarySource::orderBy('day_of_month')->get();

        // 0. Shared Smart Grocery List items
        $groceryItems = GroceryItem::with(['user', 'buyer'])
            ->active()
            ->orderBy('is_bought')
            ->orderBy('created_at', 'desc')
            ->get();
        $pendingGroceryCount = $groceryItems->where('is_bought', false)->count();
        $boughtGroceryCount = $groceryItems->where('is_bought', true)->count();
        $estimatedGroceryTotal = (float) $groceryItems->where('is_bought', true)->sum('estimated_price');
        $groceryCategories = GroceryItem::CATEGORIES;

        // 1. Auto-Cycle Calculations with Cycle Browsing Support
        $today = Carbon::today();
        $currentDay = $today->day;

        // Auto-anchor cycle to the first active salary day of the month
        $firstSalaryDay = SalarySource::where('is_active', true)->min('day_of_month');
        $cycleMode = $settings->cycle_mode ?? 'first_salary';

        if ($cycleMode === 'first_salary' && $firstSalaryDay) {
            $salaryDay = (int) $firstSalaryDay;
        } else {
            $salaryDay = (int) ($settings->salary_day ?: ($firstSalaryDay ?: 1));
        }

        if ($currentDay >= $salaryDay) {
            $baseStart = Carbon::create($today->year, $today->month, min($salaryDay, 28))->startOfDay();
        } else {
            $baseStart = Carbon::create($today->year, $today->month, min($salaryDay, 28))->subMonth()->startOfDay();
        }

        $cycleOffset = (int) request('cycle_offset', 0);
        if ($cycleOffset !== 0) {
            $cycleStart = (clone $baseStart)->addMonthsNoOverflow($cycleOffset);
        } else {
            $cycleStart = clone $baseStart;
        }
        $cycleEnd = (clone $cycleStart)->addMonthNoOverflow();
        $isCurrentCycle = ($cycleOffset === 0);

        $monthArabicNames = [
            1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
            5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
            9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
        ];
        $monthLabel = ($monthArabicNames[$cycleStart->month] ?? $cycleStart->format('F')).' '.$cycleStart->year;

        if ($isCurrentCycle) {
            $totalCycleDays = max(1, $cycleStart->diffInDays($cycleEnd));
            $daysElapsed = max(0, min($totalCycleDays, $cycleStart->diffInDays($today)));
            $daysRemaining = max(1, $totalCycleDays - $daysElapsed);
        } elseif ($cycleOffset < 0) {
            $totalCycleDays = max(1, $cycleStart->diffInDays($cycleEnd));
            $daysElapsed = $totalCycleDays;
            $daysRemaining = 0;
        } else {
            $totalCycleDays = max(1, $cycleStart->diffInDays($cycleEnd));
            $daysElapsed = 0;
            $daysRemaining = $totalCycleDays;
        }

        // Scope envelope spending strictly to the active viewed cycle:
        $cycleExpensesByEnvelope = Transaction::where('type', 'expense')
            ->where('date', '>=', $cycleStart->toDateString())
            ->where('date', '<', $cycleEnd->toDateString())
            ->whereNotNull('envelope_id')
            ->groupBy('envelope_id')
            ->selectRaw('envelope_id, SUM(amount) as total')
            ->pluck('total', 'envelope_id');

        foreach ($envelopes as $env) {
            $env->setAttribute('cycle_spent', (float) ($cycleExpensesByEnvelope[$env->id] ?? 0));
        }

        // 2. Financial Metrics for the cycle
        $totalSalaryFromSources = (float) $salarySources->where('is_active', true)->sum(fn ($s) => $s->amount_in_shekel);
        $totalSalary = $salarySources->isNotEmpty() ? $totalSalaryFromSources : (float) $settings->salary;

        $appSalariesTotal = (float) $salarySources->where('is_active', true)->where('payment_method', '!=', 'cash')->sum(fn ($s) => $s->amount_in_shekel);
        $cashSalariesTotal = (float) $salarySources->where('is_active', true)->where('payment_method', 'cash')->sum(fn ($s) => $s->amount_in_shekel);

        // Cycle-scoped transactions (expenses, incomes, savings)
        $cycleExtraIncome = (float) Transaction::where('type', 'income')
            ->where('date', '>=', $cycleStart->toDateString())
            ->where('date', '<', $cycleEnd->toDateString())
            ->sum('amount');
        $totalIncome = $totalSalary + $cycleExtraIncome;

        $totalExpenses = (float) Transaction::where('type', 'expense')
            ->where('date', '>=', $cycleStart->toDateString())
            ->where('date', '<', $cycleEnd->toDateString())
            ->sum('amount');

        $totalSavingsTransfers = (float) Transaction::where('type', 'savings')
            ->where('date', '>=', $cycleStart->toDateString())
            ->where('date', '<', $cycleEnd->toDateString())
            ->sum('amount');

        $targetSavingsAmount = ($totalSalary * $settings->savings_target_percent) / 100;
        $actualSavedOrReserved = max($targetSavingsAmount, $totalSavingsTransfers);

        $netRemaining = max(0, $totalIncome - $totalExpenses - $actualSavedOrReserved);

        // Real-world physical wallet balances (cumulative for actual liquidity)
        $cashIncome = (float) Transaction::where('payment_method', 'cash')->where('type', 'income')->sum('amount');
        $cashExpense = (float) Transaction::where('payment_method', 'cash')->where('type', 'expense')->sum('amount');
        $cashBalance = max(0, $cashSalariesTotal + $cashIncome - $cashExpense);

        $appIncome = (float) Transaction::whereNotIn('payment_method', ['cash', 'credit'])->where('type', 'income')->sum('amount');
        $appExpense = (float) Transaction::whereNotIn('payment_method', ['cash', 'credit'])->where('type', 'expense')->sum('amount');
        $appBalance = max(0, $appSalariesTotal + $appIncome - $appExpense);

        // Rollover surplus from previous cycles (if any)
        $prevIncome = (float) Transaction::where('type', 'income')->where('date', '<', $cycleStart->toDateString())->sum('amount');
        $prevExpenses = (float) Transaction::where('type', 'expense')->where('date', '<', $cycleStart->toDateString())->sum('amount');
        $prevSavings = (float) Transaction::where('type', 'savings')->where('date', '<', $cycleStart->toDateString())->sum('amount');
        $surplusCarriedOver = max(0, $prevIncome - $prevExpenses - $prevSavings);

        // Deduct reserved savings from active app spendable balance
        $appSpendableBalance = max(0, $appBalance - $actualSavedOrReserved);

        // Daily safe pacing anchored to spendable app balance after deducting savings (rounded to whole integer)
        $activeDailyBase = $appBalance > 0 ? $appSpendableBalance : $netRemaining;
        $safeDaily = ($isCurrentCycle && $daysRemaining > 0) ? max(0, (int) round($activeDailyBase / $daysRemaining)) : 0;

        // Today's live pacing and remaining allowance
        $todayStr = Carbon::today()->toDateString();
        $todayExpenses = (float) Transaction::where('type', 'expense')
            ->where('date', $todayStr)
            ->sum('amount');

        // Today's baseline allowance: before today's expenses, the available pool was ($activeDailyBase + $todayExpenses)
        $todayBaseAllowance = ($isCurrentCycle && $daysRemaining > 0)
            ? max(0, (int) round(($activeDailyBase + $todayExpenses) / $daysRemaining))
            : $safeDaily;

        if ($todayBaseAllowance <= 0 && $safeDaily > 0) {
            $todayBaseAllowance = $safeDaily;
        }

        $todayRemaining = max(0, (float) ($todayBaseAllowance - $todayExpenses));
        $todayOverspend = max(0, (float) ($todayExpenses - $todayBaseAllowance));
        $todaySpentPercent = $todayBaseAllowance > 0
            ? min(100, (int) round(($todayExpenses / $todayBaseAllowance) * 100))
            : ($todayExpenses > 0 ? 100 : 0);

        // Process active daily surplus harvesting for debts and saving goals automatically
        try {
            DebtSurplusHarvestService::processActiveHarvests((float) $safeDaily);
        } catch (\Throwable $e) {
            // Silently bypass if database tables not yet migrated
        }

        try {
            GoalSurplusHarvestService::processActiveHarvests((float) $safeDaily);
        } catch (\Throwable $e) {
            // Silently bypass if database tables not yet migrated
        }

        try {
            $debts = Debt::with(['records', 'harvestLogs'])->latest()->get();
            foreach ($debts as $d) {
                $d->recalculateBalance();
            }
        } catch (\Throwable $e) {
            $debts = Debt::with('records')->latest()->get();
            foreach ($debts as $d) {
                $d->recalculateBalance();
            }
        }
        $totalDebtsOwed = (float) $debts->where('status', 'active')->sum('total_debt');
        $activeDebtsCount = $debts->where('status', 'active')->count();
        $completedHarvestDebts = $debts->filter(fn ($d) => $d->is_harvest_complete && $d->status === 'active');

        $daysPercent = (int) round(($daysElapsed / $totalCycleDays) * 100);
        $spentPercent = $totalIncome > 0 ? (int) round(($totalExpenses / $totalIncome) * 100) : 0;

        // 3. 50/30/20 Breakdown & Wallet Balances (Anchored to App Salaries, leaving Cash untouched)
        $budgetBase = $appSalariesTotal > 0 ? $appSalariesTotal : ($totalSalary > 0 ? $totalSalary : $totalIncome);
        $needsBudget = $budgetBase * 0.5;
        $wantsBudget = $budgetBase * 0.3;
        $savingsBudget = $budgetBase * 0.2;

        $needsSpent = 0;
        $wantsSpent = 0;
        $savingsSpent = 0;

        $categoryChartLabels = [];
        $categoryChartValues = [];
        $categoryChartColors = [];

        foreach ($envelopes as $env) {
            $spent = $env->spent;
            if ($env->pillar === 'needs') {
                $needsSpent += $spent;
            } elseif ($env->pillar === 'wants') {
                $wantsSpent += $spent;
            } elseif ($env->pillar === 'savings') {
                $savingsSpent += $spent;
            }

            if ($spent > 0) {
                $categoryChartLabels[] = $env->icon.' '.$env->name;
                $categoryChartValues[] = $spent;
                $categoryChartColors[] = $env->color ?? '#10b981';
            }
        }

        // 4. Goals Total (converted accurately to base currency / Shekels)
        $totalSavedAllGoals = (float) $goals->sum(fn ($g) => $g->equivalent_in_shekel);
        $totalTargetAllGoals = (float) $goals->sum(fn ($g) => $g->target_equivalent_in_shekel);
        $overallGoalsPercent = $totalTargetAllGoals > 0 ? (int) round(($totalSavedAllGoals / $totalTargetAllGoals) * 100) : 0;

        // 5. Subscriptions Total
        $monthlySubsTotal = 0;
        $yearlySubsTotal = 0;
        foreach ($subscriptions as $s) {
            if ($s->period === 'monthly') {
                $monthlySubsTotal += (float) $s->cost;
                $yearlySubsTotal += ((float) $s->cost * 12);
            } else {
                $yearlySubsTotal += (float) $s->cost;
                $monthlySubsTotal += ((float) $s->cost / 12);
            }
        }

        // 6. Automatic Leak Detection Radar (رصد المبالغ الصغيرة المتكررة تلقائياً من واقع المصاريف)
        $cycleExpenses = Transaction::where('type', 'expense')
            ->where('date', '>=', $cycleStart->toDateString())
            ->where('date', '<', $cycleEnd->toDateString())
            ->with('envelope')
            ->get();

        $leakGroups = [];
        foreach ($cycleExpenses as $tx) {
            $amount = (float) $tx->amount;
            $envelope = $tx->envelope;
            $note = trim($tx->note ?? '');

            // Use note if present; otherwise envelope name
            $name = $note !== '' ? $note : ($envelope ? $envelope->name : 'مصروف يومي نثري');
            $groupKey = mb_strtolower($name);

            if (! isset($leakGroups[$groupKey])) {
                $leakGroups[$groupKey] = [
                    'name' => $name,
                    'count' => 0,
                    'total_spent' => 0,
                    'envelope_name' => $envelope ? $envelope->name : null,
                    'envelope_icon' => $envelope ? ($envelope->icon ?? '💸') : '💸',
                    'envelope_color' => $envelope ? ($envelope->color ?? '#f59e0b') : '#f59e0b',
                    'is_micro' => $amount <= 35,
                ];
            }

            $leakGroups[$groupKey]['count']++;
            $leakGroups[$groupKey]['total_spent'] += $amount;
            if ($amount <= 35) {
                $leakGroups[$groupKey]['is_micro'] = true;
            }
        }

        $detectedLeaks = collect($leakGroups)
            ->filter(fn ($item) => $item['is_micro'] || $item['count'] >= 2)
            ->sortByDesc('total_spent')
            ->take(6)
            ->map(function ($item) use ($daysElapsed, $totalCycleDays) {
                $avgCost = $item['count'] > 0 ? ($item['total_spent'] / $item['count']) : 0;
                if ($item['count'] >= 2 && $daysElapsed > 0) {
                    $projectedMonth = (int) round(($item['total_spent'] / $daysElapsed) * $totalCycleDays);
                } else {
                    $projectedMonth = (int) round($avgCost * 30);
                }
                $projectedYear = (int) round($projectedMonth * 12);

                if ($projectedYear >= 4500) {
                    $comparison = 'بطارية ليثيوم ممتازة أو جهاز لابتوب حديث';
                } elseif ($projectedYear >= 2500) {
                    $comparison = 'هاتف ذكي ممتاز أو صيانة طاقة شمسية كاملة';
                } elseif ($projectedYear >= 1200) {
                    $comparison = 'اشتراك إنترنت فايبر سريع لمدة سنة كاملة';
                } else {
                    $comparison = 'صندوق طوارئ نقدي يغنيك عن طلب السلف عند الحاجة';
                }

                return [
                    'name' => $item['name'],
                    'count' => $item['count'],
                    'total_spent' => (int) round($item['total_spent']),
                    'avg_cost' => round($avgCost, 1),
                    'projected_month' => $projectedMonth,
                    'projected_year' => $projectedYear,
                    'comparison' => $comparison,
                    'envelope_name' => $item['envelope_name'],
                    'envelope_icon' => $item['envelope_icon'],
                    'envelope_color' => $item['envelope_color'],
                ];
            })
            ->values();

        $totalDetectedLeakMonth = (int) $detectedLeaks->sum('projected_month');
        $totalDetectedLeakYear = (int) $detectedLeaks->sum('projected_year');

        $currencyRates = CurrencyService::getRates();
        $currenciesMeta = CurrencyService::getCurrencies();
        $currentCurrency = $settings->currency ?? 'ILS';
        $rateToSAR = CurrencyService::getRate($currentCurrency, 'SAR');
        $rateToILS = CurrencyService::getRate($currentCurrency, 'ILS');

        $validTabs = [
            'tab-dashboard',
            'tab-envelopes',
            'tab-goals',
            'tab-leaks',
            'tab-ledger',
            'tab-debts',
        ];
        $activeTab = session('active_tab') ?? request('tab') ?? 'tab-dashboard';
        if (! in_array($activeTab, $validTabs, true)) {
            $activeTab = 'tab-dashboard';
        }

        // Gamification, Streaks & Achievements
        $gamification = GamificationService::getGamificationData(
            $workspaceId,
            (float) $safeDaily,
            (float) $todayBaseAllowance,
            (float) $todayExpenses
        );

        // AI & Forecasts: Cashflow Runway Radar & Monthly Mirror Review
        $cashflowRunway = FinancialForecastService::calculateCashflowRunway(
            $workspaceId,
            (float) $appSpendableBalance,
            (float) $totalExpenses,
            (int) $daysElapsed,
            (int) $daysRemaining,
            $cycleEnd,
            (float) $safeDaily
        );

        $monthlyMirror = FinancialForecastService::generateMonthlyMirrorReport(
            $workspaceId,
            $cycleStart,
            $cycleEnd
        );

        return view('dashboard', compact(
            'activeTab',
            'settings',
            'envelopes',
            'goals',
            'recentTransactions',
            'allTransactions',
            'wishlist',
            'subscriptions',
            'totalCycleDays',
            'daysElapsed',
            'daysRemaining',
            'daysPercent',
            'spentPercent',
            'monthLabel',
            'totalIncome',
            'totalExpenses',
            'netRemaining',
            'safeDaily',
            'todayBaseAllowance',
            'todayExpenses',
            'todayRemaining',
            'todayOverspend',
            'todaySpentPercent',
            'actualSavedOrReserved',
            'needsBudget',
            'wantsBudget',
            'savingsBudget',
            'needsSpent',
            'wantsSpent',
            'savingsSpent',
            'categoryChartLabels',
            'categoryChartValues',
            'categoryChartColors',
            'totalSavedAllGoals',
            'totalTargetAllGoals',
            'overallGoalsPercent',
            'monthlySubsTotal',
            'yearlySubsTotal',
            'currencyRates',
            'currenciesMeta',
            'currentCurrency',
            'rateToSAR',
            'rateToILS',
            'cashBalance',
            'appBalance',
            'budgetBase',
            'totalSalary',
            'salarySources',
            'appSalariesTotal',
            'cashSalariesTotal',
            'cycleStart',
            'cycleEnd',
            'isCurrentCycle',
            'cycleOffset',
            'surplusCarriedOver',
            'firstSalaryDay',
            'cycleMode',
            'salaryDay',
            'appSpendableBalance',
            'detectedLeaks',
            'totalDetectedLeakMonth',
            'totalDetectedLeakYear',
            'debts',
            'totalDebtsOwed',
            'activeDebtsCount',
            'completedHarvestDebts',
            'groceryItems',
            'pendingGroceryCount',
            'boughtGroceryCount',
            'estimatedGroceryTotal',
            'groceryCategories',
            'gamification',
            'cashflowRunway',
            'monthlyMirror'
        ));
    }
}
