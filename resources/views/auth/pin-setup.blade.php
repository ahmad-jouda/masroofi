<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعداد رمز الدخول السريع (PIN) - {{ config('app.name', 'مصروفي Pro') }}</title>

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

        .pin-box-row {
            display: flex;
            justify-content: center;
            gap: 0.75rem;
            margin: 0.75rem 0 1.25rem 0;
            direction: ltr;
        }

        .pin-box {
            width: 55px;
            height: 60px;
            text-align: center;
            font-size: 1.8rem;
            font-weight: 800;
            border-radius: 14px;
            background: rgba(15, 23, 42, 0.8);
            border: 2px solid rgba(255, 255, 255, 0.12);
            color: #10b981;
            transition: all 0.2s;
            outline: none;
            box-sizing: border-box;
        }

        .pin-box:focus {
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.25);
            background: rgba(15, 23, 42, 1);
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
            margin-top: 1rem;
            font-family: inherit;
        }

        .auth-btn-primary:hover {
            transform: translateY(-1px);
            background: linear-gradient(135deg, #34d399, #10b981);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            padding: 0.75rem 1rem;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 700;
            margin-bottom: 1.25rem;
        }
    </style>
</head>
<body class="auth-body">

<div class="auth-card">
    <div style="text-align: center; margin-bottom: 1rem; font-size: 2.5rem;">
        🔐
    </div>

    <h2 class="auth-title">إعداد رمز الدخول السريع (PIN)</h2>
    <p class="auth-desc">
        اختر رمز PIN مكوّن من 4 أرقام لتسهيل الدخول السريع في المرات القادمة دون الحاجة لإدخال الإيميل وكلمة المرور في كل مرة! ⚡
    </p>

    @if($errors->any())
        <div class="alert-error">
            <span>⚠️</span> <span>{{ $errors->first() }}</span>
        </div>
    @endif

    <form action="{{ route('pin.setup.store') }}" method="POST">
        @csrf
        <div style="margin-bottom: 1.25rem;">
            <label style="font-size: 0.88rem; font-weight: 700; color: #e2e8f0; display: block; text-align: center; margin-bottom: 0.5rem;">
                أدخل رمز الـ PIN المكوّن من 4 أرقام:
            </label>
            <div class="pin-box-row">
                <input type="password" inputmode="numeric" maxlength="1" class="pin-box" id="p1" required autofocus oninput="moveFocus(this, 'p2')">
                <input type="password" inputmode="numeric" maxlength="1" class="pin-box" id="p2" required oninput="moveFocus(this, 'p3')" onkeydown="checkBack(event, this, 'p1')">
                <input type="password" inputmode="numeric" maxlength="1" class="pin-box" id="p3" required oninput="moveFocus(this, 'p4')" onkeydown="checkBack(event, this, 'p2')">
                <input type="password" inputmode="numeric" maxlength="1" class="pin-box" id="p4" required oninput="moveFocus(this, 'c1')" onkeydown="checkBack(event, this, 'p3')">
            </div>
            <input type="hidden" name="pin" id="fullPin">
        </div>

        <div style="margin-bottom: 1.25rem;">
            <label style="font-size: 0.88rem; font-weight: 700; color: #e2e8f0; display: block; text-align: center; margin-bottom: 0.5rem;">
                أعد كتابة الـ PIN للتأكيد:
            </label>
            <div class="pin-box-row">
                <input type="password" inputmode="numeric" maxlength="1" class="pin-box" id="c1" required oninput="moveFocus(this, 'c2')" onkeydown="checkBack(event, this, 'p4')">
                <input type="password" inputmode="numeric" maxlength="1" class="pin-box" id="c2" required oninput="moveFocus(this, 'c3')" onkeydown="checkBack(event, this, 'c1')">
                <input type="password" inputmode="numeric" maxlength="1" class="pin-box" id="c3" required oninput="moveFocus(this, 'c4')" onkeydown="checkBack(event, this, 'c2')">
                <input type="password" inputmode="numeric" maxlength="1" class="pin-box" id="c4" required oninput="submitReady()" onkeydown="checkBack(event, this, 'c3')">
            </div>
            <input type="hidden" name="pin_confirmation" id="fullPinConfirm">
        </div>

        <button type="submit" class="auth-btn-primary" onclick="prepareSubmit(event)">
            🔒 تثبيت رمز الـ PIN والانتقال للتطبيق
        </button>
    </form>

    <div style="text-align: center; margin-top: 1.25rem;">
        <a href="{{ route('dashboard') }}" style="color: #94a3b8; font-size: 0.85rem; text-decoration: none;">
            تخطي والإعداد لاحقاً ←
        </a>
    </div>
</div>

<script>
    function moveFocus(current, nextId) {
        if (current.value.length >= 1) {
            const next = document.getElementById(nextId);
            if (next) next.focus();
        }
    }

    function checkBack(e, current, prevId) {
        if (e.key === 'Backspace' && current.value.length === 0) {
            const prev = document.getElementById(prevId);
            if (prev) prev.focus();
        }
    }

    function prepareSubmit(e) {
        const pin = (document.getElementById('p1').value || '') +
                    (document.getElementById('p2').value || '') +
                    (document.getElementById('p3').value || '') +
                    (document.getElementById('p4').value || '');

        const pinConfirm = (document.getElementById('c1').value || '') +
                           (document.getElementById('c2').value || '') +
                           (document.getElementById('c3').value || '') +
                           (document.getElementById('c4').value || '');

        document.getElementById('fullPin').value = pin;
        document.getElementById('fullPinConfirm').value = pinConfirm;

        if (pin.length !== 4 || pinConfirm.length !== 4) {
            e.preventDefault();
            alert('يرجى تعبئة الأرقام الأربعة بالكامل في الخانتين');
        }
    }

    function submitReady() {
        const pin = (document.getElementById('p1').value || '') +
                    (document.getElementById('p2').value || '') +
                    (document.getElementById('p3').value || '') +
                    (document.getElementById('p4').value || '');

        const pinConfirm = (document.getElementById('c1').value || '') +
                           (document.getElementById('c2').value || '') +
                           (document.getElementById('c3').value || '') +
                           (document.getElementById('c4').value || '');

        document.getElementById('fullPin').value = pin;
        document.getElementById('fullPinConfirm').value = pinConfirm;
    }
</script>

</body>
</html>
