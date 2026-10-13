<?php

namespace App\Http\Controllers;

use App\Models\UserSetting;
use App\Models\Wishlist;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'price' => 'required|numeric|min:1',
            'duration_hours' => 'nullable|integer|min:1',
        ]);

        $item = Wishlist::create([
            'title' => $validated['title'],
            'price' => $validated['price'],
            'duration_hours' => $validated['duration_hours'] ?? 48,
            'status' => 'pending',
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'item' => $item]);
        }

        return redirect()->route('dashboard')->with('success', 'تمت إضافة الرغبة لقائمة فترة التهدئة ⏳');
    }

    public function update(Request $request, Wishlist $wishlist)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,saved,bought',
        ]);

        $wishlist->update(['status' => $validated['status']]);

        if ($validated['status'] === 'saved') {
            $settings = UserSetting::find(1);
            if ($settings) {
                $settings->increment('saved_from_impulse', $wishlist->price);
            }
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('dashboard')->with('success', 'تم تحديث حالة السلعة بنجاح');
    }

    public function destroy(Request $request, Wishlist $wishlist)
    {
        $wishlist->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('dashboard')->with('success', 'تم حذف العنصر');
    }
}
