<?php

namespace App\Http\Controllers;

use App\Models\Envelope;
use App\Models\SalarySource;
use App\Models\SavingGoal;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\UserSetting;
use App\Models\Wishlist;
use App\Services\CurrencyService;
use Illuminate\Http\Request;

class UserSettingController extends Controller
{
    public function update(Request $request)
    {
        $workspaceId = $request->user()?->workspace_id ?? 1;
        $settings = UserSetting::firstOrCreate(['workspace_id' => $workspaceId]);
        $oldCurrency = $settings->currency ?? 'SAR';
        $oldSalary = (float) $settings->salary;

        $validated = $request->validate([
            'salary' => 'nullable|numeric|min:0',
            'salary_day' => 'nullable|integer|between:1,31',
            'cycle_mode' => 'nullable|in:first_salary,custom_day',
            'work_hours_per_week' => 'nullable|integer|between:1,100',
            'savings_target_percent' => 'nullable|integer|between:0,90',
            'currency' => 'nullable|string|max:10',
            'theme' => 'nullable|in:dark,light',
            'sound_enabled' => 'nullable|boolean',
        ]);

        $currencyConverted = false;
        $rateUsed = 1.0;
        $newCurrency = $validated['currency'] ?? null;

        if ($newCurrency && strtoupper($newCurrency) !== strtoupper($oldCurrency)) {
            $rateUsed = CurrencyService::convertAllFinancialData($oldCurrency, $newCurrency);
            $currencyConverted = true;

            // Currency field has already been updated in CurrencyService
            unset($validated['currency']);

            // If salary input was unchanged from the pre-conversion salary, do not overwrite the newly converted salary
            if (isset($validated['salary']) && abs((float) $validated['salary'] - $oldSalary) < 0.01) {
                unset($validated['salary']);
            }
        }

        $settings->update($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'settings' => $settings->fresh(),
                'converted' => $currencyConverted,
                'rate' => $rateUsed,
            ]);
        }

        $successMsg = 'تم حفظ وتحديث الإعدادات بنجاح';
        if ($currencyConverted) {
            $currenciesMeta = CurrencyService::getCurrencies();
            $oldMeta = $currenciesMeta[$oldCurrency] ?? ['name' => $oldCurrency, 'flag' => ''];
            $newMeta = $currenciesMeta[$newCurrency] ?? ['name' => $newCurrency, 'flag' => ''];
            $successMsg = "تم تحويل العملة بنجاح من {$oldMeta['flag']} {$oldMeta['name']} ({$oldCurrency}) إلى {$newMeta['flag']} {$newMeta['name']} ({$newCurrency})، وتم تحويل كافة المبالغ والميزانيات وفق سعر الصرف (1 {$oldCurrency} = {$rateUsed} {$newCurrency})!";
        }

        return redirect()->route('dashboard')->with('success', $successMsg);
    }

    /**
     * Refresh live currency exchange rates from external API.
     */
    public function refreshCurrencyRates(Request $request)
    {
        $rates = CurrencyService::refreshRates(true);
        $lastUpdated = CurrencyService::getLastUpdated();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'تم تحديث أسعار الصرف بنجاح من البورصة العالمية!',
                'rates' => $rates,
                'last_updated' => $lastUpdated,
                'currencies' => CurrencyService::getCurrencies(),
            ]);
        }

        return back()->with('success', 'تم تحديث أسعار الصرف الحية بنجاح!');
    }

    /**
     * Completely zero out and reset all financial data.
     */
    public function resetData(Request $request)
    {
        Transaction::query()->delete();
        SalarySource::query()->delete();
        SavingGoal::query()->delete();
        Wishlist::query()->delete();
        Subscription::query()->delete();
        Envelope::query()->update(['budget' => 0.00]);

        $workspaceId = $request->user()?->workspace_id ?? 1;
        $setting = UserSetting::firstOrCreate(['workspace_id' => $workspaceId]);
        $setting->update([
            'salary' => 0.00,
            'saved_from_impulse' => 0.00,
            'salary_day' => 1,
        ]);

        return redirect()->route('dashboard')->with('success', 'تم تصفير النظام والمصروفات والرواتب بالكامل! يمكنك الآن إدخال بياناتك من الصفر ✨');
    }

    /**
     * Start a new financial cycle immediately anchored from today.
     */
    public function startNewCycleNow(Request $request)
    {
        $workspaceId = $request->user()?->workspace_id ?? 1;
        $setting = UserSetting::firstOrCreate(['workspace_id' => $workspaceId]);
        $today = now();
        $setting->update([
            'salary_day' => $today->day,
        ]);

        return redirect()->route('dashboard')->with('success', "🎉 تم إقفال الدورة السابقة وبدء دورة مالية جديدة بنجاح تبدأ من اليوم ({$today->format('Y-m-d')})!");
    }
}
