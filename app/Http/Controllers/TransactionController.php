<?php

namespace App\Http\Controllers;

use App\Models\DebtRecord;
use App\Models\SavingGoal;
use App\Models\Transaction;
use App\Services\DailySurplusAdjustmentService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:expense,income,savings',
            'amount' => 'required|numeric|min:0.01',
            'envelope_id' => 'nullable|exists:envelopes,id',
            'goal_id' => 'nullable|exists:saving_goals,id',
            'date' => 'required|date',
            'payment_method' => 'required|in:card,cash,transfer,credit',
            'note' => 'nullable|string|max:255',
        ]);

        if ($validated['type'] !== 'savings') {
            $validated['goal_id'] = null;
        }
        if ($validated['type'] === 'income') {
            $validated['envelope_id'] = null;
        }

        $tx = Transaction::create($validated);

        if ($tx->type === 'savings' && $tx->goal_id) {
            $goal = SavingGoal::find($tx->goal_id);
            if ($goal) {
                $goal->increment('current_amount', $tx->amount);
            }
        }

        $adjustmentMessage = null;
        if ($tx->type === 'expense' && Carbon::parse($tx->date)->lt(Carbon::today())) {
            $adjustment = DailySurplusAdjustmentService::recalculateForDate($tx->date);
            if ($adjustment) {
                $adjustmentMessage = DailySurplusAdjustmentService::formatAdjustmentSummary($adjustment);
            }
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'transaction' => $tx->load(['envelope', 'goal', 'user']),
                'adjustment_message' => $adjustmentMessage,
            ]);
        }

        $msg = 'تم تسجيل المعاملة بنجاح ✅';
        if ($adjustmentMessage) {
            $msg .= ' '.$adjustmentMessage;
        }

        return redirect()->route('dashboard')->with('success', $msg);
    }

    public function update(Request $request, Transaction $transaction)
    {
        $validated = $request->validate([
            'type' => 'required|in:expense,income,savings',
            'amount' => 'required|numeric|min:0.01',
            'envelope_id' => 'nullable|exists:envelopes,id',
            'goal_id' => 'nullable|exists:saving_goals,id',
            'date' => 'required|date',
            'payment_method' => 'required|in:card,cash,transfer,credit',
            'note' => 'nullable|string|max:255',
        ]);

        if ($validated['type'] !== 'savings') {
            $validated['goal_id'] = null;
        }
        if ($validated['type'] === 'income') {
            $validated['envelope_id'] = null;
        }

        $oldDate = Carbon::parse($transaction->date)->toDateString();
        $oldType = $transaction->type;

        DB::transaction(function () use ($validated, $transaction) {
            // Revert old saving goal if was savings
            if ($transaction->type === 'savings' && $transaction->goal_id) {
                $oldGoal = SavingGoal::find($transaction->goal_id);
                if ($oldGoal) {
                    $oldGoal->decrement('current_amount', min((float) $oldGoal->current_amount, (float) $transaction->amount));
                }
            }

            // Update attributes
            $transaction->update($validated);

            // Apply new saving goal if now savings
            if ($transaction->type === 'savings' && ! empty($transaction->goal_id)) {
                $newGoal = SavingGoal::find($transaction->goal_id);
                if ($newGoal) {
                    $newGoal->increment('current_amount', $transaction->amount);
                }
            }
        });

        $fresh = $transaction->fresh();
        $newDate = Carbon::parse($fresh->date)->toDateString();
        $newType = $fresh->type;

        $adjMessages = [];
        if ($oldType === 'expense' && Carbon::parse($oldDate)->lt(Carbon::today())) {
            $adj1 = DailySurplusAdjustmentService::recalculateForDate($oldDate);
            if ($adj1) {
                $adjMessages[] = DailySurplusAdjustmentService::formatAdjustmentSummary($adj1);
            }
        }
        if ($newDate !== $oldDate && $newType === 'expense' && Carbon::parse($newDate)->lt(Carbon::today())) {
            $adj2 = DailySurplusAdjustmentService::recalculateForDate($newDate);
            if ($adj2) {
                $adjMessages[] = DailySurplusAdjustmentService::formatAdjustmentSummary($adj2);
            }
        }

        $adjText = ! empty($adjMessages) ? ' '.implode(' ', array_filter($adjMessages)) : '';

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'transaction' => $fresh,
                'adjustment_message' => trim($adjText) ?: null,
            ]);
        }

        return redirect()->route('dashboard')->with('success', 'تم تعديل المعاملة وتحديث الرصيد بنجاح ✏️'.$adjText);
    }

    public function syncOffline(Request $request)
    {
        $validated = $request->validate([
            'transactions' => 'required|array|min:1',
            'transactions.*.type' => 'required|in:expense,income,savings',
            'transactions.*.amount' => 'required|numeric|min:0.01',
            'transactions.*.envelope_id' => 'nullable|exists:envelopes,id',
            'transactions.*.goal_id' => 'nullable|exists:saving_goals,id',
            'transactions.*.date' => 'required|date',
            'transactions.*.payment_method' => 'required|in:card,cash,transfer,credit',
            'transactions.*.note' => 'nullable|string|max:255',
        ]);

        $created = [];

        DB::transaction(function () use ($validated, &$created) {
            foreach ($validated['transactions'] as $txData) {
                $isSavings = ($txData['type'] === 'savings');
                $isIncome = ($txData['type'] === 'income');
                $tx = Transaction::create([
                    'type' => $txData['type'],
                    'amount' => $txData['amount'],
                    'envelope_id' => ! $isIncome ? ($txData['envelope_id'] ?? null) : null,
                    'goal_id' => $isSavings ? ($txData['goal_id'] ?? null) : null,
                    'date' => $txData['date'],
                    'payment_method' => $txData['payment_method'],
                    'note' => $txData['note'] ?? null,
                ]);

                if ($tx->type === 'savings' && ! empty($tx->goal_id)) {
                    $goal = SavingGoal::find($tx->goal_id);
                    if ($goal) {
                        $goal->increment('current_amount', $tx->amount);
                    }
                }

                $created[] = $tx;
            }
        });

        $expenseDates = collect($validated['transactions'])
            ->where('type', 'expense')
            ->pluck('date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->filter(fn ($d) => Carbon::parse($d)->lt(Carbon::today()))
            ->unique();

        foreach ($expenseDates as $expDate) {
            DailySurplusAdjustmentService::recalculateForDate($expDate);
        }

        return response()->json([
            'success' => true,
            'synced_count' => count($created),
            'transactions' => $created,
            'message' => 'تمت مزامنة العمليات بنجاح ✅',
        ]);
    }

    public function destroy(Request $request, Transaction $transaction)
    {
        $date = Carbon::parse($transaction->date)->toDateString();
        $type = $transaction->type;

        if ($transaction->type === 'savings' && $transaction->goal_id) {
            $goal = SavingGoal::find($transaction->goal_id);
            if ($goal) {
                $goal->decrement('current_amount', min($goal->current_amount, $transaction->amount));
            }
        }

        // Link with DebtRecord: if this transaction was linked to a debt repayment, delete the record and recalculate balance
        $linkedDebtRecords = DebtRecord::where('transaction_id', $transaction->id)->get();
        foreach ($linkedDebtRecords as $record) {
            $debt = $record->debt;
            $record->delete();
            $debt?->recalculateBalance();
        }

        $transaction->delete();

        $adjText = '';
        if ($type === 'expense' && Carbon::parse($date)->lt(Carbon::today())) {
            $adj = DailySurplusAdjustmentService::recalculateForDate($date);
            if ($adj) {
                $adjText = ' '.DailySurplusAdjustmentService::formatAdjustmentSummary($adj);
            }
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'adjustment_message' => trim($adjText) ?: null]);
        }

        return redirect()->route('dashboard')->with('success', 'تم حذف المعاملة وتحديث الرصيد'.$adjText);
    }
}
