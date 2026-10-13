<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ResetPasswordController extends Controller
{
    /**
     * Show reset password form with the token.
     */
    public function showResetForm(Request $request, ?string $token = null)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->email,
        ]);
    }

    /**
     * Reset user password using verified email and token.
     */
    public function reset(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email|exists:users,email',
            'password' => ['required', 'string', 'confirmed', Password::min(9)->mixedCase()->numbers()],
        ], [
            'email.required' => 'يرجى إدخال البريد الإلكتروني',
            'password.required' => 'يرجى إدخال كلمة المرور الجديدة',
            'password.min' => 'يجب ألا تقل كلمة المرور عن 9 خانات',
            'password.mixed' => 'يجب أن تحتوي كلمة المرور على أحرف كبيرة (A-Z) وأحرف صغيرة (a-z)',
            'password.numbers' => 'يجب أن تحتوي كلمة المرور على أرقام (0-9)',
            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق',
        ]);

        $record = DB::table('password_reset_tokens')->where('email', $request->email)->first();

        if (! $record || ! Hash::check($request->token, $record->token)) {
            throw ValidationException::withMessages([
                'email' => ['رابط استعادة كلمة المرور غير صالح أو منتهي الصلاحية.'],
            ]);
        }

        // Check if token is older than 60 minutes
        if (now()->subMinutes(60)->gt($record->created_at)) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            throw ValidationException::withMessages([
                'email' => ['انتهت صلاحية هذا الرابط. يرجى طلب رابط جديد.'],
            ]);
        }

        $user = User::where('email', $request->email)->first();
        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // Delete used token
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return redirect()->route('login')->with('success', 'تم تغيير كلمة المرور بنجاح! يمكنك الآن تسجيل الدخول بها 🔐✨');
    }
}
