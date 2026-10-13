<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Envelope;
use App\Models\User;
use App\Models\UserSetting;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    /**
     * Display a listing of users and workspaces.
     */
    public function index()
    {
        $users = User::with('workspace')->latest()->get();
        $workspaces = Workspace::withCount(['users', 'transactions'])->get();
        $settings = UserSetting::first();

        return view('admin.users.index', compact('users', 'workspaces', 'settings'));
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request)
    {
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
            'role' => 'required|in:admin,member',
            'workspace_mode' => 'required|in:existing,new',
            'workspace_id' => 'nullable|exists:workspaces,id',
            'new_workspace_name' => 'nullable|string|max:150',
        ], [
            'name.required' => 'اسم المستخدم مطلوب',
            'username.required' => 'اسم الدخول (Username) مطلوب',
            'username.unique' => 'اسم الدخول هذا مستخدم بالفعل',
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

        // Clean phone number
        $cleanPhone = $validated['phone'] ? preg_replace('/[^\d+]/', '', $validated['phone']) : null;

        // Determine workspace
        if ($validated['workspace_mode'] === 'new') {
            $wsName = $validated['new_workspace_name'] ?: "محفظة {$validated['name']}";
            $workspace = Workspace::create([
                'name' => $wsName,
                'currency' => 'ILS',
                'created_by' => auth()->id(),
            ]);

            // Initialize clean settings for the new independent workspace
            UserSetting::create([
                'workspace_id' => $workspace->id,
                'salary' => 0.00,
                'salary_day' => 1,
                'currency' => 'ILS',
                'theme' => 'dark',
                'sound_enabled' => true,
                'saved_from_impulse' => 0.00,
            ]);

            // Starter clean envelopes
            $starterEnvelopes = [
                ['name' => 'احتياجات أساسية وفواتير', 'pillar' => 'needs', 'icon' => '🏠', 'color' => '#3b82f6', 'budget' => 0],
                ['name' => 'بقالة وتموين', 'pillar' => 'needs', 'icon' => '🛒', 'color' => '#10b981', 'budget' => 0],
                ['name' => 'كماليات ومصاريف شخصية', 'pillar' => 'wants', 'icon' => '☕', 'color' => '#f59e0b', 'budget' => 0],
            ];
            foreach ($starterEnvelopes as $env) {
                Envelope::create(array_merge($env, ['workspace_id' => $workspace->id]));
            }

            $workspaceId = $workspace->id;
            $modeText = "محفظة جديدة ومستقلة («{$workspace->name}»)";
        } else {
            // Join existing workspace (e.g. Family shared account)
            $workspaceId = $validated['workspace_id'] ?: auth()->user()->workspace_id;
            $ws = Workspace::find($workspaceId);
            $modeText = "المحفظة المشتركة («{$ws->name}»)";
        }

        User::create([
            'name' => $validated['name'],
            'username' => strtolower($validated['username']),
            'email' => strtolower($validated['email']),
            'phone' => $cleanPhone,
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'workspace_id' => $workspaceId,
        ]);

        return redirect()->route('admin.users.index')
            ->with('success', "تم إنشاء حساب المستخدم «{$validated['name']}» وربطه مع {$modeText} بنجاح! 🎉");
    }

    /**
     * Update an existing user.
     */
    public function update(Request $request, User $user)
    {
        if ($user->id === auth()->id()) {
            $request->merge(['role' => 'admin']);
        }

        if ($request->filled('phone')) {
            $request->merge([
                'phone' => preg_replace('/[^\d]/', '', (string) $request->input('phone')),
            ]);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'username' => 'required|string|alpha_dash|max:50|unique:users,username,'.$user->id,
            'email' => 'required|email|max:150|unique:users,email,'.$user->id,
            'phone' => ['nullable', 'string', 'regex:/^(056|059)[0-9]{7}$/', 'unique:users,phone,'.$user->id],
            'role' => 'required|in:admin,member',
            'workspace_id' => 'required|exists:workspaces,id',
            'password' => ['nullable', 'string', 'confirmed', Password::min(9)->mixedCase()->numbers()],
        ], [
            'name.required' => 'اسم المستخدم مطلوب',
            'username.required' => 'اسم الدخول (Username) مطلوب',
            'username.unique' => 'اسم الدخول هذا مستخدم بالفعل',
            'email.required' => 'البريد الإلكتروني مطلوب',
            'email.unique' => 'البريد الإلكتروني هذا مستخدم بالفعل',
            'phone.regex' => 'رقم الجوال يجب أن يبدأ بـ 056 أو 059 ومكون من 10 أرقام فقط (مثال: 0599123456)',
            'phone.unique' => 'رقم الجوال هذا مسجل لمستخدم آخر',
            'password.min' => 'يجب ألا تقل كلمة المرور عن 9 خانات',
            'password.mixed' => 'يجب أن تحتوي كلمة المرور على أحرف كبيرة (A-Z) وأحرف صغيرة (a-z)',
            'password.numbers' => 'يجب أن تحتوي كلمة المرور على أرقام (0-9)',
            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق',
        ]);

        if ($user->id === auth()->id() && $validated['role'] !== 'admin') {
            return back()->with('error', 'لا يمكنك إزالة صلاحية المدير عن حسابك الحالي!');
        }

        $cleanPhone = $validated['phone'] ? preg_replace('/[^\d+]/', '', $validated['phone']) : null;

        $user->name = $validated['name'];
        $user->username = strtolower($validated['username']);
        $user->email = strtolower($validated['email']);
        $user->phone = $cleanPhone;
        $user->role = $validated['role'];
        $user->workspace_id = $validated['workspace_id'];

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return redirect()->route('admin.users.index')
            ->with('success', "تم تحديث بيانات المستخدم «{$user->name}» بنجاح! ✅");
    }

    /**
     * Reset a user's PIN code.
     */
    public function resetPin(User $user)
    {
        $user->pin_code = null;
        $user->pin_enabled = false;
        $user->save();

        return redirect()->route('admin.users.index')
            ->with('success', "تمت إعادة تعيين رمز PIN للمستخدم «{$user->name}» بنجاح. يمكنه الآن الدخول بكلمة المرور وتعيين رمز جديد. 🔑");
    }

    /**
     * Delete user.
     */
    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'لا يمكنك حذف حسابك الحالي!');
        }

        $userName = $user->name;
        $user->delete();

        return back()->with('success', "تم حذف المستخدم «{$userName}» بنجاح.");
    }
}
