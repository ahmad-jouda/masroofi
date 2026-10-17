<?php

namespace App\Http\Controllers;

use App\Models\DebtRecord;
use App\Models\Envelope;
use App\Models\SalarySource;
use App\Models\SavingGoal;
use App\Models\Transaction;
use App\Models\UserSetting;
use App\Models\Workspace;
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
            'transactions' => 'nullable|array',
            'transactions.*.type' => 'required|in:expense,income,savings',
            'transactions.*.amount' => 'required|numeric|min:0.01',
            'transactions.*.envelope_id' => 'nullable|exists:envelopes,id',
            'transactions.*.goal_id' => 'nullable|exists:saving_goals,id',
            'transactions.*.date' => 'required|date',
            'transactions.*.payment_method' => 'required|in:card,cash,transfer,credit',
            'transactions.*.note' => 'nullable|string|max:255',
            'updates' => 'nullable|array',
            'updates.*.id' => 'required|integer',
            'updates.*.type' => 'required|in:expense,income,savings',
            'updates.*.amount' => 'required|numeric|min:0.01',
            'updates.*.envelope_id' => 'nullable|exists:envelopes,id',
            'updates.*.goal_id' => 'nullable|exists:saving_goals,id',
            'updates.*.date' => 'required|date',
            'updates.*.payment_method' => 'required|in:card,cash,transfer,credit',
            'updates.*.note' => 'nullable|string|max:255',
        ]);

        $transactionsList = $validated['transactions'] ?? [];
        $updatesList = $validated['updates'] ?? [];

        if (empty($transactionsList) && empty($updatesList)) {
            return response()->json([
                'success' => false,
                'message' => 'لا توجد بيانات أو معاملات للمزامنة',
            ], 422);
        }

        $created = [];
        $updated = [];
        $affectedDates = collect();

        DB::transaction(function () use ($transactionsList, $updatesList, &$created, &$updated, &$affectedDates) {
            foreach ($transactionsList as $txData) {
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
                if ($tx->type === 'expense') {
                    $affectedDates->push(Carbon::parse($tx->date)->toDateString());
                }
            }

            foreach ($updatesList as $upData) {
                $tx = Transaction::find($upData['id']);
                if (! $tx) {
                    continue;
                }

                $oldDate = Carbon::parse($tx->date)->toDateString();
                $oldType = $tx->type;

                if ($oldType === 'expense') {
                    $affectedDates->push($oldDate);
                }

                // Revert old saving goal
                if ($tx->type === 'savings' && $tx->goal_id) {
                    $oldGoal = SavingGoal::find($tx->goal_id);
                    if ($oldGoal) {
                        $oldGoal->decrement('current_amount', min((float) $oldGoal->current_amount, (float) $tx->amount));
                    }
                }

                $isSavings = ($upData['type'] === 'savings');
                $isIncome = ($upData['type'] === 'income');

                $tx->update([
                    'type' => $upData['type'],
                    'amount' => $upData['amount'],
                    'envelope_id' => ! $isIncome ? ($upData['envelope_id'] ?? null) : null,
                    'goal_id' => $isSavings ? ($upData['goal_id'] ?? null) : null,
                    'date' => $upData['date'],
                    'payment_method' => $upData['payment_method'],
                    'note' => $upData['note'] ?? null,
                ]);

                // Apply new saving goal
                if ($tx->type === 'savings' && ! empty($tx->goal_id)) {
                    $newGoal = SavingGoal::find($tx->goal_id);
                    if ($newGoal) {
                        $newGoal->increment('current_amount', $tx->amount);
                    }
                }

                $updated[] = $tx;
                if ($tx->type === 'expense') {
                    $affectedDates->push(Carbon::parse($tx->date)->toDateString());
                }
            }
        });

        foreach ($affectedDates->unique()->filter(fn ($d) => Carbon::parse($d)->lt(Carbon::today())) as $expDate) {
            DailySurplusAdjustmentService::recalculateForDate($expDate);
        }

        $totalSynced = count($created) + count($updated);

        return response()->json([
            'success' => true,
            'synced_count' => $totalSynced,
            'created_count' => count($created),
            'updated_count' => count($updated),
            'transactions' => $created,
            'updates' => $updated,
            'message' => 'تمت المزامنة بنجاح ✅',
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

    /**
     * Export Transactions Ledger report as printable PDF statement view.
     */
    public function exportPdf(Request $request)
    {
        $workspaceId = $request->user()?->workspace_id ?? 1;
        $workspace = $request->user()?->workspace ?? Workspace::find($workspaceId);
        $settings = UserSetting::firstOrCreate(['workspace_id' => $workspaceId]);

        $period = $request->query('period', 'current_cycle');
        $type = $request->query('type', 'all');
        $envelopeId = $request->query('envelope_id');
        $userId = $request->query('user_id');
        $fromDate = $request->query('from');
        $toDate = $request->query('to');

        // Determine date range
        $today = Carbon::today();
        $startDate = null;
        $endDate = null;
        $periodLabel = 'كل السجل الزمني';

        if ($period === 'current_cycle') {
            $firstSalaryDay = SalarySource::where('is_active', true)->min('day_of_month');
            $cycleMode = $settings->cycle_mode ?? 'first_salary';
            $salaryDay = ($cycleMode === 'first_salary' && $firstSalaryDay) ? (int) $firstSalaryDay : (int) ($settings->salary_day ?: ($firstSalaryDay ?: 1));

            if ($today->day >= $salaryDay) {
                $startDate = Carbon::create($today->year, $today->month, min($salaryDay, 28))->startOfDay();
            } else {
                $startDate = Carbon::create($today->year, $today->month, min($salaryDay, 28))->subMonth()->startOfDay();
            }
            $endDate = (clone $startDate)->addMonthNoOverflow()->subDay()->endOfDay();
            $periodLabel = "دورة الراتب الحالية ({$startDate->format('Y/m/d')} إلى {$endDate->format('Y/m/d')})";
        } elseif ($period === 'current_month') {
            $startDate = $today->copy()->startOfMonth();
            $endDate = $today->copy()->endOfMonth();
            $periodLabel = "الشهر الحالي ({$startDate->format('Y/m/d')} إلى {$endDate->format('Y/m/d')})";
        } elseif ($period === 'last_month') {
            $startDate = $today->copy()->subMonth()->startOfMonth();
            $endDate = $today->copy()->subMonth()->endOfMonth();
            $periodLabel = "الشهر السابق ({$startDate->format('Y/m/d')} إلى {$endDate->format('Y/m/d')})";
        } elseif ($period === 'custom' && $fromDate && $toDate) {
            $startDate = Carbon::parse($fromDate)->startOfDay();
            $endDate = Carbon::parse($toDate)->endOfDay();
            $periodLabel = "فترة مخصصة ({$startDate->format('Y/m/d')} إلى {$endDate->format('Y/m/d')})";
        }

        $query = Transaction::with(['envelope', 'goal', 'user']);

        if ($startDate && $endDate) {
            $query->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()]);
        }

        if ($type && $type !== 'all') {
            $query->where('type', $type);
        }

        if ($envelopeId) {
            $query->where('envelope_id', $envelopeId);
        }

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $transactions = $query->orderBy('date', 'desc')->orderBy('id', 'desc')->get();

        $totalExpenses = (float) $transactions->where('type', 'expense')->sum('amount');
        $totalIncome = (float) $transactions->where('type', 'income')->sum('amount');
        $totalSavings = (float) $transactions->where('type', 'savings')->sum('amount');
        $net = $totalIncome - $totalExpenses - $totalSavings;

        $envelopes = Envelope::all();
        $members = $workspace ? $workspace->users()->get() : collect([$request->user()]);

        return view('reports.ledger-pdf', compact(
            'transactions',
            'workspace',
            'settings',
            'period',
            'type',
            'envelopeId',
            'userId',
            'fromDate',
            'toDate',
            'periodLabel',
            'totalExpenses',
            'totalIncome',
            'totalSavings',
            'net',
            'envelopes',
            'members'
        ));
    }
}
