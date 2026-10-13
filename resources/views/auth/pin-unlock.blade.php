<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>شاشة القفل السريعة - {{ config('app.name', 'مصروفي Pro') }}</title>

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
            max-width: 420px;
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 2.5rem 2rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), 0 0 40px rgba(16, 185, 129, 0.1);
        }

        .pin-avatar {
            width: 75px;
            height: 75px;
            border-radius: 50%;
            background: linear-gradient(135deg, #10b981, #3b82f6);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            box-shadow: 0 0 25px rgba(16, 185, 129, 0.3);
            border: 2px solid rgba(255, 255, 255, 0.2);
            margin: 0 auto 1rem auto;
        }

        .pin-dots {
            display: flex;
            justify-content: center;
            gap: 1.1rem;
            margin: 1.75rem 0;
            direction: ltr;
        }

        .pin-dot {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid rgba(255, 255, 255, 0.3);
            background: transparent;
            transition: all 0.2s ease;
        }

        .pin-dot.filled {
            background: #10b981;
            border-color: #10b981;
            box-shadow: 0 0 14px #10b981;
            transform: scale(1.15);
        }

        .numpad {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.85rem;
            max-width: 280px;
            margin: 0 auto;
            direction: ltr;
        }

        .num-btn {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 14px;
            padding: 0.85rem 0;
            font-size: 1.4rem;
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

        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            padding: 0.75rem 1rem;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 700;
            margin-bottom: 1.25rem;
            text-align: center;
        }
    </style>
</head>
<body class="auth-body">

<div class="auth-card">
    <div style="text-align: center;">
        <div class="pin-avatar">
            {{ $user->avatar ?? '👤' }}
        </div>
        <h2 style="font-size: 1.35rem; font-weight: 800; margin: 0 0 0.3rem 0;">{{ $user->name }}</h2>
        <p style="color: #94a3b8; font-size: 0.85rem; margin: 0;">أدخل رمز الـ PIN المكوّن من 4 أرقام لفتح التطبيق 🔓</p>
    </div>

    @if($errors->any())
        <div class="alert-error" style="margin-top: 1rem;">
            <span>⚠️</span> <span>{{ $errors->first() }}</span>
        </div>
    @endif

    <form id="pinUnlockForm" action="{{ route('pin.unlock.submit') }}" method="POST">
        @csrf
        <input type="hidden" name="pin" id="unlockPin" value="">

        <div class="pin-dots">
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

    <div style="text-align: center; margin-top: 1.5rem;">
        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" style="background: none; border: none; color: #94a3b8; font-size: 0.82rem; cursor: pointer; text-decoration: underline;">
                🚪 تسجيل الخروج والدخول بحساب آخر
            </button>
        </form>
    </div>
</div>

<script>
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
                document.getElementById('unlockPin').value = currentPin;
                setTimeout(() => {
                    document.getElementById('pinUnlockForm').submit();
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

    // Keyboard support
    document.addEventListener('keydown', function(e) {
        if (e.key >= '0' && e.key <= '9') {
            pressPin(e.key);
        } else if (e.key === 'Backspace') {
            backspacePin();
        } else if (e.key === 'Escape') {
            clearPin();
        }
    });
</script>

</body>
</html>
