<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class WorkspaceMemberController extends Controller
{
    /**
     * Add a family member/partner to the authenticated user's current workspace.
     */
    public function store(Request $request)
    {
        $workspace = auth()->user()->workspace;

        if (! $workspace) {
            return back()->with('error', 'عذراً، لا توجد محفظة مرتبطة بحسابك الحالي.');
        }

        if ($request->filled('phone')) {
            $request->merge([
                'phone' => preg_replace('/[^\d]/', '', (string) $request->input('phone')),
            ]);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'username' => 'required|string|alpha_dash|max:50|unique:users,username',
            'email' => 'required|email|max:150|unique:users,email',
            'phone' => ['nullable', 'string', 'regex:/^(056|059)[0-9]{7}$/', 'unique:users,phone'],
            'password' => ['required', 'string', 'confirmed', Password::min(9)->mixedCase()->numbers()],
        ], [
            'name.required' => 'اسم الشريك / الفرد مطلوب',
            'username.required' => 'اسم الدخول (Username) مطلوب',
            'username.unique' => 'اسم الدخول هذا مستخدم بالفعل، يرجى اختيار اسم آخر',
            'email.required' => 'البريد الإلكتروني مطلوب',
            'email.unique' => 'البريد الإلكتروني هذا مستخدم بالفعل',
            'phone.regex' => 'رقم الجوال يجب أن يبدأ بـ 056 أو 059 ومكون من 10 أرقام فقط (مثال: 0599123456)',
            'phone.unique' => 'رقم الجوال هذا مسجل لمستخدم آخر',
            'password.required' => 'كلمة المرور مطلوبة',
            'password.min' => 'يجب ألا تقل كلمة المرور عن 9 خانات',
            'password.mixed' => 'يجب أن تحتوي كلمة المرور على أحرف كبيرة (A-Z) وأحرف صغيرة (a-z)',
            'password.numbers' => 'يجب أن تحتوي كلمة المرور على أرقام (0-9)',
            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق',
        ]);

        $cleanPhone = $validated['phone'] ? preg_replace('/[^\d]/', '', $validated['phone']) : null;

        $member = User::create([
            'name' => $validated['name'],
            'username' => strtolower($validated['username']),
            'email' => strtolower($validated['email']),
            'phone' => $cleanPhone,
            'password' => Hash::make($validated['password']),
            'role' => 'member',
            'workspace_id' => $workspace->id,
        ]);

        return back()->with('success', "تمت إضافة «{$member->name}» بنجاح إلى محفظتك العائلية («{$workspace->name}»)! يمكنها الآن تسجيل الدخول والتشارك في متابعة وتسجيل المصاريف معك. 🎉");
    }

    /**
     * Remove a member from the user's workspace.
     */
    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'لا يمكنك إزالة حسابك الحالي من المحفظة!');
        }

        if ($user->workspace_id !== auth()->user()->workspace_id) {
            abort(403, 'غير مصرح لك بإدارة هذا الحساب.');
        }

        $memberName = $user->name;
        $user->delete();

        return back()->with('success', "تمت إزالة «{$memberName}» من المحفظة العائلية بنجاح.");
    }

    /**
     * Reset a member's PIN code.
     */
    public function resetPin(User $user)
    {
        if ($user->workspace_id !== auth()->user()->workspace_id) {
            abort(403, 'غير مصرح لك بإدارة هذا الحساب.');
        }

        $user->pin_code = null;
        $user->pin_enabled = false;
        $user->save();

        return back()->with('success', "تمت إعادة تعيين رمز PIN للشريك «{$user->name}» بنجاح. يمكنه الآن تسجيل الدخول بكلمة المرور وتعيين رمز جديد. 🔑");
    }
}
