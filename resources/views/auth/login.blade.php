<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - {{ config('app.name', 'مصروفي Pro') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
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
            animation: fadeInScale 0.35s ease-out;
        }

        @keyframes fadeInScale {
            from { opacity: 0; transform: scale(0.96); }
            to { opacity: 1; transform: scale(1); }
        }

        .auth-logo {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .auth-logo-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1.25rem;
            background: rgba(16, 185, 129, 0.12);
            border: 1px solid rgba(16, 185, 129, 0.25);
            border-radius: 50px;
            font-size: 1.2rem;
            font-weight: 800;
            color: #10b981;
            box-shadow: 0 0 20px rgba(16, 185, 129, 0.15);
        }

        .auth-title {
            font-size: 1.4rem;
            font-weight: 800;
            margin: 0 0 0.4rem 0;
            text-align: center;
            color: #f8fafc;
        }

        .auth-desc {
            font-size: 0.88rem;
            color: #94a3b8;
            margin: 0 0 1.75rem 0;
            text-align: center;
            line-height: 1.5;
        }

        .auth-form {
            display: flex;
            flex-direction: column;
            gap: 1.15rem;
        }

        .auth-input-group {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }

        .auth-label {
            font-size: 0.85rem;
            font-weight: 700;
            color: #e2e8f0;
            display: flex;
            align-items: center;
            gap: 0.4rem;
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
            transition: all 0.2s ease;
            box-sizing: border-box;
        }

        .auth-input:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
            background: rgba(15, 23, 42, 0.9);
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
            transition: all 0.2s;
            margin-top: 0.5rem;
            font-family: inherit;
        }

        .auth-btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 24px -5px rgba(16, 185, 129, 0.5);
            background: linear-gradient(135deg, #34d399, #10b981);
        }

        .auth-btn-primary:active {
            transform: translateY(1px);
        }

        .auth-footer-links {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85rem;
            margin-top: 0.5rem;
        }

        .auth-link {
            color: #38bdf8;
            text-decoration: none;
            font-weight: 700;
            transition: color 0.15s;
        }

        .auth-link:hover {
            color: #7dd3fc;
            text-decoration: underline;
        }

        /* PIN Pad Styles */
        .pin-avatar-wrap {
            text-align: center;
            margin-bottom: 1rem;
        }

        .pin-avatar {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: linear-gradient(135deg, #10b981, #3b82f6);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            box-shadow: 0 0 25px rgba(16, 185, 129, 0.3);
            border: 2px solid rgba(255, 255, 255, 0.2);
        }

        .pin-dots {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin: 1.5rem 0;
            direction: ltr;
        }

        .pin-dot {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            border: 2px solid rgba(255, 255, 255, 0.3);
            background: transparent;
            transition: all 0.2s ease;
        }

        .pin-dot.filled {
            background: #10b981;
            border-color: #10b981;
            box-shadow: 0 0 12px #10b981;
            transform: scale(1.15);
        }

        .numpad {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.75rem;
            max-width: 280px;
            margin: 0 auto;
            direction: ltr;
        }

        .num-btn {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 14px;
            padding: 0.85rem 0;
            font-size: 1.35rem;
            font-weight: 800;
            color: #fff;
            cursor: pointer;
            transition: all 0.15s;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: inherit;
        }

        .num-btn:hover {
            background: rgba(255, 255, 255, 0.14);
            border-color: #10b981;
            transform: scale(1.05);
        }

        .num-btn:active {
            transform: scale(0.95);
        }

        .num-btn.action-btn {
            font-size: 1rem;
            background: transparent;
            border-color: transparent;
            color: #94a3b8;
        }

        .num-btn.action-btn:hover {
            color: #f87171;
            background: rgba(239, 68, 68, 0.1);
        }

        .alert-box {
            padding: 0.75rem 1rem;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 700;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
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
    <div class="auth-logo">
        <div class="auth-logo-badge">
            <span>💰</span>
            <span>مصروفي Pro</span>
        </div>
    </div>

    @if(session('status') || session('success'))
        <div class="alert-box alert-success">
            <span>✅</span>
            <span>{{ session('status') ?? session('success') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="alert-box alert-error">
            <span>⚠️</span>
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    @if(isset($rememberedUser) && $rememberedUser)
        <!-- ============================================== -->
        <!-- QUICK PIN UNLOCK VIEW (When Device Remembers User) -->
        <!-- ============================================== -->
        <div id="quickPinSection">
            <div class="pin-avatar-wrap">
                <div class="pin-avatar">
                    {{ $rememberedUser->avatar ?? '👤' }}
                </div>
            </div>

            <h2 class="auth-title">مرحباً {{ $rememberedUser->name }}</h2>
            <p class="auth-desc">أدخل رمز الدخول السريع (PIN) لفتح التطبيق مباشرة 🔒</p>

            <form id="pinLoginForm" action="{{ route('pin.login') }}" method="POST">
                @csrf
                <input type="hidden" name="user_id" value="{{ $rememberedUser->id }}">
                <input type="hidden" name="pin" id="pinInput" value="">

                <div class="pin-dots" id="pinDots">
                    <div class="pin-dot" id="dot0"></div>
                    <div class="pin-dot" id="dot1"></div>
                    <div class="pin-dot" id="dot2"></div>
                    <div class="pin-dot" id="dot3"></div>
                </div>

                <div class="numpad">
                    <button type="button" class="num-btn" onclick="pressPin('1')">1</button>
                    <button type="button" class="num-btn" onclick="pressPin('2')">2</button>
                    <button type="button" class="num-btn" onclick="pressPin('3')">3</button>
                    <button type="button" class="num-btn" onclick="pressPin('4')">4</button>
                    <button type="button" class="num-btn" onclick="pressPin('5')">5</button>
                    <button type="button" class="num-btn" onclick="pressPin('6')">6</button>
                    <button type="button" class="num-btn" onclick="pressPin('7')">7</button>
                    <button type="button" class="num-btn" onclick="pressPin('8')">8</button>
                    <button type="button" class="num-btn" onclick="pressPin('9')">9</button>
                    <button type="button" class="num-btn action-btn" onclick="clearPin()">مسح</button>
                    <button type="button" class="num-btn" onclick="pressPin('0')">0</button>
                    <button type="button" class="num-btn action-btn" onclick="backspacePin()">⌫</button>
                </div>
            </form>

            <div style="text-align: center; margin-top: 1.5rem; display: flex; flex-direction: column; gap: 0.5rem;">
                <button type="button" class="auth-link" style="background: none; border: none; cursor: pointer; font-size: 0.88rem;" onclick="toggleLoginMode('password')">
                    🔑 أو الدخول بكلمة المرور
                </button>
                <form action="{{ route('device.forget') }}" method="POST" style="margin: 0;">
                    @csrf
                    <button type="submit" class="auth-link" style="background: none; border: none; cursor: pointer; font-size: 0.8rem; color: #94a3b8;">
                        👤 تبديل الحساب / تسجيل مستخدم آخر
                    </button>
                </form>
            </div>
        </div>

        <!-- HIDDEN PASSWORD SECTION (Can toggle) -->
        <div id="passwordSection" style="display: none;">
            <h2 class="auth-title">تسجيل الدخول الكامل</h2>
            <p class="auth-desc">أدخل بيانات اعتماد حسابك المسجل</p>

            <form action="{{ route('login.submit') }}" method="POST" class="auth-form">
                @csrf
                <div class="auth-input-group">
                    <label class="auth-label">
                        <span>👤</span>
                        <span>البريد الإلكتروني / الجوال / اسم المستخدم:</span>
                    </label>
                    <input type="text" name="login_identifier" class="auth-input" value="{{ old('login_identifier', $rememberedUser->username) }}" required autofocus placeholder="مثال: admin أو 059... أو name@mail.com">
                </div>

                <div class="auth-input-group">
                    <label class="auth-label">
                        <span>🔑</span>
                        <span>كلمة المرور:</span>
                    </label>
                    <div class="password-input-wrap">
                        <input type="password" name="password" id="rememberedPassword" class="auth-input" required placeholder="••••••••">
                        <button type="button" class="btn-password-toggle" onclick="togglePasswordVisibility('rememberedPassword', this)" title="إظهار كلمة المرور" aria-label="إظهار كلمة المرور">
                            <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>

                <div class="auth-footer-links">
                    <label style="display: flex; align-items: center; gap: 0.4rem; cursor: pointer; font-size: 0.85rem; color: #cbd5e1;">
                        <input type="checkbox" name="remember" checked style="accent-color: #10b981;">
                        <span>تذكر هذا الجهاز</span>
                    </label>
                    <a href="{{ route('password.request') }}" class="auth-link">نسيت كلمة المرور؟</a>
                </div>

                <button type="submit" class="auth-btn-primary">
                    🚀 دخول إلى مصروفي Pro
                </button>
            </form>

            <div style="text-align: center; margin-top: 1.25rem;">
                <button type="button" class="auth-link" style="background: none; border: none; cursor: pointer;" onclick="toggleLoginMode('pin')">
                    🔒 العودة للدخول السريع بـ PIN
                </button>
            </div>
        </div>

    @else
        <!-- ============================================== -->
        <!-- STANDARD LOGIN VIEW (Email / Phone / Username) -->
        <!-- ============================================== -->
        <div>
            <h2 class="auth-title">مرحباً بك في مصروفي Pro</h2>
            <p class="auth-desc">سجّل دخولك بواسطة الإيميل أو الجوال أو اسم المستخدم</p>

            <form action="{{ route('login.submit') }}" method="POST" class="auth-form">
                @csrf
                <div class="auth-input-group">
                    <label class="auth-label">
                        <span>👤</span>
                        <span>البريد الإلكتروني / رقم الجوال / اسم المستخدم:</span>
                    </label>
                    <input type="text" name="login_identifier" class="auth-input" value="{{ old('login_identifier') }}" required autofocus placeholder="admin أو 0599000000 أو name@example.com">
                </div>

                <div class="auth-input-group">
                    <label class="auth-label">
                        <span>🔑</span>
                        <span>كلمة المرور:</span>
                    </label>
                    <div class="password-input-wrap">
                        <input type="password" name="password" id="loginPassword" class="auth-input" required placeholder="••••••••">
                        <button type="button" class="btn-password-toggle" onclick="togglePasswordVisibility('loginPassword', this)" title="إظهار كلمة المرور" aria-label="إظهار كلمة المرور">
                            <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>

                <div class="auth-footer-links">
                    <label style="display: flex; align-items: center; gap: 0.4rem; cursor: pointer; font-size: 0.85rem; color: #cbd5e1;">
                        <input type="checkbox" name="remember" checked style="accent-color: #10b981;">
                        <span>تذكر هذا الجهاز</span>
                    </label>
                    <a href="{{ route('password.request') }}" class="auth-link">نسيت كلمة المرور؟</a>
                </div>

                <button type="submit" class="auth-btn-primary">
                    🚀 تسجيل الدخول الآن
                </button>
            </form>

            <div style="text-align: center; margin-top: 1.5rem; font-size: 0.8rem; color: #94a3b8; border-top: 1px solid rgba(255,255,255,0.08); padding-top: 1rem;">
                🛡️ نظام الحسابات خاص ومغلق. يتم تزويد الحسابات بواسطة مدير النظام فقط.
            </div>
        </div>
    @endif

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

    let currentPin = '';

    function updateDots() {
        for (let i = 0; i < 4; i++) {
            const dot = document.getElementById('dot' + i);
            if (dot) {
                if (i < currentPin.length) {
                    dot.classList.add('filled');
                } else {
                    dot.classList.remove('filled');
                }
            }
        }
    }

    function pressPin(digit) {
        if (currentPin.length < 4) {
            currentPin += digit;
            updateDots();
            if (currentPin.length === 4) {
                document.getElementById('pinInput').value = currentPin;
                setTimeout(() => {
                    document.getElementById('pinLoginForm').submit();
                }, 200);
            }
        }
    }

    function clearPin() {
        currentPin = '';
        updateDots();
    }

    function backspacePin() {
        if (currentPin.length > 0) {
            currentPin = currentPin.slice(0, -1);
            updateDots();
        }
    }

    function toggleLoginMode(mode) {
        const pinSec = document.getElementById('quickPinSection');
        const passSec = document.getElementById('passwordSection');
        if (mode === 'password') {
            if (pinSec) pinSec.style.display = 'none';
            if (passSec) passSec.style.display = 'block';
        } else {
            if (passSec) passSec.style.display = 'none';
            if (pinSec) pinSec.style.display = 'block';
            clearPin();
        }
    }

    // Keyboard support for PIN entry
    document.addEventListener('keydown', function(e) {
        if (document.getElementById('quickPinSection') && document.getElementById('quickPinSection').style.display !== 'none') {
            if (e.key >= '0' && e.key <= '9') {
                pressPin(e.key);
            } else if (e.key === 'Backspace') {
                backspacePin();
            } else if (e.key === 'Escape') {
                clearPin();
            }
        }
    });
</script>

</body>
</html>
