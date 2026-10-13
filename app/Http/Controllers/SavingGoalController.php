<?php

namespace App\Http\Controllers;

use App\Models\SavingGoal;
use App\Models\Transaction;
use App\Services\CurrencyService;
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
        ]);

        $goal = SavingGoal::create([
            'name' => $validated['name'],
            'icon' => $validated['icon'] ?? '🎯',
            'target_amount' => $validated['target_amount'],
            'current_amount' => $validated['current_amount'] ?? 0,
            'currency' => $validated['currency'] ?? 'ILS',
            'deadline' => $validated['deadline'] ?? null,
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

    public function destroy(Request $request, SavingGoal $goal)
    {
        $goal->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('dashboard')->with('success', 'تم حذف هدف التحويش');
    }
}
