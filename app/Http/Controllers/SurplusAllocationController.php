<?php

namespace App\Http\Controllers;

use App\Models\Debt;
use App\Models\SavingGoal;
use App\Models\Transaction;
use App\Services\CurrencyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SurplusAllocationController extends Controller
{
    public function allocate(Request $request)
    {
        $validated = $request->validate([
            'surplus_amount' => 'required|numeric|min:0.01',
            'debt_id' => 'nullable|exists:debts,id',
            'debt_percentage' => 'nullable|numeric|min:0|max:100',
            'save_debt_auto_harvest' => 'nullable|boolean',
            'goal_id' => 'nullable|exists:saving_goals,id',
            'goal_percentage' => 'nullable|numeric|min:0|max:100',
            'save_goal_auto_harvest' => 'nullable|boolean',
            'execution_mode' => 'nullable|in:scheduled,instant',
        ]);

        $surplus = (float) $validated['surplus_amount'];
        $debtPct = isset($validated['debt_percentage']) ? (float) $validated['debt_percentage'] : 0;
        $goalPct = isset($validated['goal_percentage']) ? (float) $validated['goal_percentage'] : 0;
        $executionMode = $request->input('execution_mode', 'instant');

        if (($debtPct + $goalPct) > 100) {
            return back()->with('error', 'مجموع النسب المقتطعة لا يمكن أن يتجاوز 100% من الفائض اليومي.');
        }

        if ($debtPct <= 0 && $goalPct <= 0) {
            return redirect()->route('dashboard')->with('success', '🛡️ تم الإبقاء على كامل الفائض ('.number_format($surplus, 0).' ₪) في يوميتك لرفع مخصصات الأيام القادمة تلقائياً!');
        }

        if ($debtPct > 0 && empty($validated['debt_id'])) {
            return back()->with('error', 'يرجى اختيار الدين المراد توجيه السداد إليه.');
        }

        if ($goalPct > 0 && empty($validated['goal_id'])) {
            return back()->with('error', 'يرجى اختيار حصالة التحويش المراد توجيه المبلغ إليها.');
        }

        // 🌙 Scheduled Mode: Just save the distribution policy for midnight without touching live liquidity now!
        if ($executionMode === 'scheduled') {
            $messages = [];
            if ($debtPct > 0 && ! empty($validated['debt_id'])) {
                $debt = Debt::findOrFail($validated['debt_id']);
                $debt->update([
                    'is_harvest_active' => true,
                    'harvest_ratio' => $debtPct / 100,
                    'harvest_started_at' => now()->toDateString(),
                ]);
                $messages[] = "تخصيص {$debtPct}% لدين [{$debt->name}]";
            }

            if ($goalPct > 0 && ! empty($validated['goal_id'])) {
                $goal = SavingGoal::findOrFail($validated['goal_id']);
                $goal->update([
                    'is_harvest_active' => true,
                    'harvest_ratio' => $goalPct / 100,
                    'harvest_started_at' => now()->toDateString(),
                ]);
                $messages[] = "تخصيص {$goalPct}% لحصالة [{$goal->name}]";
            }

            $bufferPct = max(0, 100 - ($debtPct + $goalPct));
            if ($bufferPct > 0) {
                $messages[] = "إبقاء {$bufferPct}% لرفع يوميات باقي الشهر";
            }

            $summary = implode(' و ', $messages);

            return redirect()->route('dashboard')->with('success', "🌙 تم تثبيت خطة التوزيع بنجاح ({$summary})! سيتم اقتطاع الفائض الفعلي المتبقي في وقته المحدد (عند منتصف الليل)، دون المساس برصيد يوميتك الحالية الآن.");
        }

        $debtILS = 0;
        $goalILS = 0;
        $goalAmountConverted = 0;
        $goalCurrency = 'ILS';
        $debtName = '';
        $goalName = '';

        DB::transaction(function () use (
            $validated, $surplus, $debtPct, $goalPct,
            &$debtILS, &$goalILS, &$goalAmountConverted, &$goalCurrency, &$debtName, &$goalName
        ) {
            $workspaceId = auth()->user()?->workspace_id ?? 1;
            $userId = auth()->id() ?? 1;

            // 1. Process Debt Allocation (if percentage > 0 and debt selected)
            if ($debtPct > 0 && ! empty($validated['debt_id'])) {
                $debt = Debt::findOrFail($validated['debt_id']);
                $debtName = $debt->name;
                $rawDebtILS = round($surplus * ($debtPct / 100), 2);
                $debtILS = min($rawDebtILS, (float) $debt->total_debt);

                if ($debtILS > 0) {
                    $tx = Transaction::create([
                        'workspace_id' => $workspaceId,
                        'user_id' => $userId,
                        'type' => 'expense',
                        'amount' => $debtILS,
                        'date' => now()->toDateString(),
                        'payment_method' => 'transfer',
                        'note' => "سداد دين من فائض اليومية ({$debtPct}%): {$debt->name}",
                    ]);

                    $debt->records()->create([
                        'workspace_id' => $workspaceId,
                        'user_id' => $userId,
                        'type' => 'repayment',
                        'amount' => $debtILS,
                        'date' => now()->toDateString(),
                        'payment_method' => 'transfer',
                        'note' => "سداد فوري من فائض اليومية ({$debtPct}%)",
                        'transaction_id' => $tx->id,
                    ]);

                    $debt->recalculateBalance();
                }
            }

            // 2. Process Saving Goal Allocation (if percentage > 0 and goal selected)
            if ($goalPct > 0 && ! empty($validated['goal_id'])) {
                $goal = SavingGoal::findOrFail($validated['goal_id']);
                $goalName = $goal->name;
                $goalCurrency = strtoupper($goal->currency ?? 'ILS');
                $goalILS = round($surplus * ($goalPct / 100), 2);

                if ($goalILS > 0) {
                    $goalAmountConverted = $goalCurrency === 'ILS'
                        ? $goalILS
                        : round(CurrencyService::convert($goalILS, 'ILS', $goalCurrency), 2);

                    $goal->increment('current_amount', $goalAmountConverted);

                    Transaction::create([
                        'workspace_id' => $workspaceId,
                        'user_id' => $userId,
                        'type' => 'savings',
                        'amount' => $goalILS,
                        'goal_id' => $goal->id,
                        'date' => now()->toDateString(),
                        'payment_method' => 'transfer',
                        'note' => "اقتناص فائض اليومية ({$goalPct}%) لحصالة: {$goal->name} (+{$goalAmountConverted} {$goalCurrency})",
                    ]);
                }
            }
        });

        // Build descriptive feedback
        $messages = [];
        if ($debtILS > 0) {
            $messages[] = "سداد {$debtILS} ₪ لحساب دين {$debtName}";
        }
        if ($goalILS > 0) {
            $messages[] = "ترحيل {$goalAmountConverted} {$goalCurrency} ({$goalILS} ₪) لحصالة {$goalName}";
        }
        $remainingDayILS = max(0, round($surplus - ($debtILS + $goalILS), 2));
        if ($remainingDayILS > 0) {
            $messages[] = "المتبقي في يوميتك {$remainingDayILS} ₪ يرحل لرفع يوميات باقي الشهر";
        }

        // Save auto-harvest persistence if user toggled it
        if ($request->boolean('save_debt_auto_harvest') && ! empty($validated['debt_id']) && $debtPct > 0) {
            $debtModel = Debt::find($validated['debt_id']);
            if ($debtModel) {
                $debtModel->update([
                    'is_harvest_active' => true,
                    'harvest_ratio' => $debtPct / 100,
                    'harvest_started_at' => $debtModel->harvest_started_at ?? now()->toDateString(),
                ]);
                $messages[] = "⚡ تم تثبيت استقطاع {$debtPct}% تلقائياً من فائض نهاية كل يوم لسداد دين {$debtName}";
            }
        }

        if ($request->boolean('save_goal_auto_harvest') && ! empty($validated['goal_id']) && $goalPct > 0) {
            $goalModel = SavingGoal::find($validated['goal_id']);
            if ($goalModel) {
                $goalModel->update([
                    'is_harvest_active' => true,
                    'harvest_ratio' => $goalPct / 100,
                    'harvest_started_at' => $goalModel->harvest_started_at ?? now()->toDateString(),
                ]);
                $messages[] = "⚡ تم تثبيت استقطاع {$goalPct}% تلقائياً من فائض نهاية كل يوم لحصالة {$goalName}";
            }
        }

        $fullMsg = '🎉 تم توزيع فائض اليوم بنجاح: '.implode(' | ', $messages);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $fullMsg,
                'debt_ils' => $debtILS,
                'goal_ils' => $goalILS,
                'remaining_ils' => $remainingDayILS,
            ]);
        }

        return redirect()->route('dashboard')->with('success', $fullMsg);
    }
}
