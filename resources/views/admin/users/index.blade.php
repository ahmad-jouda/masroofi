@extends('layouts.app')

@section('content')
<div style="max-width: 1240px; margin: 0 auto; padding-bottom: 3.5rem;">

    <!-- HEADER & ACTION -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1.25rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.65rem; margin-bottom: 0.35rem;">
                <span style="font-size: 1.9rem;">👥</span>
                <h1 style="font-size: 1.65rem; font-weight: 800; margin: 0; color: #f8fafc;">إدارة المستخدمين والمحافظ المشتركة</h1>
            </div>
            <p style="color: var(--text-secondary); margin: 0; font-size: 0.92rem; line-height: 1.5;">
                التحكم الحصري بإنشاء وتعديل الحسابات، ربط أفراد العائلة بنفس المحفظة، أو منح الشركاء والأصدقاء محافظاً مستقلة ونظيفة.
            </p>
        </div>

        <button type="button" class="btn-primary" onclick="openModal('createUserModal')" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.4rem; font-size: 0.95rem; font-weight: 800; border-radius: 12px; box-shadow: 0 4px 14px var(--primary-glow);">
            <span>➕</span>
            <span>إنشاء مستخدم جديد</span>
        </button>
    </div>

    <!-- STATS CARDS -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.15rem; margin-bottom: 2rem;">
        <div class="stat-card" style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.25); border-radius: 18px; padding: 1.35rem; position: relative; overflow: hidden;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 0.88rem; color: #34d399; font-weight: 800;">إجمالي المستخدمين</span>
                <div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(16, 185, 129, 0.15); display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">👤</div>
            </div>
            <div style="font-size: 2rem; font-weight: 900; color: #10b981; margin-top: 0.4rem; font-family: var(--font-numbers);">{{ $users->count() }}</div>
            <div style="font-size: 0.78rem; color: #9ca3af; margin-top: 0.2rem;">حسابات مسجلة ومفعلة بالنظام</div>
        </div>

        <div class="stat-card" style="background: rgba(59, 130, 246, 0.08); border: 1px solid rgba(59, 130, 246, 0.25); border-radius: 18px; padding: 1.35rem; position: relative; overflow: hidden;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 0.88rem; color: #60a5fa; font-weight: 800;">المحافظ ومساحات العمل</span>
                <div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(59, 130, 246, 0.15); display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">💼</div>
            </div>
            <div style="font-size: 2rem; font-weight: 900; color: #3b82f6; margin-top: 0.4rem; font-family: var(--font-numbers);">{{ $workspaces->count() }}</div>
            <div style="font-size: 0.78rem; color: #9ca3af; margin-top: 0.2rem;">محافظ عائلية ومحافظ مستقلة</div>
        </div>

        <div class="stat-card" style="background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.25); border-radius: 18px; padding: 1.35rem; position: relative; overflow: hidden;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 0.88rem; color: #fbbf24; font-weight: 800;">أعضاء محفظتك (العائلة)</span>
                <div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(245, 158, 11, 0.15); display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">👨‍👩‍👧‍👦</div>
            </div>
            <div style="font-size: 2rem; font-weight: 900; color: #f59e0b; margin-top: 0.4rem; font-family: var(--font-numbers);">
                {{ $users->where('workspace_id', auth()->user()->workspace_id)->count() }}
            </div>
            <div style="font-size: 0.78rem; color: #9ca3af; margin-top: 0.2rem;">يتشاركون نفس المصروف والدخل</div>
        </div>
    </div>

    <!-- USERS TABLE CARD -->
    <div class="admin-users-card">
        <!-- Toolbar: Search & Filters -->
        <div class="admin-users-toolbar">
            <div class="admin-users-search">
                <span class="search-ico">🔍</span>
                <input type="text" id="userSearchInput" oninput="filterUsersTable()" placeholder="ابحث باسم المستخدم، @username، البريد، أو رقم الجوال...">
            </div>

            <div style="display: flex; align-items: center; gap: 0.85rem; flex-wrap: wrap;">
                <div class="admin-users-filters">
                    <button type="button" class="user-filter-pill active" data-role="all" onclick="setRoleFilter('all', this)">الكل ({{ $users->count() }})</button>
                    <button type="button" class="user-filter-pill" data-role="admin" onclick="setRoleFilter('admin', this)">👑 المدراء ({{ $users->where('role', 'admin')->count() }})</button>
                    <button type="button" class="user-filter-pill" data-role="member" onclick="setRoleFilter('member', this)">👤 الأعضاء ({{ $users->where('role', 'member')->count() }})</button>
                </div>
                <span id="usersCountBadge" style="font-size: 0.8rem; font-weight: 800; color: #94a3b8; background: rgba(255, 255, 255, 0.05); padding: 0.35rem 0.75rem; border-radius: 8px; border: 1px solid var(--card-border);">
                    {{ $users->count() }} مستخدم
                </span>
            </div>
        </div>

        <!-- Table -->
        <div style="overflow-x: auto;">
            <table class="ledger-table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="text-align: right; min-width: 200px;">المستخدم والحساب</th>
                        <th style="text-align: right; min-width: 190px;">بيانات الاتصال</th>
                        <th style="text-align: right; min-width: 170px;">المحفظة المالية</th>
                        <th style="text-align: right; min-width: 130px;">الدور والصلاحية</th>
                        <th style="text-align: right; min-width: 110px;">حماية PIN</th>
                        <th style="text-align: center; min-width: 120px;">الإجراءات</th>
                    </tr>
                </thead>
                <tbody id="usersTableBody">
                    @forelse($users as $u)
                        <tr class="user-row" 
                            data-search="{{ strtolower($u->name . ' ' . $u->username . ' ' . $u->email . ' ' . ($u->phone ?? '') . ' ' . ($u->workspace->name ?? '')) }}"
                            data-role="{{ $u->role }}">
                            <!-- 1. User & Account -->
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <div style="width: 42px; height: 42px; border-radius: 50%; background: linear-gradient(135deg, #10b981, #3b82f6); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; color: #fff; font-weight: 800; flex-shrink: 0; box-shadow: 0 4px 10px rgba(0,0,0,0.25);">
                                        {{ mb_substr($u->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <div style="font-weight: 800; color: #f8fafc; font-size: 0.95rem; display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap;">
                                            <span>{{ $u->name }}</span>
                                            @if($u->id === auth()->id())
                                                <span class="user-current-badge">أنت (الحساب الحالي) ⭐</span>
                                            @endif
                                        </div>
                                        <div style="margin-top: 0.25rem;">
                                            <span class="username-pill" dir="ltr">&#64;{{ $u->username ?? '—' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- 2. Contact info -->
                            <td>
                                <div style="display: flex; flex-direction: column; gap: 0.25rem; font-size: 0.85rem;">
                                    <div style="color: #cbd5e1; display: flex; align-items: center; gap: 0.4rem;">
                                        <span style="opacity: 0.7;">✉️</span>
                                        <span>{{ $u->email }}</span>
                                    </div>
                                    <div style="color: #94a3b8; display: flex; align-items: center; gap: 0.4rem;">
                                        <span style="opacity: 0.7;">📱</span>
                                        <span dir="ltr">{{ $u->phone ?: 'غير مسجل' }}</span>
                                    </div>
                                </div>
                            </td>

                            <!-- 3. Workspace -->
                            <td>
                                <div style="display: flex; flex-direction: column; gap: 0.35rem; align-items: flex-start;">
                                    <span style="background: rgba(59, 130, 246, 0.12); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.28); padding: 0.25rem 0.65rem; border-radius: 8px; font-size: 0.82rem; font-weight: 700;">
                                        💼 {{ $u->workspace->name ?? 'محفظة مخصصة' }}
                                    </span>
                                    @if($u->workspace_id === auth()->user()->workspace_id)
                                        <span style="font-size: 0.72rem; color: #f59e0b; font-weight: 700;">👨‍👩‍👧‍👦 محفظة مشتركة (معك)</span>
                                    @else
                                        <span style="font-size: 0.72rem; color: #94a3b8;">🔒 محفظة مستقلة ومنفصلة</span>
                                    @endif
                                </div>
                            </td>

                            <!-- 4. Role -->
                            <td>
                                @if($u->role === 'admin')
                                    <span style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(16, 185, 129, 0.08)); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.4); padding: 0.3rem 0.7rem; border-radius: 8px; font-size: 0.82rem; font-weight: 800; display: inline-flex; align-items: center; gap: 0.3rem;">
                                        👑 مدير (أدمن)
                                    </span>
                                @else
                                    <span style="background: rgba(255, 255, 255, 0.06); color: #cbd5e1; border: 1px solid rgba(255, 255, 255, 0.1); padding: 0.3rem 0.7rem; border-radius: 8px; font-size: 0.82rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.3rem;">
                                        👤 عضو مسجل
                                    </span>
                                @endif
                            </td>

                            <!-- 5. PIN -->
                            <td>
                                @if($u->hasPin())
                                    <span style="background: rgba(16, 185, 129, 0.12); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.25); padding: 0.25rem 0.6rem; border-radius: 8px; font-weight: 800; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 0.3rem;" title="رمز الدخول السريع مفعل">
                                        🔒 مفعل
                                    </span>
                                @else
                                    <span style="background: rgba(148, 163, 184, 0.08); color: #94a3b8; border: 1px solid rgba(148, 163, 184, 0.2); padding: 0.25rem 0.6rem; border-radius: 8px; font-size: 0.8rem;">
                                        ⏳ لم يُحدد بعد
                                    </span>
                                @endif
                            </td>

                            <!-- 6. Actions -->
                            <td style="text-align: center;">
                                <div class="user-action-btns">
                                    <!-- Edit User Button -->
                                    <button type="button" class="btn-user-action edit" onclick="openEditUserModal({{ json_encode($u) }})" title="تعديل بيانات المستخدم">
                                        ✏️
                                    </button>

                                    <!-- Reset PIN Button (If set) -->
                                    @if($u->hasPin())
                                        <form id="resetPinForm_{{ $u->id }}" action="{{ route('admin.users.resetPin', $u) }}" method="POST" style="display: none;">
                                            @csrf
                                        </form>
                                        <button type="button" class="btn-user-action pin" onclick="confirmResetPin('resetPinForm_{{ $u->id }}', '{{ $u->name }}')" title="إعادة تعيين رمز PIN في حال نسيانه">
                                            🔑
                                        </button>
                                    @endif

                                    <!-- Delete User Button (Other users only) -->
                                    @if($u->id !== auth()->id())
                                        <form action="{{ route('admin.users.destroy', $u) }}" method="POST" style="display: inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" class="btn-user-action delete" onclick="confirmDeleteForm(event, 'هل أنت متأكد من حذف حساب المستخدم «{{ $u->name }}» نهائياً؟ سيتم إلغاء وصوله للنظام.', 'تأكيد حذف المستخدم')" title="حذف المستخدم نهائياً">
                                                🗑️
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 3rem 1rem; color: #94a3b8;">
                                <div style="font-size: 2rem; margin-bottom: 0.5rem;">👥</div>
                                <div style="font-weight: 700;">لا يوجد أي مستخدمين مسجلين بعد</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ============================================== -->
<!-- 1. CREATE USER MODAL                           -->
<!-- ============================================== -->
<div class="modal-backdrop hidden" id="createUserModal">
    <div class="modal-card modal-card-lg">
        <div class="modal-header">
            <div>
                <h3 style="margin: 0; font-size: 1.25rem; font-weight: 800; display: flex; align-items: center; gap: 0.5rem;">
                    <span>➕</span>
                    <span>إنشاء حساب مستخدم جديد</span>
                </h3>
                <p style="font-size: 0.82rem; color: var(--text-secondary); margin: 0.25rem 0 0 0;">
                    حدد بيانات الدخول للمستخدم واختر ما إذا كان سيتشارك معك نفس المحفظة أو محفظة مستقلة.
                </p>
            </div>
            <button type="button" class="modal-close-btn" onclick="closeModal('createUserModal')">&times;</button>
        </div>

        <form action="{{ route('admin.users.store') }}" method="POST" class="modal-form" style="padding: 1.5rem;">
            @csrf

            <!-- 1. Basic Account Info -->
            <div class="form-row">
                <div class="form-group">
                    <label>الاسم الكامل / المستعار: *</label>
                    <input type="text" name="name" class="styled-input" required placeholder="مثال: سارة، أبو محمد، أخي خالد...">
                </div>
                <div class="form-group">
                    <label>اسم الدخول (Username فريد): *</label>
                    <input type="text" name="username" class="styled-input" required placeholder="مثال: sara, khaled...">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>البريد الإلكتروني: *</label>
                    <input type="email" name="email" class="styled-input" required placeholder="sara@example.com">
                </div>
                <div class="form-group">
                    <label>رقم الجوال (للدخول به):</label>
                    <input type="text" name="phone" class="styled-input" placeholder="0599123456">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>كلمة المرور الأولية: *</label>
                    <input type="password" name="password" class="styled-input" required placeholder="•••••••• (9 خانات، أحرف A-z وأرقام)">
                    <div style="font-size: 0.73rem; color: #94a3b8; margin-top: 4px;">يجب ألا تقل عن 9 خانات وتحتوي على أرقام وأحرف كبيرة وصغيرة (A-Z, a-z, 0-9).</div>
                </div>
                <div class="form-group">
                    <label>تأكيد كلمة المرور: *</label>
                    <input type="password" name="password_confirmation" class="styled-input" required placeholder="••••••••">
                </div>
            </div>

            <!-- 2. Role -->
            <div class="form-group">
                <label>نوع الصلاحية / الدور:</label>
                <select name="role" class="styled-select">
                    <option value="member" selected>👤 عضو (يسجل المصاريف والعمليات فقط)</option>
                    <option value="admin">👑 مدير نظام (صلاحية كاملة لإنشاء مستخدمين وإدارة النظام)</option>
                </select>
            </div>

            <!-- 3. Workspace Architecture Selection -->
            <div style="background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(255,255,255,0.12); border-radius: 14px; padding: 1.15rem; margin-top: 0.5rem;">
                <label style="font-weight: 800; font-size: 0.95rem; color: #38bdf8; display: block; margin-bottom: 0.75rem;">
                    🏢 تخصيص المحفظة المالية (Workspace):
                </label>

                <div style="display: flex; flex-direction: column; gap: 0.85rem;">
                    <!-- Option 1: Shared Family Workspace -->
                    <label style="display: flex; align-items: flex-start; gap: 0.65rem; cursor: pointer; padding: 0.65rem; border-radius: 10px; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08);">
                        <input type="radio" name="workspace_mode" value="existing" checked onchange="toggleWorkspaceMode('existing')" style="accent-color: #10b981; margin-top: 0.25rem;">
                        <div>
                            <div style="font-weight: 800; color: #f8fafc; font-size: 0.9rem;">
                                👨‍👩‍👧‍👦 مشاركة نفس المحفظة الحالية (محفظة العائلة / الزوجين)
                            </div>
                            <div style="font-size: 0.78rem; color: #94a3b8; line-height: 1.5; margin-top: 0.2rem;">
                                مثالي للزوجة أو الشريك: يتشاركان في نفس المصروفات، الأظرف، الرواتب، والديون. وكل حركة تظهر باسم من أضافها.
                            </div>
                        </div>
                    </label>

                    <!-- Option 2: Clean Independent Workspace -->
                    <label style="display: flex; align-items: flex-start; gap: 0.65rem; cursor: pointer; padding: 0.65rem; border-radius: 10px; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08);">
                        <input type="radio" name="workspace_mode" value="new" onchange="toggleWorkspaceMode('new')" style="accent-color: #10b981; margin-top: 0.25rem;">
                        <div>
                            <div style="font-weight: 800; color: #f8fafc; font-size: 0.9rem;">
                                🆕 إنشاء محفظة مستقلة وجديدة كلياً (تطبيق مصفّر من الصفر)
                            </div>
                            <div style="font-size: 0.78rem; color: #94a3b8; line-height: 1.5; margin-top: 0.2rem;">
                                مثالي للأخ أو الصديق: يحصل على تطبيق فارغ تماماً يبدأ فيه مصاريفه وحساباته الخاصة بمعزل كامل عن حساباتك!
                            </div>
                        </div>
                    </label>
                </div>

                <!-- Existing Workspace Selector -->
                <div id="existingWorkspaceGroup" style="margin-top: 1rem;">
                    <label style="font-size: 0.82rem; color: #cbd5e1; margin-bottom: 0.35rem; display: block;">اختر المحفظة القائمة للربط:</label>
                    <select name="workspace_id" class="styled-select">
                        @foreach($workspaces as $ws)
                            <option value="{{ $ws->id }}" {{ $ws->id === auth()->user()->workspace_id ? 'selected' : '' }}>
                                {{ $ws->name }} (أعضاؤها: {{ $ws->users_count }} مستخدمين)
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- New Workspace Name Input -->
                <div id="newWorkspaceGroup" style="display: none; margin-top: 1rem;">
                    <label style="font-size: 0.82rem; color: #cbd5e1; margin-bottom: 0.35rem; display: block;">اسم المحفظة الجديدة المستقلة:</label>
                    <input type="text" name="new_workspace_name" class="styled-input" placeholder="مثال: محفظة خالد، حساب أحمد الشخصي...">
                    <small style="color: #94a3b8; font-size: 0.75rem;">سيتم توليد تصنيفات وأظرف أولية نظيفة له تلقائياً ليكون جاهزاً للاستخدام فوراً.</small>
                </div>
            </div>

            <div class="modal-actions" style="margin-top: 1.5rem;">
                <button type="button" class="btn-subtle" onclick="closeModal('createUserModal')">إلغاء</button>
                <button type="submit" class="btn-primary" style="background: linear-gradient(135deg, #10b981, #059669);">
                    🚀 إنشاء الحساب وتثبيت الصلاحيات
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================== -->
<!-- 2. EDIT USER MODAL                             -->
<!-- ============================================== -->
<div class="modal-backdrop hidden" id="editUserModal">
    <div class="modal-card modal-card-lg">
        <div class="modal-header">
            <div>
                <h3 style="margin: 0; font-size: 1.25rem; font-weight: 800; display: flex; align-items: center; gap: 0.5rem;">
                    <span>✏️</span>
                    <span>تعديل بيانات المستخدم والصلاحيات</span>
                </h3>
                <p style="font-size: 0.82rem; color: var(--text-secondary); margin: 0.25rem 0 0 0;">
                    تحديث بيانات الحساب، تغيير المحفظة المعينة، أو تعديل كلمة المرور.
                </p>
            </div>
            <button type="button" class="modal-close-btn" onclick="closeModal('editUserModal')">&times;</button>
        </div>

        <form id="editUserForm" action="" method="POST" class="modal-form" style="padding: 1.5rem;">
            @csrf
            @method('PUT')

            <!-- 1. Basic Account Info -->
            <div class="form-row">
                <div class="form-group">
                    <label>الاسم الكامل / المستعار: *</label>
                    <input type="text" name="name" id="editUserName" class="styled-input" required>
                </div>
                <div class="form-group">
                    <label>اسم الدخول (Username فريد): *</label>
                    <input type="text" name="username" id="editUserUsername" class="styled-input" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>البريد الإلكتروني: *</label>
                    <input type="email" name="email" id="editUserEmail" class="styled-input" required>
                </div>
                <div class="form-group">
                    <label>رقم الجوال:</label>
                    <input type="text" name="phone" id="editUserPhone" class="styled-input">
                </div>
            </div>

            <!-- 2. Role & Workspace -->
            <div class="form-row">
                <div class="form-group">
                    <label>نوع الصلاحية / الدور:</label>
                    <select name="role" id="editUserRole" class="styled-select">
                        <option value="member">👤 عضو (يسجل المصاريف والعمليات فقط)</option>
                        <option value="admin">👑 مدير نظام (صلاحية كاملة لإنشاء مستخدمين وإدارة النظام)</option>
                    </select>
                    <small id="editUserRoleNote" style="color: #f59e0b; font-size: 0.72rem; display: none; margin-top: 0.2rem;">
                        ⚠️ لا يمكنك سحب صلاحية المدير عن حسابك الحالي.
                    </small>
                </div>

                <div class="form-group">
                    <label>المحفظة المعينة (Workspace):</label>
                    <select name="workspace_id" id="editUserWorkspaceId" class="styled-select">
                        @foreach($workspaces as $ws)
                            <option value="{{ $ws->id }}">
                                {{ $ws->name }} (أعضاؤها: {{ $ws->users_count }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- 3. Password Reset (Optional) -->
            <div style="background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(255,255,255,0.12); border-radius: 14px; padding: 1rem; margin-top: 0.5rem;">
                <label style="font-weight: 800; font-size: 0.88rem; color: #cbd5e1; display: block; margin-bottom: 0.5rem;">
                    🔒 تغيير كلمة المرور (اختياري - اتركها فارغة إذا لم ترغب بتغييرها):
                </label>
                <div class="form-row">
                    <div class="form-group">
                        <input type="password" name="password" class="styled-input" placeholder="كلمة المرور الجديدة (9 خانات، أحرف A-z وأرقام)">
                        <div style="font-size: 0.73rem; color: #94a3b8; margin-top: 4px;">يجب ألا تقل عن 9 خانات وتحتوي على أرقام وأحرف كبيرة وصغيرة (A-Z, a-z, 0-9).</div>
                    </div>
                    <div class="form-group">
                        <input type="password" name="password_confirmation" class="styled-input" placeholder="تأكيد كلمة المرور الجديدة">
                    </div>
                </div>
            </div>

            <div class="modal-actions" style="margin-top: 1.5rem;">
                <button type="button" class="btn-subtle" onclick="closeModal('editUserModal')">إلغاء</button>
                <button type="submit" class="btn-primary" style="background: linear-gradient(135deg, #3b82f6, #2563eb);">
                    💾 حفظ التعديلات
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const currentAuthId = {{ auth()->id() }};

    function toggleWorkspaceMode(mode) {
        const existGrp = document.getElementById('existingWorkspaceGroup');
        const newGrp = document.getElementById('newWorkspaceGroup');
        if (mode === 'new') {
            if (existGrp) existGrp.style.display = 'none';
            if (newGrp) newGrp.style.display = 'block';
        } else {
            if (existGrp) existGrp.style.display = 'block';
            if (newGrp) newGrp.style.display = 'none';
        }
    }

    let activeRoleFilter = 'all';

    function setRoleFilter(role, el) {
        activeRoleFilter = role;
        document.querySelectorAll('.user-filter-pill').forEach(btn => btn.classList.remove('active'));
        if (el) el.classList.add('active');
        filterUsersTable();
    }

    function filterUsersTable() {
        const query = document.getElementById('userSearchInput').value.toLowerCase().trim();
        const rows = document.querySelectorAll('.user-row');
        let count = 0;

        rows.forEach(row => {
            const search = row.dataset.search || '';
            const role = row.dataset.role || '';
            const matchesQuery = !query || search.includes(query);
            const matchesRole = activeRoleFilter === 'all' || role === activeRoleFilter;

            if (matchesQuery && matchesRole) {
                row.style.display = '';
                count++;
            } else {
                row.style.display = 'none';
            }
        });

        const badge = document.getElementById('usersCountBadge');
        if (badge) badge.textContent = `${count} مستخدم`;
    }

    function openEditUserModal(user) {
        const form = document.getElementById('editUserForm');
        form.action = `/admin/users/${user.id}`;

        document.getElementById('editUserName').value = user.name || '';
        document.getElementById('editUserUsername').value = user.username || '';
        document.getElementById('editUserEmail').value = user.email || '';
        document.getElementById('editUserPhone').value = user.phone || '';
        document.getElementById('editUserWorkspaceId').value = user.workspace_id || '';

        const roleSelect = document.getElementById('editUserRole');
        const roleNote = document.getElementById('editUserRoleNote');
        roleSelect.value = user.role || 'member';

        if (user.id === currentAuthId) {
            roleSelect.style.pointerEvents = 'none';
            roleSelect.style.opacity = '0.7';
            if (roleNote) roleNote.style.display = 'block';
        } else {
            roleSelect.style.pointerEvents = 'auto';
            roleSelect.style.opacity = '1';
            if (roleNote) roleNote.style.display = 'none';
        }

        openModal('editUserModal');
    }

    async function confirmResetPin(formId, userName) {
        if (window.showCustomConfirm) {
            const confirmed = await window.showCustomConfirm({
                title: 'إعادة تعيين رمز PIN',
                message: `هل أنت متأكد من إعادة تعيين رمز الدخول السريع للمستخدم «${userName}»؟`,
                details: 'سيتم مسح رمز الـ PIN الحالي لهذا المستخدم، وسيتمكن من تسجيل الدخول بكلمة المرور الخاصة به وتعيين رمز PIN جديد.',
                confirmText: 'نعم، أعد التعيين',
                cancelText: 'تراجع',
                icon: '🔑',
                isDanger: false
            });
            if (confirmed) {
                document.getElementById(formId).submit();
            }
        } else {
            if (confirm(`هل أنت متأكد من إعادة تعيين رمز PIN للمستخدم «${userName}»؟`)) {
                document.getElementById(formId).submit();
            }
        }
    }
</script>
@endsection
