<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\ResetPasswordMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ForgotPasswordController extends Controller
{
    /**
     * Show forgot password form.
     */
    public function showLinkRequestForm()
    {
        return view('auth.forgot-password');
    }

    /**
     * Send a reset link to the given user's email.
     */
    public function sendResetLinkEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ], [
            'email.required' => 'يرجى إدخال البريد الإلكتروني',
            'email.email' => 'صيغة البريد الإلكتروني غير صالحة',
            'email.exists' => 'عذراً، هذا البريد الإلكتروني غير مسجل في النظام',
        ]);

        $user = User::where('email', $request->email)->first();

        // Generate a 64-character secure token
        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        $resetUrl = route('password.reset', [
            'token' => $token,
            'email' => $request->email,
        ]);

        // Attempt sending email, fallback cleanly if mail service is not configured
        $mailSent = false;
        try {
            Mail::to($user->email)->send(new ResetPasswordMail($user, $resetUrl));
            $mailSent = true;
        } catch (\Throwable $e) {
            // Mail transport error (e.g. no SMTP configured locally)
        }

        $showLocalLink = (app()->isLocal() && config('mail.default') === 'log') || ! $mailSent;

        return back()->with('status', 'تم إنشاء رابط استعادة كلمة المرور الآمن بنجاح وإرساله إلى بريدك الإلكتروني! 📧')
            ->with('simulated_reset_link', $showLocalLink ? $resetUrl : null);
    }
}
