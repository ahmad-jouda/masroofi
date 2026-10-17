<?php

namespace App\Services;

use App\Models\Debt;
use App\Models\Envelope;
use App\Models\SalarySource;
use App\Models\SavingGoal;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\UserSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FinancialAdvisorService
{
    /**
     * Convert Eastern Arabic numerals (٠-٩) to Western (0-9).
     */
    public static function normalizeArabicNumerals(string $str): string
    {
        $eastern = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $western = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace($eastern, $western, $str);
    }

    /**
     * Compile complete real-time financial snapshot for the given workspace.
     *
     * @return array<string, mixed>
     */
    public static function getFinancialSnapshot(int $workspaceId): array
    {
        $settings = UserSetting::firstOrCreate(
            ['workspace_id' => $workspaceId],
            [
                'salary' => 7500.00,
                'salary_day' => 27,
                'currency' => 'ILS',
                'cycle_mode' => 'first_salary',
            ]
        );

        $currency = $settings->currency ?? 'ILS';
        $currenciesMeta = CurrencyService::getCurrencies();
        $currencySymbol = $currenciesMeta[$currency]['symbol'] ?? '₪';
        $currencyName = $currenciesMeta[$currency]['name'] ?? 'شيكل';

        $today = Carbon::today();
        $firstSalaryDay = SalarySource::where('workspace_id', $workspaceId)->where('is_active', true)->min('day_of_month');
        $cycleMode = $settings->cycle_mode ?? 'first_salary';

        if ($cycleMode === 'first_salary' && $firstSalaryDay) {
            $salaryDay = (int) $firstSalaryDay;
        } else {
            $salaryDay = (int) ($settings->salary_day ?: ($firstSalaryDay ?: 1));
        }

        if ($today->day >= $salaryDay) {
            $cycleStart = Carbon::create($today->year, $today->month, min($salaryDay, 28))->startOfDay();
        } else {
            $cycleStart = Carbon::create($today->year, $today->month, min($salaryDay, 28))->subMonth()->startOfDay();
        }
        $cycleEnd = (clone $cycleStart)->addMonthNoOverflow();

        $totalCycleDays = max(1, $cycleStart->diffInDays($cycleEnd));
        $daysElapsed = max(0, $cycleStart->diffInDays($today));
        $daysRemaining = max(1, $today->diffInDays($cycleEnd, false));

        // Income & Expenses
        $startDateStr = $cycleStart->toDateString();
        $endDateStr = $cycleEnd->toDateString();

        $salarySources = SalarySource::where('workspace_id', $workspaceId)->where('is_active', true)->get();
        $totalSalary = $salarySources->isNotEmpty()
            ? (float) $salarySources->sum(fn ($s) => $s->amount_in_shekel)
            : (float) ($settings->salary ?? 0);

        $appSalariesTotal = (float) $salarySources->where('payment_method', '!=', 'cash')->sum(fn ($s) => $s->amount_in_shekel);
        $cashSalariesTotal = (float) $salarySources->where('payment_method', 'cash')->sum(fn ($s) => $s->amount_in_shekel);

        $appIncome = (float) Transaction::where('workspace_id', $workspaceId)
            ->whereNotIn('payment_method', ['cash', 'credit'])
            ->where('type', 'income')
            ->sum('amount');

        $appExpense = (float) Transaction::where('workspace_id', $workspaceId)
            ->whereNotIn('payment_method', ['cash', 'credit'])
            ->where('type', 'expense')
            ->sum('amount');

        $appBalance = max(0.0, $appSalariesTotal + $appIncome - $appExpense);
        $incomeBase = $appSalariesTotal > 0 ? $appSalariesTotal : $totalSalary;

        $cycleExpenses = (float) Transaction::where('workspace_id', $workspaceId)
            ->where('type', 'expense')
            ->whereBetween('date', [$startDateStr, $endDateStr])
            ->sum('amount');

        $todayExpenses = (float) Transaction::where('workspace_id', $workspaceId)
            ->where('type', 'expense')
            ->whereDate('date', $today->toDateString())
            ->sum('amount');

        $targetSavingsAmount = ($totalSalary * ($settings->savings_target_percent ?? 20)) / 100;
        $totalSavingsTransfers = (float) Transaction::where('workspace_id', $workspaceId)
            ->where('type', 'savings')
            ->where('date', '>=', $startDateStr)
            ->where('date', '<', $endDateStr)
            ->sum('amount');
        $actualSavedOrReserved = max($targetSavingsAmount, $totalSavingsTransfers);

        $appSpendableBalance = max(0.0, round($appBalance - $actualSavedOrReserved, 2));
        if ($appSpendableBalance <= 0 && $appBalance > 0) {
            $appSpendableBalance = (float) round($appBalance, 2);
        }

        $safeDaily = $daysRemaining > 0 ? (float) max(0, (int) round($appSpendableBalance / $daysRemaining)) : 0.0;
        $todayRemaining = max(0.0, round($safeDaily - $todayExpenses, 1));

        // Runway & Radar
        $cashflowRunway = FinancialForecastService::calculateCashflowRunway(
            $workspaceId,
            $appSpendableBalance,
            $cycleExpenses,
            $daysElapsed,
            $daysRemaining,
            $cycleEnd,
            $safeDaily
        );

        // Active Debts
        $debts = Debt::where('workspace_id', $workspaceId)->where('status', 'active')->get();
        $totalDebtsOwed = (float) $debts->sum('total_debt');
        $debtsList = $debts->map(fn ($d) => [
            'person' => $d->person_name,
            'amount' => (float) $d->total_debt,
            'due_date' => $d->due_date ? Carbon::parse($d->due_date)->format('Y-m-d') : null,
            'harvest_enabled' => (bool) $d->surplus_harvest_enabled,
        ])->toArray();

        // Saving Goals
        $goals = SavingGoal::where('workspace_id', $workspaceId)->get();
        $totalSavedAllGoals = (float) $goals->sum('current_amount');
        $goalsList = $goals->map(fn ($g) => [
            'name' => $g->name,
            'saved' => (float) $g->current_amount,
            'target' => (float) $g->target_amount,
            'percent' => $g->target_amount > 0 ? (int) round(($g->current_amount / $g->target_amount) * 100) : 0,
        ])->toArray();

        // Envelopes
        $envelopes = Envelope::where('workspace_id', $workspaceId)->get()->map(fn ($e) => [
            'name' => $e->name,
            'spent' => (float) $e->spent,
            'budget' => (float) $e->monthly_limit,
            'pillar' => $e->pillar,
        ])->toArray();

        // Subscriptions
        $subsTotal = (float) Subscription::where('workspace_id', $workspaceId)->get()->sum(fn ($s) => $s->monthly_cost);

        return [
            'currency' => $currency,
            'currency_symbol' => $currencySymbol,
            'currency_name' => $currencyName,
            'salary_day' => $salaryDay,
            'days_elapsed' => $daysElapsed,
            'days_remaining' => $daysRemaining,
            'total_cycle_days' => $totalCycleDays,
            'total_salary' => $totalSalary,
            'income_base' => $incomeBase,
            'cycle_expenses' => $cycleExpenses,
            'today_expenses' => $todayExpenses,
            'app_spendable_balance' => $appSpendableBalance,
            'safe_daily' => $safeDaily,
            'today_remaining' => $todayRemaining,
            'cashflow_runway' => $cashflowRunway,
            'total_debts_owed' => $totalDebtsOwed,
            'debts' => $debtsList,
            'total_saved_goals' => $totalSavedAllGoals,
            'goals' => $goalsList,
            'envelopes' => $envelopes,
            'monthly_subs_total' => $subsTotal,
        ];
    }

    /**
     * Ask the AI Financial Advisor.
     * Uses external LLM if configured; otherwise seamlessly uses local NLP Financial Intelligence Engine.
     *
     * @return array{message: string, verdict: string, context_data: array<string, mixed>}
     */
    public static function ask(int $workspaceId, ?int $userId, string $question): array
    {
        $question = trim($question);
        $snapshot = self::getFinancialSnapshot($workspaceId);

        // 1. Check for external LLM API (Gemini or OpenAI)
        $externalKey = config('services.ai_advisor.gemini_key') ?: env('GEMINI_API_KEY');
        if (! $externalKey) {
            $externalKey = config('services.ai_advisor.openai_key') ?: env('OPENAI_API_KEY');
        }

        if ($externalKey) {
            try {
                $llmResult = self::queryLLM($question, $snapshot, $externalKey);
                if ($llmResult) {
                    return $llmResult;
                }
            } catch (\Throwable $e) {
                Log::warning('AI Advisor external LLM call failed, falling back to local engine: '.$e->getMessage());
            }
        }

        // 2. Local Intelligent Financial NLP Engine
        return self::analyzeLocal($question, $snapshot);
    }

    /**
     * Local Intelligent Financial NLP Engine.
     * Understands Levantine/Palestinian Arabic colloquial queries and evaluates real numbers.
     *
     * @param  array<string, mixed>  $snap
     * @return array{message: string, verdict: string, context_data: array<string, mixed>}
     */
    public static function analyzeLocal(string $question, array $snap): array
    {
        $normalized = self::normalizeArabicNumerals($question);
        $sym = $snap['currency_symbol'];
        $balance = (float) $snap['app_spendable_balance'];
        $daily = (float) $snap['safe_daily'];
        $daysRem = (int) $snap['days_remaining'];

        // =========================================================================
        // INTENT 1: Purchase Feasibility ("هل ميزانيتي تسمح بشراء X بـ Y؟", "بدي اشتري...")
        // =========================================================================
        $purchaseIntent = self::extractPurchaseDetails($normalized);
        if ($purchaseIntent) {
            return self::evaluatePurchaseDecision($purchaseIntent, $snap);
        }

        // =========================================================================
        // INTENT 2: General Financial Health & Pacing ("كيف وضعي المالي؟", "كيف وتيرة الصرف؟")
        // =========================================================================
        if (preg_match('/(وضع|وضعي|حالي|حالتنا|الصرف|معدل|وتيرة|الراتب|بيكفي|بينفذ|رادار)/iu', $normalized)) {
            $runway = $snap['cashflow_runway'];
            $burnRate = $runway['burn_rate'] ?? 0;
            $runwayDays = $runway['runway_days'] ?? 0;
            $status = $runway['status'] ?? 'safe';

            if ($status === 'safe') {
                $verdict = 'safe';
                $msg = "🟢 **وضعك المالي ممتاز ومطمئن جداً!**\n\n"
                    .'- **الرصيد المتاح حالياً:** '.number_format($balance, 1)." {$sym}\n"
                    ."- **معدل صرفك اليومي:** {$burnRate} {$sym}/يوم (ضمن الحد الآمن)\n"
                    ."- **اليومية المسموحة:** {$daily} {$sym}/يوم للأيام الـ {$daysRem} المتبقية حتى الراتب القادم.\n"
                    .'- **الفائض المتوقع بنهاية الدورة:** حوالي '.number_format($runway['projected_surplus'] ?? 0, 0)." {$sym}.\n\n"
                    .'💡 **نصيحتي لك:** استمر على هذا الانضباط، ويمكنك تفعيل حصاد الفائض اليومي لتغذية أهدافك أو سداد ديونك تلقائياً دون أن تشعر!';
            } else {
                $verdict = 'danger';
                $gapDays = $runway['gap_days'] ?? 3;
                $exDay = $runway['exhaustion_day'] ?? 24;
                $exMonth = $runway['exhaustion_month'] ?? 'هذا الشهر';
                $recDaily = $runway['recommended_daily'] ?? $daily;

                $msg = "⚠️ **تنبيه رادار الميزانية: وتيرة صرفك الحالية تحتاج ضبطاً سريعاً!**\n\n"
                    .'- **الرصيد المتاح:** '.number_format($balance, 1)." {$sym}\n"
                    ."- **معدل صرفك الفعلي:** {$burnRate} {$sym}/يوم\n"
                    ."- **الخطر المتوقع:** بمعدل الصرف الحالي، قد ينفذ رصيد التطبيق قبل موعد الراتب بـ {$gapDays} أيام (حوالي يوم {$exDay} {$exMonth})!\n\n"
                    ."🎯 **خطة الإنقاذ السريعة:**\n"
                    .'الموصى به حالياً خفض مصروفك اليومي لـ **'.(int) $recDaily." {$sym}/يوم** للأيام الـ {$daysRem} القادمة، وتأجيل أي مشتريات كمالية غير ضرورية للوصول لموعد الراتب بسلام ودون الحاجة للاستدانة.";
            }

            return [
                'message' => $msg,
                'verdict' => $verdict,
                'context_data' => [
                    'type' => 'status_review',
                    'balance' => $balance,
                    'safe_daily' => $daily,
                    'burn_rate' => $burnRate,
                    'days_remaining' => $daysRem,
                ],
            ];
        }

        // =========================================================================
        // INTENT 3: Savings Advice ("كيف أحوش؟", "كم بقدر أوفر؟", "طريقة ادخار")
        // =========================================================================
        if (preg_match('/(أحوش|احوش|اوفر|أوفر|ادخار|توفير|حصالة|هدف|صندوق)/iu', $normalized)) {
            $totalSaved = $snap['total_saved_goals'];
            $targetSurplus = max(0, round($balance * 0.15, 0));

            $msg = "💰 **استراتيجية التوفير الذكية لمصروفي:**\n\n"
                .'- **إجمالي مدخراتك في الأهداف حالياً:** '.number_format($totalSaved, 1)." {$sym}\n"
                .'- **الرصيد المتاح في ميزانيتك:** '.number_format($balance, 1)." {$sym}\n"
                ."- **فائض التوفير المقترح لهذا الشهر:** حوالي {$targetSurplus} {$sym} دون التأثير على مصاريفك الأساسية.\n\n"
                ."🌟 **3 خطوات عملية أنصحك بها اليوم:**\n"
                ."1. **تفعيل حصاد الفائض (Surplus Harvesting):** فعّل الحصاد الآلي لأهدافك؛ أي مبلغ يتبقى من يوميتك عند منتصف الليل سيتحول تلقائياً للحصالة.\n"
                ."2. **صندوق الطوارئ أولاً:** اجعل هدفك الأول تجميع ما يعادل مصاريف شهر واحد لحماية أسرتك من أي طارئ.\n"
                .'3. **قاعدة الـ 48 ساعة:** قبل شراء أي غرض غير أساسي، انتظر 48 ساعة وسجله في «قائمة الأمنيات»؛ ستجد أن 60% من رغبات الشراء تتلاشى تلقائياً!';

            return [
                'message' => $msg,
                'verdict' => 'info',
                'context_data' => [
                    'type' => 'savings_advice',
                    'total_saved' => $totalSaved,
                    'suggested_surplus' => $targetSurplus,
                ],
            ];
        }

        // =========================================================================
        // INTENT 4: Debts Strategy ("شو أسدد من ديوني؟", "كيف أخلص الديون؟", "ديون")
        // =========================================================================
        if (preg_match('/(دين|ديون|سداد|تسديد|قرض|سلفة|اسدد|أسدد)/iu', $normalized)) {
            $totalDebts = (float) $snap['total_debts_owed'];
            $debts = $snap['debts'];

            if ($totalDebts <= 0 || empty($debts)) {
                $msg = "🎉 **ما شاء الله! ليس عليك أي ديون معلقة حالياً في التطبيق.**\n\n"
                    .'سجلك المالي نظيف تماماً، وهذه نقطة قوة عظيمة تتيح لك توجيه كامل فائضك لبناء مدخرات واستثمارات أسرتك!';
                $verdict = 'safe';
            } else {
                $verdict = 'caution';
                $closestDebt = collect($debts)->sortBy('amount')->first();
                $debtName = $closestDebt['person'] ?? 'أحد الدائنين';
                $debtAmt = $closestDebt['amount'] ?? 0;

                $msg = "🤝 **خطة سداد الديون الذكية (طريقة كرة الثلج Snowball):**\n\n"
                    .'- **إجمالي الديون القائمة:** '.number_format($totalDebts, 1)." {$sym} موزعة على ".count($debts)." التزامات.\n"
                    ."- **الأولوية الأولى الموصى بها:** البدء بسداد دين **{$debtName}** (قيمته {$debtAmt} {$sym}) لأنه الأقل حجماً، وسداده سيمنحك دفعة نفسية وانضباطاً كبيراً!\n\n"
                    ."💡 **نصيحة المستشار:**\n"
                    .'يمكنك تفعيل «حصاد الفائض التلقائي للدين» من قسم الديون، ليتم سداد جزء من الدين تلقائياً من بواقي يوميتك يومياً دون أن تشعر بأي عبء إضافي!';
            }

            return [
                'message' => $msg,
                'verdict' => $verdict,
                'context_data' => [
                    'type' => 'debt_advice',
                    'total_debts' => $totalDebts,
                ],
            ];
        }

        // =========================================================================
        // INTENT 5: Expense Breakdown ("وين بروح مصروفي؟", "شو أكثر شي بصرف عليه؟")
        // =========================================================================
        if (preg_match('/(وين|أين|بروح|رايح|مصروفي|اكثر|أكثر|بصرف|استنزاف|ظرف)/iu', $normalized)) {
            $envelopes = collect($snap['envelopes'])->sortByDesc('spent')->values();
            $topEnv = $envelopes->first();

            if ($topEnv && $topEnv['spent'] > 0) {
                $envName = $topEnv['name'];
                $spent = $topEnv['spent'];
                $msg = "📊 **تحليل خريطة مصاريفك للشهر الحالي:**\n\n"
                    ."- **أكثر ظرف استنزف ميزانيتك:** ظرف **«{$envName}»** بإجمالي صرف بلغ **".number_format($spent, 1)." {$sym}**.\n"
                    .'- **إجمالي مصاريف الدورة حتى الآن:** '.number_format($snap['cycle_expenses'], 1)." {$sym}.\n\n"
                    ."💡 **توجيه المستشار:**\n"
                    ."إذا رغبت في توفير فوري سريع، ركّز على تقنين بنود هذا الظرف للأيام الـ {$daysRem} القادمة، واحرص على تسجيل مشتريات البقالة في «قائمة المقاضي المشتركة» لتجنب تكرار شراء الأغراض الموجودة في البيت مسبقاً.";
            } else {
                $msg = "📊 **مصاريفك للشهر الحالي ما زالت في بدايتها وموزعة بتوازن.**\n\n"
                    .'- إجمالي المنصرف حتى الآن: '.number_format($snap['cycle_expenses'], 1)." {$sym}\n"
                    .'- رصيدك المتاح: '.number_format($balance, 1)." {$sym}\n"
                    ."- يوميتك الآمنة: {$daily} {$sym}/يوم.\n\n"
                    .'حافظ على تسجيل كل حركة مصروف صغيرة أولاً بأول لتظل متحكماً بميزانيتك بدقة!';
            }

            return [
                'message' => $msg,
                'verdict' => 'info',
                'context_data' => [
                    'type' => 'breakdown_review',
                    'top_envelope' => $topEnv['name'] ?? null,
                ],
            ];
        }

        // =========================================================================
        // INTENT 6: Default Conversational Guide / Smart Greeting
        // =========================================================================
        $msg = "مرحباً بك! أنا **مستشارك المالي الذكي** في تطبيق «مصروفي» 🤖✨\n\n"
            ."أنا مطلع لحظياً على كامل أرقام ميزانيتك ومدخراتك:\n"
            .'- **رصيدك المتاح للصرف حالياً:** '.number_format($balance, 1)." {$sym}\n"
            ."- **يوميتك المسموحة الآمنة:** {$daily} {$sym}/يوم\n"
            ."- **الأيام المتبقية حتى الراتب:** {$daysRem} يوماً\n\n"
            ."💬 **اسألني باللغة العامية المريحة، مثل:**\n"
            ."• *«هل ميزانيتي تسمح بشراء شاشة بـ 800 شيكل هذا الأسبوع؟»*\n"
            ."• *«كيف وضعي المالي وهل وتيرة صرفي آمنة؟»*\n"
            ."• *«كيف أحوش وكم أقدر أوفر هالشهر؟»*\n"
            ."• *«شو أسدد من ديوني أولاً؟»*\n\n"
            .'أنا هنا لأساعدك على اتخاذ قرارات مالية حكيمة تحمي استقرار بيتك وأسرتك!';

        return [
            'message' => $msg,
            'verdict' => 'info',
            'context_data' => [
                'type' => 'welcome_overview',
                'balance' => $balance,
                'safe_daily' => $daily,
            ],
        ];
    }

    /**
     * Extract purchase amount, item name, and timeframe from colloquial queries.
     *
     * @return array{amount: float, item: string, timeframe: string}|null
     */
    protected static function extractPurchaseDetails(string $text): ?array
    {
        // 1. Check for purchase action keywords
        $isPurchase = preg_match('/(شراء|اشتري|أشتري|اشري|أشري|أجيب|اجيب|أخذ|اخذ|شريت|اشتريت|بدي|حابب|نفسي|بفكر|تسمح)/iu', $text);
        if (! $isPurchase) {
            return null;
        }

        // 2. Extract number (Amount)
        // Match numbers optionally preceded by "بـ" or followed by currency
        $amount = 0.0;
        if (preg_match('/(?:بـ|ب|بقيمة|بسعر|بمبلغ)?\s*(\d+(?:\.\d+)?)\s*(?:شيكل|ريال|دولار|جنيه|درهم|دينار|₪|SAR|\$)?/iu', $text, $matches)) {
            // Find the most relevant numeric match
            preg_match_all('/\b\d+(?:\.\d+)?\b/', $text, $allNums);
            if (! empty($allNums[0])) {
                // If there's a number after "بـ" or in the string, pick the amount
                $amount = (float) end($allNums[0]);
                foreach ($allNums[0] as $num) {
                    if ((float) $num >= 10) { // likely item cost rather than days
                        $amount = (float) $num;
                        break;
                    }
                }
            }
        }

        if ($amount <= 0) {
            return null;
        }

        // 3. Extract Item Name
        $item = 'هذا الغرض';
        if (preg_match('/(?:شراء|اشتري|أشتري|اجيب|أجيب|أخذ|اخذ)\s+([^\d\s\?؟]+(?:\s+[^\d\s\?؟]+)?)/iu', $text, $itemMatch)) {
            $candidate = trim($itemMatch[1]);
            $candidate = preg_replace('/\s+بـ?$|\s+ب$/u', '', $candidate);
            $candidate = trim($candidate);
            // Filter out common stopwords
            if (! in_array($candidate, ['بـ', 'ب', 'هذا', 'هذه', 'لي', 'لنا', 'هيك', 'اليوم', 'شيء', 'غرض', ''], true)) {
                $item = $candidate;
            }
        }

        // 4. Extract timeframe
        $timeframe = 'هذا الأسبوع';
        if (preg_match('/(اليوم|هاليوم)/iu', $text)) {
            $timeframe = 'اليوم';
        } elseif (preg_match('/(هالشهر|هذا الشهر|الشهر)/iu', $text)) {
            $timeframe = 'هذا الشهر';
        } elseif (preg_match('/(هالأسبوع|هذا الأسبوع|الأسبوع)/iu', $text)) {
            $timeframe = 'هذا الأسبوع';
        }

        return [
            'amount' => $amount,
            'item' => $item,
            'timeframe' => $timeframe,
        ];
    }

    /**
     * Evaluate purchase feasibility and impact on cashflow, daily allowance, and debts.
     *
     * @param  array{amount: float, item: string, timeframe: string}  $purchase
     * @param  array<string, mixed>  $snap
     * @return array{message: string, verdict: string, context_data: array<string, mixed>}
     */
    protected static function evaluatePurchaseDecision(array $purchase, array $snap): array
    {
        $amount = $purchase['amount'];
        $item = $purchase['item'];
        $timeframe = $purchase['timeframe'];

        $sym = $snap['currency_symbol'];
        $balance = (float) $snap['app_spendable_balance'];
        $safeDaily = (float) $snap['safe_daily'];
        $daysRem = max(1, (int) $snap['days_remaining']);
        $totalDebts = (float) $snap['total_debts_owed'];

        $balanceAfter = $balance - $amount;
        $newSafeDaily = max(0.0, round($balanceAfter / $daysRem, 1));
        $dailyDrop = round($safeDaily - $newSafeDaily, 1);

        $contextData = [
            'type' => 'purchase_verdict',
            'item' => $item,
            'amount' => $amount,
            'current_balance' => $balance,
            'balance_after' => $balanceAfter,
            'current_daily' => $safeDaily,
            'new_daily' => $newSafeDaily,
            'daily_drop' => $dailyDrop,
            'days_remaining' => $daysRem,
            'timeframe' => $timeframe,
        ];

        // -------------------------------------------------------------------------
        // Case 1: DANGER / HIGH RISK (الرصيد لا يغطي أو يسبب عجزاً شديداً)
        // -------------------------------------------------------------------------
        if ($balanceAfter <= 0 || ($balanceAfter < ($safeDaily * 3) && $daysRem > 4)) {
            $verdict = 'danger';

            $weeksToSave = max(2, (int) ceil($amount / max(50, ($safeDaily * 3))));
            $weeklySaveAmt = (int) round($amount / $weeksToSave);

            $msg = "🔴 **القرار المالي: لا أنصحك إطلاقاً بشراء «{$item}» الآن — مخاطرة مالية عالية!**\n\n"
                ."بناءً على فحص دقيق لميزانيتك ومدخراتك:\n"
                ."- **سعر {$item}:** ".number_format($amount, 0)." {$sym}\n"
                .'- **رصيدك المتاح حالياً:** '.number_format($balance, 1)." {$sym}\n";

            if ($balanceAfter < 0) {
                $deficit = abs($balanceAfter);
                $msg .= '- ⚠️ **عجز مالي فوري:** المبلغ يتجاوز رصيدك المتاح بمقدار '.number_format($deficit, 1)." {$sym} وسيجبرك على السلف أو كسر مدخرات الطوارئ!\n\n";
            } else {
                $msg .= '- ⚠️ **استنزاف شبه كامل:** سيتبقى معك فقط '.number_format($balanceAfter, 1)." {$sym} لتغطية {$daysRem} يوماً متبقية حتى الراتب، مما يعني أن يوميتك ستنهار إلى **{$newSafeDaily} {$sym}/يوم** فقط!\n\n";
            }

            if ($totalDebts > 0) {
                $msg .= '📌 **تذكير بالالتزامات:** لديك ديون قائمة بقيمة '.number_format($totalDebts, 0)." {$sym} والأولوية القصوى هي عدم مراكمة التزامات جديدة.\n\n";
            }

            $msg .= "💡 **الحل البديل الذكي والمريح:**\n"
                ."لا تحرم نفسك منها، ولكن خطط لشرائها بذكاء: أنشئ لها هدفاً في «أهداف التحويش» ووفر **{$weeklySaveAmt} {$sym} أسبوعياً**؛ ستتمكن من شرائها نقداً بعد **{$weeksToSave} أسابيع** براحة واطمئنان دون أن تتأثر مصاريف بيتك الأساسية!";

            return [
                'message' => $msg,
                'verdict' => $verdict,
                'context_data' => $contextData,
            ];
        }

        // -------------------------------------------------------------------------
        // Case 2: CAUTION / FEASIBLE WITH SACRIFICE (ممكن ولكن بضغط على اليومية)
        // -------------------------------------------------------------------------
        if ($newSafeDaily < ($safeDaily * 0.6) || $amount > ($balance * 0.4)) {
            $verdict = 'caution';

            $msg = "🟡 **القرار المالي: شراء «{$item}» ممكن، ولكن بحذر وتضحية في مصروفك اليومي!**\n\n"
                ."تحليل الأرقام والأثر المالي:\n"
                ."- **سعر {$item}:** ".number_format($amount, 0)." {$sym}\n"
                .'- **رصيدك المتاح حالياً:** '.number_format($balance, 1)." {$sym} (سيتبقى معك **".number_format($balanceAfter, 1)." {$sym}**)\n"
                ."- **الأثر على يوميتك:** ستنخفض يوميتك الآمنة من **{$safeDaily} {$sym}** إلى **{$newSafeDaily} {$sym}/يوم** للأيام الـ {$daysRem} المتبقية حتى الراتب.\n\n"
                ."⚖️ **تقدير المستشار:**\n"
                ."إذا كان شراء {$item} ضرورياً وملحاً ومستعد لضغط مصاريف الطلعات والكماليات خلال الأسبوعين القادمين، يمكنك الشراء.\n"
                .'أما إذا كان الغرض كمالياً أو يمكن تأجيله، فالأنسب الانتظار حتى نزول الراتب القادم لشرائها دون أن تشعر بأي تضييق على ميزانية أسرتك.';

            return [
                'message' => $msg,
                'verdict' => $verdict,
                'context_data' => $contextData,
            ];
        }

        // -------------------------------------------------------------------------
        // Case 3: SAFE / HIGHLY RECOMMENDED (مسموح وآمن وميزانيتك ممتازة)
        // -------------------------------------------------------------------------
        $verdict = 'safe';

        $msg = "🟢 **القرار المالي: نعم، ميزانيتك تسمح تماماً بشراء «{$item}» {$timeframe}!**\n\n"
            ."أرقامك مطمئنة وفي مسار آمن جداً:\n"
            ."- **سعر {$item}:** ".number_format($amount, 0)." {$sym}\n"
            .'- **رصيدك المتاح حالياً:** '.number_format($balance, 1)." {$sym}\n"
            .'- **الرصيد بعد الشراء:** سيتبقى معك **'.number_format($balanceAfter, 1)." {$sym}** فائض سيولة مريح.\n"
            ."- **يوميتك الجديدة:** ستبقى يوميتك المسموحة عند **{$newSafeDaily} {$sym}/يوم** للأيام الـ {$daysRem} المتبقية، وهي تغطي مصاريفك المعتادة بكل راحة ودون أي عجز.\n\n"
            ."🎉 **توكل على الله واشترِ {$item} وهنيئاً لك ولأسرتك!**";

        return [
            'message' => $msg,
            'verdict' => $verdict,
            'context_data' => $contextData,
        ];
    }

    /**
     * Query External LLM (Gemini or OpenAI) with injected live financial context.
     *
     * @param  array<string, mixed>  $snap
     * @return array{message: string, verdict: string, context_data: array<string, mixed>}|null
     */
    protected static function queryLLM(string $question, array $snap, string $apiKey): ?array
    {
        $sym = $snap['currency_symbol'];
        $balance = $snap['app_spendable_balance'];
        $daily = $snap['safe_daily'];
        $daysRem = $snap['days_remaining'];
        $burnRate = $snap['cashflow_runway']['burn_rate'] ?? 0;
        $debts = $snap['total_debts_owed'];
        $savings = $snap['total_saved_goals'];

        $systemPrompt = "أنت «مستشار مصروفي الذكي» (AI Financial Advisor) لتطبيق مالي عائلي.
تتحدث باللغة العربية بلهجة شامية/فلسطينية ودودة، حكيمة، مشجعة، وواقعية جداً.
المستخدم يسألك عن قرارات الشراء وإدارة ميزانيته.
البيانات المالية الحية للمستخدم الآن:
- العملة: {$sym} ({$snap['currency_name']})
- الرصيد المتاح حالياً للصرف: {$balance} {$sym}
- اليومية المسموحة الآمنة: {$daily} {$sym}/يوم
- الأيام المتبقية حتى الراتب القادم: {$daysRem} يوماً
- معدل الصرف اليومي الفعلي: {$burnRate} {$sym}/يوم
- إجمالي الديون المعلقة: {$debts} {$sym}
- إجمالي المدخرات في الأهداف: {$savings} {$sym}

مهمتك:
1. حلل السؤال بدقة بناءً على الأرقام الحقيقية المذكورة أعلاه.
2. إذا كان السؤال عن شراء غرض (مثلاً شاشة أو هاتف)، احسب: هل يغطي الرصيد؟ كم سيتبقى؟ كم ستصبح اليومية الجديدة؟
3. أعطِ حكماً واضحاً ومباشراً في البداية: (🟢 مسموح وآمن، أو 🟡 ممكن بحذر وضغط، أو 🔴 غير مستحسن ومخاطرة).
4. كن لطيفاً ومختصراً ومفيداً كأنك خبير مالي وصديق ناصح.";

        // Try Gemini 1.5 Flash / Pro
        try {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}";
            $response = Http::timeout(10)->post($url, [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => "{$systemPrompt}\n\nسؤال المستخدم: {$question}"],
                        ],
                    ],
                ],
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $reply = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
                if ($reply) {
                    $verdict = 'info';
                    if (str_contains($reply, '🟢') || str_contains($reply, 'مسموح') || str_contains($reply, 'آمن')) {
                        $verdict = 'safe';
                    } elseif (str_contains($reply, '🟡') || str_contains($reply, 'بحذر') || str_contains($reply, 'ممكن')) {
                        $verdict = 'caution';
                    } elseif (str_contains($reply, '🔴') || str_contains($reply, 'لا أنصح') || str_contains($reply, 'مخاطرة')) {
                        $verdict = 'danger';
                    }

                    return [
                        'message' => $reply,
                        'verdict' => $verdict,
                        'context_data' => [
                            'source' => 'llm_gemini',
                            'balance' => $balance,
                            'safe_daily' => $daily,
                        ],
                    ];
                }
            }
        } catch (\Throwable $e) {
            // Fall through to local
        }

        return null;
    }
}
