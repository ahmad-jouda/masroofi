<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * Show login form.
     */
    public function showLoginForm(Request $request)
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        // Check if there is a remembered user with PIN enabled for quick unlock on this device
        $rememberedUserId = $request->cookie('masroofi_device_user_id');
        $rememberedUser = null;
        if ($rememberedUserId) {
            $rememberedUser = User::where('id', $rememberedUserId)->where('pin_enabled', true)->first();
        }

        return view('auth.login', compact('rememberedUser'));
    }

    /**
     * Handle standard authentication (Email / Phone / Username + Password).
     */
    public function login(Request $request)
    {
        $request->validate([
            'login_identifier' => 'required|string',
            'password' => 'required|string',
        ], [
            'login_identifier.required' => 'يرجى إدخال البريد الإلكتروني أو رقم الجوال أو اسم المستخدم',
            'password.required' => 'يرجى إدخال كلمة المرور',
        ]);

        $this->ensureIsNotRateLimited($request);

        $identifier = trim($request->input('login_identifier'));
        $password = $request->input('password');
        $remember = $request->boolean('remember');

        // Determine field type: Email, Phone, or Username
        $field = 'username';
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $field = 'email';
        } elseif (preg_match('/^[0-9+\-\s()]{7,20}$/', $identifier)) {
            // Clean phone string (remove spaces, hyphens)
            $cleanPhone = preg_replace('/[^\d+]/', '', $identifier);
            // Search either exact or cleaned
            $userByPhone = User::where('phone', $identifier)->orWhere('phone', $cleanPhone)->first();
            if ($userByPhone) {
                $field = 'id';
                $identifier = $userByPhone->id;
            } else {
                $field = 'phone';
            }
        }

        if (! Auth::attempt([$field => $identifier, 'password' => $password], $remember)) {
            RateLimiter::hit($this->throttleKey($request));

            throw ValidationException::withMessages([
                'login_identifier' => ['بيانات الدخول غير صحيحة. يرجى التأكد من البريد أو الجوال أو اسم المستخدم وكلمة المرور.'],
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));
        $request->session()->regenerate();

        $user = Auth::user();

        // Set device cookie for easy PIN unlock on future visits
        $cookie = cookie('masroofi_device_user_id', $user->id, 60 * 24 * 90); // 90 days

        // If user has not set a 4-digit PIN yet, redirect to PIN setup
        if (! $user->hasPin()) {
            return redirect()->route('pin.setup')->withCookie($cookie);
        }

        return redirect()->intended(route('dashboard'))->withCookie($cookie)->with('success', "مرحباً بك مجدداً يا {$user->name}! 👋✨");
    }

    /**
     * Handle Quick 4-Digit PIN Authentication from the login screen.
     */
    public function loginWithPin(Request $request)
    {
        $request->validate([
            'pin' => 'required|digits:4',
            'user_id' => 'required|exists:users,id',
        ], [
            'pin.required' => 'يرجى إدخال رمز الـ PIN المكون من 4 أرقام',
            'pin.digits' => 'يجب أن يتكون رمز الـ PIN من 4 أرقام دقيقة',
        ]);

        $this->ensureIsNotRateLimited($request);

        $user = User::find($request->user_id);

        if (! $user || ! $user->hasPin() || ! $user->verifyPin($request->pin)) {
            RateLimiter::hit($this->throttleKey($request));

            throw ValidationException::withMessages([
                'pin' => ['رمز الـ PIN غير صحيح. يرجى المحاولة مرة أخرى.'],
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));
        Auth::login($user, true);
        $request->session()->regenerate();
        $request->session()->forget('masroofi_locked');

        $cookie = cookie('masroofi_device_user_id', $user->id, 60 * 24 * 90);

        return redirect()->intended(route('dashboard'))
            ->withCookie($cookie)
            ->with('success', "تم فتح التطبيق بنجاح! مرحباً {$user->name} 🔓");
    }

    /**
     * Log user out.
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'تم تسجيل الخروج بأمان. إلى اللقاء! 👋');
    }

    /**
     * Forget device (switch account / remove remembered PIN user).
     */
    public function forgetDevice(Request $request)
    {
        $forgetCookie = cookie()->forget('masroofi_device_user_id');

        return redirect()->route('login')->withCookie($forgetCookie);
    }

    /**
     * Ensure the login request is not rate limited.
     */
    protected function ensureIsNotRateLimited(Request $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        throw ValidationException::withMessages([
            'login_identifier' => ["تم تجاوز عدد محاولات الدخول المسموحة. يرجى الانتظار {$seconds} ثانية."],
        ]);
    }

    /**
     * Get rate limiting throttle key.
     */
    protected function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower($request->input('login_identifier', $request->input('user_id', ''))).'|'.$request->ip());
    }
}
