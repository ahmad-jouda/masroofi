<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'cost' => 'required|numeric|min:0.5',
            'period' => 'required|in:monthly,yearly',
        ]);

        $sub = Subscription::create($validated);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'subscription' => $sub]);
        }

        return redirect()->route('dashboard')->with('success', 'تم تسجيل الاشتراك الدوري بنجاح');
    }

    public function destroy(Request $request, Subscription $subscription)
    {
        $subscription->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('dashboard')->with('success', 'تم حذف الاشتراك');
    }
}
