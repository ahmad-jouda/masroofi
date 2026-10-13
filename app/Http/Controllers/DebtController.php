<?php

namespace App\Http\Controllers;

use App\Models\Debt;
use App\Models\Transaction;
use App\Services\DebtSurplusHarvestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DebtController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'type' => 'required|in:supermarket,person,store,other',
            'initial_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $defaultIcons = [
            'supermarket' => '🛒',
            'person' => '👤',
            'store' => '🏪',
            'other' => '📑',
        ];

        $debt = Debt::create([
            'name' => $validated['name'],
            'type' => $validated['type'],
            'icon' => $defaultIcons[$validated['type']] ?? '🛒',
            'notes' => $validated['notes'] ?? null,
            'total_debt' => 0.00,
            'status' => 'active',
        ]);

        $initialAmount = (float) ($validated['initial_amount'] ?? 0);
        if ($initialAmount > 0) {
            $debt->records()->create([
                'type' => 'add_debt',
                'amount' => $initialAmount,
                'date' => now()->toDateString(),
                'note' => 'رصيد افتتاحي مستحق',
            ]);
            $debt->recalculateBalance();
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'debt' => $debt]);
        }

        return redirect()->route('dashboard')->with('success', 'تم فتح حساب الدين بنجاح ✅');
    }

    public function addRecord(Request $request, Debt $debt)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'note' => 'nullable|string|max:255',
        ]);

        $debt->records()->create([
            'type' => 'add_debt',
            'amount' => $validated['amount'],
            'date' => $validated['date'],
            'note' => $validated['note'] ?: 'مشتريات بالدين / استدانة',
        ]);

        $debt->recalculateBalance();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'debt' => $debt->fresh()]);
        }

        return redirect()->route('dashboard')->with('success', 'تم تسجيل المشتريات / الاستدانة بنجاح 📑');
    }

    public function repay(Request $request, Debt $debt)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'payment_method' => 'required|in:transfer,cash,card',
            'note' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($validated, $debt) {
            $tx = Transaction::create([
                'type' => 'expense',
                'amount' => $validated['amount'],
                'date' => $validated['date'],
                'payment_method' => $validated['payment_method'],
                'note' => $validated['note'] ?: "سداد دين: {$debt->name}",
            ]);

            $debt->records()->create([
                'type' => 'repayment',
                'amount' => $validated['amount'],
                'date' => $validated['date'],
                'payment_method' => $validated['payment_method'],
                'note' => $validated['note'] ?: 'سداد دفعة من الدين',
                'transaction_id' => $tx->id,
            ]);

            $debt->recalculateBalance();
        });

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'debt' => $debt->fresh()]);
        }

        return redirect()->route('dashboard')->with('success', 'تم سداد الدين وتحديث الرصيد وقاعدة البيانات بنجاح 💳');
    }

    public function destroy(Debt $debt)
    {
        $debt->delete();

        return redirect()->route('dashboard')->with('success', 'تم حذف حساب الدين وسجلاته بنجاح');
    }

    public function activateHarvest(Request $request, Debt $debt)
    {
        $validated = $request->validate([
            'harvest_ratio' => 'required|numeric|min:0.1|max:1.0',
            'harvest_max_daily' => 'nullable|numeric|min:1',
            'harvest_started_at' => 'nullable|date',
        ]);

        $debt->update([
            'is_harvest_active' => true,
            'harvest_ratio' => $validated['harvest_ratio'],
            'harvest_max_daily' => $validated['harvest_max_daily'] ?? null,
            'harvest_started_at' => $validated['harvest_started_at'] ?: now()->toDateString(),
        ]);

        DebtSurplusHarvestService::processDebt($debt, DebtSurplusHarvestService::calculateCurrentSafeDaily());

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'debt' => $debt->fresh()]);
        }

        return redirect()->route('dashboard')->with('success', 'تم تفعيل مقتنص فوائض اليومية لسداد الدين بنجاح 🎯');
    }

    public function toggleHarvest(Debt $debt)
    {
        $debt->update([
            'is_harvest_active' => ! $debt->is_harvest_active,
        ]);

        $statusText = $debt->is_harvest_active ? 'تفعيل' : 'إيقاف مؤقت لـ';

        return redirect()->route('dashboard')->with('success', "تم {$statusText} مقتنص الفوائض لهذا الدين ⚙️");
    }

    public function repayFromHarvest(Request $request, Debt $debt)
    {
        $amount = (float) $debt->surplus_harvested;
        if ($amount <= 0) {
            return redirect()->route('dashboard')->with('error', 'لا يوجد رصيد مجمع من الفوائض بعد للسداد.');
        }

        $amountToRepay = min($amount, (float) $debt->total_debt);
        $paymentMethod = $request->input('payment_method', 'transfer');

        DB::transaction(function () use ($debt, $amountToRepay, $paymentMethod) {
            $tx = Transaction::create([
                'type' => 'expense',
                'amount' => $amountToRepay,
                'date' => now()->toDateString(),
                'payment_method' => $paymentMethod,
                'note' => "سداد دين: {$debt->name} (تم السداد بالكامل من فوائض اليوميات المجمعة 🎉)",
            ]);

            $debt->records()->create([
                'type' => 'repayment',
                'amount' => $amountToRepay,
                'date' => now()->toDateString(),
                'payment_method' => $paymentMethod,
                'note' => 'سداد كامل من حصالة فوائض اليومية',
                'transaction_id' => $tx->id,
            ]);

            $debt->recalculateBalance();
            $debt->update([
                'is_harvest_active' => false,
                'surplus_harvested' => max(0, $debt->surplus_harvested - $amountToRepay),
            ]);
        });

        return redirect()->route('dashboard')->with('success', '🎉 ألف مبروك! تم سداد الدين بالكامل وتصفية الحساب بنجاح من فوائض اليوميات!');
    }

    public function getHarvestLogs(Debt $debt)
    {
        $debt->load(['harvestLogs']);
        $forecast = DebtSurplusHarvestService::getTodayForecast($debt, DebtSurplusHarvestService::calculateCurrentSafeDaily());

        return response()->json([
            'success' => true,
            'debt' => $debt,
            'logs' => $debt->harvestLogs,
            'forecast' => $forecast,
        ]);
    }
}
