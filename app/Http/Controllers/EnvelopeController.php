<?php

namespace App\Http\Controllers;

use App\Models\Envelope;
use App\Models\SalarySource;
use App\Models\UserSetting;
use Illuminate\Http\Request;

class EnvelopeController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'icon' => 'nullable|string|max:20',
            'budget' => 'nullable|numeric|min:0',
            'pillar' => 'required|in:needs,wants,savings',
            'color' => 'nullable|string|max:20',
        ]);

        $env = Envelope::create([
            'name' => $validated['name'],
            'icon' => $validated['icon'] ?? '📦',
            'budget' => $validated['budget'] ?? 0.00,
            'pillar' => $validated['pillar'],
            'color' => $validated['color'] ?? '#10b981',
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'envelope' => $env]);
        }

        return redirect()->route('dashboard')->with('success', 'تم إنشاء ظرف الميزانية بنجاح');
    }

    public function update(Request $request, Envelope $envelope)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'icon' => 'nullable|string|max:20',
            'budget' => 'nullable|numeric|min:0',
            'pillar' => 'required|in:needs,wants,savings',
            'color' => 'nullable|string|max:20',
        ]);

        $envelope->update([
            'name' => $validated['name'],
            'icon' => $validated['icon'] ?? $envelope->icon,
            'budget' => $validated['budget'] ?? 0.00,
            'pillar' => $validated['pillar'],
            'color' => $validated['color'] ?? $envelope->color,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'envelope' => $envelope->fresh()]);
        }

        return redirect()->route('dashboard')->with('success', 'تم تعديل الظرف بنجاح ✏️');
    }

    public function destroy(Envelope $envelope)
    {
        $envelope->delete();

        return redirect()->route('dashboard')->with('success', 'تم حذف الظرف بنجاح 🗑️');
    }

    public function autoAllocate(Request $request)
    {
        $settings = UserSetting::find(1);
        $salarySources = SalarySource::where('is_active', true)->get();
        $appSalariesTotal = (float) $salarySources->where('payment_method', '!=', 'cash')->sum(fn ($s) => $s->amount_in_shekel ?? $s->amount);
        $totalSalaryFromSources = (float) $salarySources->sum(fn ($s) => $s->amount_in_shekel ?? $s->amount);

        // Distribute strictly based on App Salaries (leaving Cash untouched as separate liquidity)
        $salary = $appSalariesTotal > 0 ? $appSalariesTotal : ($totalSalaryFromSources > 0 ? $totalSalaryFromSources : (float) ($settings->salary ?? 7500));

        $needsTotal = $salary * 0.5;
        $wantsTotal = $salary * 0.3;
        $savingsTotal = $salary * 0.2;

        $needsEnvs = Envelope::where('pillar', 'needs')->get();
        $wantsEnvs = Envelope::where('pillar', 'wants')->get();
        $savingsEnvs = Envelope::where('pillar', 'savings')->get();

        if ($needsEnvs->count() > 0) {
            $share = round($needsTotal / $needsEnvs->count(), 2);
            foreach ($needsEnvs as $e) {
                $e->update(['budget' => $share]);
            }
        }

        if ($wantsEnvs->count() > 0) {
            $share = round($wantsTotal / $wantsEnvs->count(), 2);
            foreach ($wantsEnvs as $e) {
                $e->update(['budget' => $share]);
            }
        }

        if ($savingsEnvs->count() > 0) {
            $share = round($savingsTotal / $savingsEnvs->count(), 2);
            foreach ($savingsEnvs as $e) {
                $e->update(['budget' => $share]);
            }
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('dashboard')->with('success', 'تمت إعادة توزيع ميزانية الأظرف على رواتب التطبيق وفق قاعدة 50/30/20 بنجاح (مع بقاء الكاش منفصلاً)');
    }
}
