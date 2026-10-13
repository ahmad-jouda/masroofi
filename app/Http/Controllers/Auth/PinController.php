<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PinController extends Controller
{
    /**
     * Show the 4-digit PIN setup screen.
     */
    public function showSetupForm()
    {
        $user = Auth::user();

        return view('auth.pin-setup', compact('user'));
    }

    /**
     * Store or update the user's 4-digit PIN.
     */
    public function setup(Request $request)
    {
        $request->validate([
            'pin' => 'required|digits:4|confirmed',
        ], [
            'pin.required' => 'يرجى إدخال رمز الـ PIN',
            'pin.digits' => 'يجب أن يتكون رمز الـ PIN من 4 أرقام بالضبط',
            'pin.confirmed' => 'تأكيد رمز الـ PIN غير متطابق',
        ]);

        $user = Auth::user();
        $user->update([
            'pin_code' => Hash::make($request->pin),
            'pin_enabled' => true,
        ]);

        $request->session()->forget('masroofi_locked');

        // Set device cookie for quick unlock
        $cookie = cookie('masroofi_device_user_id', $user->id, 60 * 24 * 90);

        return redirect()->route('dashboard')
            ->withCookie($cookie)
            ->with('success', 'تم تفعيل رمز الـ PIN السريع بنجاح! يمكنك الآن الدخول به في أي وقت 🔒✨');
    }

    /**
     * Change or update user's 4-digit PIN with identity verification.
     */
    public function changePin(Request $request): RedirectResponse|JsonResponse
    {
        $user = Auth::user();

        $request->validate([
            'auth_method' => 'required|in:current_pin,password',
            'current_pin' => 'required_if:auth_method,current_pin|nullable|digits:4',
            'current_password' => 'required_if:auth_method,password|nullable|string',
            'new_pin' => 'required|digits:4|confirmed',
        ], [
            'auth_method.required' => 'يرجى اختيار طريقة التحقق من الهوية',
            'current_pin.required_if' => 'يرجى إدخال رمز الـ PIN الحالي للتحقق من هويتك',
            'current_pin.digits' => 'يجب أن يتكون رمز الـ PIN الحالي من 4 أرقام',
            'current_password.required_if' => 'يرجى إدخال كلمة المرور للتحقق من هويتك',
            'new_pin.required' => 'يرجى إدخال رمز الـ PIN الجديد المكون من 4 أرقام',
            'new_pin.digits' => 'يجب أن يتكون رمز الـ PIN الجديد من 4 أرقام بالضبط',
            'new_pin.confirmed' => 'تأكيد رمز الـ PIN الجديد غير متطابق',
        ]);

        // 1. Verify Identity
        if ($request->auth_method === 'current_pin') {
            if (! $user->hasPin() || ! $user->verifyPin((string) $request->current_pin)) {
                throw ValidationException::withMessages([
                    'current_pin' => ['رمز الـ PIN الحالي غير صحيح! يرجى التأكد والمحاولة مرة أخرى.'],
                ]);
            }
        } else {
            if (! Hash::check((string) $request->current_password, $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => ['كلمة المرور الحالية غير صحيحة!'],
                ]);
            }
        }

        // 2. Prevent setting the exact same PIN if already set
        if ($user->hasPin() && $user->verifyPin((string) $request->new_pin)) {
            throw ValidationException::withMessages([
                'new_pin' => ['رمز الـ PIN الجديد مطابق لرمزك القديم! يرجى اختيار رمز جديد ومختلف.'],
            ]);
        }

        // 3. Update PIN
        $user->update([
            'pin_code' => Hash::make((string) $request->new_pin),
            'pin_enabled' => true,
        ]);

        $request->session()->forget('masroofi_locked');
        $cookie = cookie('masroofi_device_user_id', $user->id, 60 * 24 * 90);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'تم تغيير وتحديث رمز الـ PIN بنجاح! 🔒✨',
            ])->withCookie($cookie);
        }

        return back()->withCookie($cookie)->with('success', 'تم تغيير وتحديث رمز الـ PIN السريع بنجاح! 🔒✨');
    }

    /**
     * Lock the application screen.
     */
    public function lock(Request $request)
    {
        $request->session()->put('masroofi_locked', true);

        return redirect()->route('pin.unlock');
    }

    /**
     * Show Quick PIN Unlock screen.
     */
    public function showUnlockForm()
    {
        $user = Auth::user();

        if (! $user || ! $user->hasPin()) {
            return redirect()->route('dashboard');
        }

        return view('auth.pin-unlock', compact('user'));
    }

    /**
     * Unlock the application using the 4-digit PIN.
     */
    public function unlock(Request $request)
    {
        $request->validate([
            'pin' => 'required|digits:4',
        ], [
            'pin.required' => 'يرجى إدخال رمز الـ PIN المكون من 4 أرقام',
            'pin.digits' => 'يجب أن يتكون رمز الـ PIN من 4 أرقام بالضبط',
        ]);

        $user = Auth::user();

        if (! $user->verifyPin($request->pin)) {
            throw ValidationException::withMessages([
                'pin' => ['رمز الـ PIN غير صحيح! يرجى المحاولة مجدداً.'],
            ]);
        }

        $request->session()->forget('masroofi_locked');

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('dashboard')->with('success', 'تم إلغاء القفل بنجاح 🔓');
    }
}
