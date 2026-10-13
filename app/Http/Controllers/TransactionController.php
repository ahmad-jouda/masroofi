<?php

namespace App\Http\Controllers;

use App\Models\SavingGoal;
use App\Models\Transaction;
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

        $tx = Transaction::create($validated);

        if ($tx->type === 'savings' && $tx->goal_id) {
            $goal = SavingGoal::find($tx->goal_id);
            if ($goal) {
                $goal->increment('current_amount', $tx->amount);
            }
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'transaction' => $tx]);
        }

        return redirect()->route('dashboard')->with('success', 'تم تسجيل المعاملة بنجاح ✅');
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

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'transaction' => $transaction->fresh()]);
        }

        return redirect()->route('dashboard')->with('success', 'تم تعديل المعاملة وتحديث الرصيد بنجاح ✏️');
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
                $tx = Transaction::create([
                    'type' => $txData['type'],
                    'amount' => $txData['amount'],
                    'envelope_id' => $txData['envelope_id'] ?? null,
                    'goal_id' => $txData['goal_id'] ?? null,
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

        return response()->json([
            'success' => true,
            'synced_count' => count($created),
            'transactions' => $created,
            'message' => 'تمت مزامنة العمليات بنجاح ✅',
        ]);
    }

    public function destroy(Request $request, Transaction $transaction)
    {
        if ($transaction->type === 'savings' && $transaction->goal_id) {
            $goal = SavingGoal::find($transaction->goal_id);
            if ($goal) {
                $goal->decrement('current_amount', min($goal->current_amount, $transaction->amount));
            }
        }

        $transaction->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('dashboard')->with('success', 'تم حذف المعاملة وتحديث الرصيد');
    }
}
