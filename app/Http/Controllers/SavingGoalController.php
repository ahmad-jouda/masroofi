<?php

namespace App\Http\Controllers;

use App\Models\SavingGoal;
use App\Models\Transaction;
use App\Services\CurrencyService;
use App\Services\GoalSurplusHarvestService;
use Illuminate\Http\Request;

class SavingGoalController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'icon' => 'nullable|string|max:20',
            'target_amount' => 'required|numeric|min:1',
            'current_amount' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'deadline' => 'nullable|date',
            'is_harvest_active' => 'nullable|boolean',
            'harvest_ratio' => 'nullable|numeric|min:1|max:100',
        ]);

        $isHarvestActive = $request->boolean('is_harvest_active');
        $harvestRatio = $request->filled('harvest_ratio') ? ((float) $request->input('harvest_ratio') / 100) : 0.50;

        $goal = SavingGoal::create([
            'name' => $validated['name'],
            'icon' => $validated['icon'] ?? '🎯',
            'target_amount' => $validated['target_amount'],
            'current_amount' => $validated['current_amount'] ?? 0,
            'currency' => $validated['currency'] ?? 'ILS',
            'deadline' => $validated['deadline'] ?? null,
            'is_harvest_active' => $isHarvestActive,
            'harvest_ratio' => $harvestRatio,
            'harvest_started_at' => $isHarvestActive ? now()->toDateString() : null,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'goal' => $goal]);
        }

        return redirect()->route('dashboard')->with('success', 'تم إنشاء حصالة التحويش بنجاح 🎯');
    }

    public function deposit(Request $request, SavingGoal $goal)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);

        $goalCurrency = strtoupper($goal->currency ?? 'ILS');
        $baseAmount = $goalCurrency === 'ILS'
            ? (float) $validated['amount']
            : CurrencyService::convert((float) $validated['amount'], $goalCurrency, 'ILS');

        $goal->increment('current_amount', $validated['amount']);

        Transaction::create([
            'type' => 'savings',
            'amount' => $baseAmount,
            'goal_id' => $goal->id,
            'date' => now()->toDateString(),
            'payment_method' => 'transfer',
            'note' => 'إيداع في حصالة: '.$goal->name.' ('.$validated['amount'].' '.$goalCurrency.')',
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'goal' => $goal]);
        }

        return redirect()->route('dashboard')->with('success', 'تم إيداع المبلغ في حصالة التحويش بنجاح 🎉');
    }

    public function harvestSurplus(Request $request, SavingGoal $goal)
    {
        $validated = $request->validate([
            'percentage' => 'required|numeric|min:1|max:100',
            'surplus_amount' => 'required|numeric|min:0.01',
        ]);

        $percentage = (float) $validated['percentage'];
        $surplus = (float) $validated['surplus_amount'];

        $harvestILS = round($surplus * ($percentage / 100), 2);

        if ($harvestILS <= 0) {
            return back()->with('error', 'المبلغ المقتطع قليل جداً للتسجيل.');
        }

        $goalCurrency = strtoupper($goal->currency ?? 'ILS');
        $harvestInGoalCurrency = $goalCurrency === 'ILS'
            ? $harvestILS
            : round(CurrencyService::convert($harvestILS, 'ILS', $goalCurrency), 2);

        $goal->increment('current_amount', $harvestInGoalCurrency);

        Transaction::create([
            'type' => 'savings',
            'amount' => $harvestILS,
            'goal_id' => $goal->id,
            'date' => now()->toDateString(),
            'payment_method' => 'transfer',
            'note' => "اقتناص فائض اليومية ({$percentage}%) لحصالة: {$goal->name} (+{$harvestInGoalCurrency} {$goalCurrency})",
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'goal' => $goal,
                'harvested_ils' => $harvestILS,
                'harvested_goal_currency' => $harvestInGoalCurrency,
            ]);
        }

        $formattedGoalAmount = number_format($harvestInGoalCurrency, 2).' '.$goalCurrency;
        $formattedIls = number_format($harvestILS, 2).' ₪';

        return redirect()->route('dashboard')->with('success', "🎉 تم اقتناص {$percentage}% من فائض اليومية بنجاح! تم ترحيل {$formattedGoalAmount} ({$formattedIls}) إلى حصالة: {$goal->name}");
    }

    public function activateHarvest(Request $request, SavingGoal $goal)
    {
        $validated = $request->validate([
            'ratio' => 'required|numeric|min:1|max:100',
            'max_daily' => 'nullable|numeric|min:0',
        ]);

        $ratio = (float) $validated['ratio'] / 100;
        $maxDaily = ! empty($validated['max_daily']) ? (float) $validated['max_daily'] : null;

        $goal->update([
            'is_harvest_active' => true,
            'harvest_ratio' => $ratio,
            'harvest_max_daily' => $maxDaily,
            'harvest_started_at' => now()->toDateString(),
        ]);

        $pct = (int) $validated['ratio'];
        $currency = strtoupper($goal->currency ?? 'ILS');

        return redirect()->route('dashboard')->with('success', "🚀 تم تفعيل الاقتطاع التلقائي لحصالة [{$goal->name}] بنجاح! سيتم استقطاع {$pct}% تلقائياً من أي فائض في نهاية كل يوم وإيداعه بالـ {$currency}.");
    }

    public function toggleHarvest(Request $request, SavingGoal $goal)
    {
        $goal->update([
            'is_harvest_active' => ! $goal->is_harvest_active,
        ]);

        $statusText = $goal->is_harvest_active ? 'تفعيل' : 'إيقاف مؤقت لـ';

        return redirect()->route('dashboard')->with('success', "تم {$statusText} الاقتطاع التلقائي من الفائض لهذه الحصالة ⚙️");
    }

    public function getHarvestLogs(SavingGoal $goal)
    {
        $goal->load(['harvestLogs']);
        $forecast = GoalSurplusHarvestService::getTodayForecast($goal);

        return response()->json([
            'success' => true,
            'goal' => $goal,
            'logs' => $goal->harvestLogs,
            'forecast' => $forecast,
        ]);
    }

    public function destroy(Request $request, SavingGoal $goal)
    {
        $goal->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('dashboard')->with('success', 'تم حذف هدف التحويش');
    }
}
