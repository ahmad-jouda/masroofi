<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعيين كلمة مرور جديدة - {{ config('app.name', 'مصروفي Pro') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">

    <style>
        body.auth-body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: radial-gradient(circle at 50% 20%, #1e293b, #0f172a 75%);
            font-family: 'Cairo', sans-serif;
            margin: 0;
            padding: 1.5rem;
            color: #f8fafc;
        }

        .auth-card {
            width: 100%;
            max-width: 440px;
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 2.25rem 2rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), 0 0 40px rgba(16, 185, 129, 0.1);
        }

        .auth-title {
            font-size: 1.35rem;
            font-weight: 800;
            margin: 0 0 0.4rem 0;
            text-align: center;
            color: #f8fafc;
        }

        .auth-desc {
            font-size: 0.88rem;
            color: #94a3b8;
            margin: 0 0 1.5rem 0;
            text-align: center;
            line-height: 1.5;
        }

        .auth-input {
            width: 100%;
            background: rgba(15, 23, 42, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 12px;
            padding: 0.75rem 1rem;
            color: #fff;
            font-family: inherit;
            font-size: 0.95rem;
            box-sizing: border-box;
        }

        .auth-input:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
        }

        .auth-btn-primary {
            width: 100%;
            padding: 0.85rem;
            border-radius: 12px;
            border: none;
            background: linear-gradient(135deg, #10b981, #059669);
            color: #fff;
            font-weight: 800;
            font-size: 1rem;
            cursor: pointer;
            box-shadow: 0 10px 20px -5px rgba(16, 185, 129, 0.4);
            margin-top: 1rem;
            font-family: inherit;
        }

        .password-input-wrap {
            position: relative;
            display: flex;
            align-items: center;
            width: 100%;
        }

        .password-input-wrap input {
            width: 100% !important;
            padding-left: 2.75rem !important;
            box-sizing: border-box !important;
        }

        .btn-password-toggle {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            cursor: pointer;
            color: #94a3b8;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px;
            border-radius: 8px;
            transition: all 0.2s ease;
            z-index: 5;
            user-select: none;
            line-height: 1;
        }

        .btn-password-toggle:hover {
            color: #10b981;
            background: rgba(16, 185, 129, 0.12);
        }

        .btn-password-toggle:focus {
            outline: none;
            color: #10b981;
        }

        .btn-password-toggle svg {
            width: 19px;
            height: 19px;
            stroke-width: 2.1;
            display: block;
        }
    </style>
</head>
<body class="auth-body">

<div class="auth-card">
    <div style="text-align: center; margin-bottom: 1rem; font-size: 2.2rem;">
        🔐
    </div>

    <h2 class="auth-title">تعيين كلمة مرور جديدة</h2>
    <p class="auth-desc">أدخل بريدك الإلكتروني المعتمد وكلمة المرور الجديدة</p>

    @if($errors->any())
        <div class="alert-error">
            <span>⚠️</span> <span>{{ $errors->first() }}</span>
        </div>
    @endif

    <form action="{{ route('password.update') }}" method="POST">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div style="display: flex; flex-direction: column; gap: 0.4rem; margin-bottom: 1rem;">
            <label style="font-size: 0.85rem; font-weight: 700; color: #e2e8f0;">البريد الإلكتروني:</label>
            <input type="email" name="email" class="auth-input" value="{{ old('email', $email) }}" required placeholder="name@example.com">
        </div>

        <div style="display: flex; flex-direction: column; gap: 0.4rem; margin-bottom: 1rem;">
            <label style="font-size: 0.85rem; font-weight: 700; color: #e2e8f0;">كلمة المرور الجديدة:</label>
            <div class="password-input-wrap">
                <input type="password" name="password" id="resetPassword" class="auth-input" required placeholder="•••••••• (9 خانات، أحرف A-z وأرقام)">
                <button type="button" class="btn-password-toggle" onclick="togglePasswordVisibility('resetPassword', this)" title="إظهار كلمة المرور" aria-label="إظهار كلمة المرور">
                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
            </div>
            <div style="font-size: 0.73rem; color: #94a3b8; margin-top: 2px;">يجب ألا تقل عن 9 خانات وتحتوي على أرقام وأحرف كبيرة وصغيرة (A-Z, a-z, 0-9).</div>
        </div>

        <div style="display: flex; flex-direction: column; gap: 0.4rem; margin-bottom: 1rem;">
            <label style="font-size: 0.85rem; font-weight: 700; color: #e2e8f0;">تأكيد كلمة المرور:</label>
            <div class="password-input-wrap">
                <input type="password" name="password_confirmation" id="resetPasswordConfirm" class="auth-input" required placeholder="••••••••">
                <button type="button" class="btn-password-toggle" onclick="togglePasswordVisibility('resetPasswordConfirm', this)" title="إظهار كلمة المرور" aria-label="إظهار كلمة المرور">
                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
            </div>
        </div>

        <button type="submit" class="auth-btn-primary">
            💾 حفظ كلمة المرور والدخول
        </button>
    </form>
</div>

<script>
    window.togglePasswordVisibility = function(inputId, btn) {
        const input = typeof inputId === 'string' ? document.getElementById(inputId) : inputId;
        if (!input) return;
        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        if (btn) {
            const eyeOpen = `<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>`;
            const eyeOff = `<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" y1="2" x2="22" y2="22"/></svg>`;
            btn.innerHTML = isPassword ? eyeOff : eyeOpen;
            btn.title = isPassword ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور';
            btn.setAttribute('aria-label', btn.title);
        }
    };
</script>

</body>
</html>
