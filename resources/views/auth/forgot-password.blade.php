<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>استعادة كلمة المرور - {{ config('app.name', 'مصروفي Pro') }}</title>

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

        .alert-box {
            padding: 0.85rem 1rem;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 700;
            margin-bottom: 1.25rem;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #6ee7b7;
        }
    </style>
</head>
<body class="auth-body">

<div class="auth-card">
    <div style="text-align: center; margin-bottom: 1rem; font-size: 2.2rem;">
        📧
    </div>

    <h2 class="auth-title">استعادة كلمة المرور عبر الإيميل</h2>
    <p class="auth-desc">
        لحماية حسابك وأمان بياناتك المالية، يتم استعادة كلمة المرور حصرياً عبر البريد الإلكتروني المسجل.
    </p>

    @if(session('status'))
        <div class="alert-box alert-success">
            <span>✅</span> <span>{{ session('status') }}</span>
        </div>
        @if(session('simulated_reset_link'))
            <div style="background: rgba(59, 130, 246, 0.12); border: 1px solid rgba(59, 130, 246, 0.3); padding: 0.75rem; border-radius: 10px; margin-bottom: 1rem; font-size: 0.8rem; text-align: center;">
                <div style="color: #93c5fd; font-weight: 700; margin-bottom: 0.3rem;">🔗 رابط إعادة التعيين الآمن المولد:</div>
                <a href="{{ session('simulated_reset_link') }}" style="color: #60a5fa; word-break: break-all; font-weight: 800;">اضغط هنا لتعيين كلمة المرور الآن ←</a>
            </div>
        @endif
    @endif

    @if($errors->any())
        <div class="alert-box alert-error">
            <span>⚠️</span> <span>{{ $errors->first() }}</span>
        </div>
    @endif

    <form action="{{ route('password.email') }}" method="POST">
        @csrf
        <div style="display: flex; flex-direction: column; gap: 0.4rem;">
            <label style="font-size: 0.85rem; font-weight: 700; color: #e2e8f0;">
                البريد الإلكتروني المسجل:
            </label>
            <input type="email" name="email" class="auth-input" value="{{ old('email') }}" required autofocus placeholder="name@example.com">
        </div>

        <button type="submit" class="auth-btn-primary">
            📩 إرسال رابط التعيين إلى الإيميل
        </button>
    </form>

    <div style="text-align: center; margin-top: 1.5rem;">
        <a href="{{ route('login') }}" style="color: #38bdf8; font-size: 0.88rem; text-decoration: none; font-weight: 700;">
            ← العودة لصفحة تسجيل الدخول
        </a>
    </div>
</div>

</body>
</html>
