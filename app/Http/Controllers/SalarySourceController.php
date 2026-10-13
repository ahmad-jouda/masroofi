<?php

namespace App\Http\Controllers;

use App\Models\SalarySource;
use App\Models\UserSetting;
use Illuminate\Http\Request;

class SalarySourceController extends Controller
{
    /**
     * Store a newly created salary source.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'amount' => 'required|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'payment_method' => 'required|in:transfer,cash,card',
            'day_of_month' => 'nullable|integer|between:1,31',
            'expected_date' => 'nullable|date',
            'notes' => 'nullable|string|max:255',
        ]);

        $validated['currency'] = $validated['currency'] ?? 'ILS';
        $validated['day_of_month'] = $validated['day_of_month'] ?? 1;

        $source = SalarySource::create($validated);
        $this->syncTotalSalaryToSettings();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'source' => $source]);
        }

        return redirect()->route('dashboard')->with('success', 'تمت إضافة مصدر الراتب بنجاح! 💼');
    }

    /**
     * Update an existing salary source.
     */
    public function update(Request $request, SalarySource $salary)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'amount' => 'required|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'payment_method' => 'required|in:transfer,cash,card',
            'day_of_month' => 'nullable|integer|between:1,31',
            'expected_date' => 'nullable|date',
            'notes' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        $salary->update($validated);
        $this->syncTotalSalaryToSettings();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'source' => $salary]);
        }

        return redirect()->route('dashboard')->with('success', 'تم تحديث بيانات الراتب بنجاح! 💼');
    }

    /**
     * Delete a salary source.
     */
    public function destroy(SalarySource $salary)
    {
        $salary->delete();
        $this->syncTotalSalaryToSettings();

        return redirect()->route('dashboard')->with('success', 'تم حذف مصدر الراتب');
    }

    /**
     * Helper to keep UserSetting salary in sync with the sum of active salaries.
     */
    protected function syncTotalSalaryToSettings(): void
    {
        $activeSum = SalarySource::where('is_active', true)->get()->sum(fn ($s) => $s->amount_in_shekel);
        $setting = UserSetting::firstOrCreate(['id' => 1]);
        if ($activeSum > 0) {
            $setting->salary = $activeSum;
            $setting->save();
        }
    }
}
