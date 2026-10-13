<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعادة تعيين كلمة المرور - مصروفي Pro</title>
</head>
<body style="margin: 0; padding: 0; background-color: #0b1120; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; direction: rtl; text-align: right; color: #f1f5f9;">

    <!-- Wrapper Table -->
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #0b1120; min-height: 100vh; padding: 40px 15px;">
        <tr>
            <td align="center" valign="top">

                <!-- Main Container -->
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width: 580px; background-color: #1e293b; border-radius: 20px; border: 1px solid rgba(255, 255, 255, 0.1); box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4); overflow: hidden;">
                    
                    <!-- Header Banner -->
                    <tr>
                        <td align="center" style="background: linear-gradient(135deg, #064e3b 0%, #0f172a 100%); padding: 36px 30px 28px; border-bottom: 1px solid rgba(16, 185, 129, 0.2);">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td align="center">
                                        <!-- Logo Icon Circle -->
                                        <div style="width: 64px; height: 64px; line-height: 64px; background: linear-gradient(135deg, #10b981, #059669); border-radius: 18px; font-size: 30px; text-align: center; margin: 0 auto 14px; box-shadow: 0 8px 20px rgba(16, 185, 129, 0.35);">
                                            🔐
                                        </div>
                                        <div style="font-size: 22px; font-weight: 800; color: #ffffff; letter-spacing: -0.5px;">
                                            مصروفي <span style="color: #34d399; font-size: 16px; background: rgba(16, 185, 129, 0.2); border: 1px solid rgba(52, 211, 153, 0.4); padding: 2px 8px; border-radius: 6px; margin-right: 4px;">PRO</span>
                                        </div>
                                        <div style="color: #94a3b8; font-size: 13px; margin-top: 4px;">
                                            إدارة المصاريف والمحفظة الذكية
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Body Content -->
                    <tr>
                        <td style="padding: 34px 34px 28px;">
                            
                            <h1 style="font-size: 20px; font-weight: 800; color: #f8fafc; margin: 0 0 16px 0; text-align: right;">
                                مرحباً {{ $user->name }} 👋
                            </h1>

                            <p style="font-size: 15px; line-height: 1.8; color: #cbd5e1; margin: 0 0 20px 0; text-align: right;">
                                تلقينا طلباً لإعادة تعيين كلمة المرور لحسابك في تطبيق <strong style="color: #34d399;">«مصروفي Pro»</strong>. اضغط على الزر الآمن أدناه لتعيين كلمة مرور جديدة والانتقال إلى لوحة التحكم:
                            </p>

                            <!-- CTA Button -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 28px 0;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $resetUrl }}" target="_blank" style="display: inline-block; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #ffffff; font-size: 16px; font-weight: 800; text-decoration: none; padding: 15px 36px; border-radius: 12px; box-shadow: 0 10px 25px rgba(16, 185, 129, 0.4); border: 1px solid rgba(255, 255, 255, 0.15);">
                                            🔐 تعيين كلمة مرور جديدة الآن
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <!-- Important Security Notice Box -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 14px; margin: 24px 0 20px;">
                                <tr>
                                    <td style="padding: 18px 20px;">
                                        <div style="font-size: 13.5px; font-weight: 700; color: #38bdf8; margin-bottom: 8px;">
                                            🛡️ إرشادات الأمان وشروط كلمة المرور:
                                        </div>
                                        <ul style="margin: 0; padding-right: 18px; color: #94a3b8; font-size: 13px; line-height: 1.8;">
                                            <li>هذا الرابط مخصص لك وصالح للاستخدام <strong>لمرة واحدة فقط</strong>.</li>
                                            <li>تنتهي صلاحية الرابط تلقائياً بعد <strong>60 دقيقة</strong> لأسباب أمنية.</li>
                                            <li>يجب أن تتكون كلمة المرور الجديدة من <strong>9 خانات على الأقل</strong> وتحتوي على أرقام وأحرف كبيرة وصغيرة <span style="direction: ltr; display: inline-block;">(A-Z, a-z, 0-9)</span>.</li>
                                        </ul>
                                    </td>
                                </tr>
                            </table>

                            <!-- Reassurance -->
                            <p style="font-size: 13px; line-height: 1.6; color: #64748b; margin: 0 0 24px 0; text-align: right;">
                                🔒 إذا لم تكن قد طلبت إعادة تعيين كلمة المرور، يمكنك تجاهل هذه الرسالة بأمان تام، وستبقى كلمة المرور الحالية لحسابك كما هي دون أي تغيير.
                            </p>

                            <!-- Direct Link Fallback -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-top: 1px dashed rgba(255, 255, 255, 0.1); padding-top: 20px;">
                                <tr>
                                    <td>
                                        <div style="font-size: 12px; color: #64748b; margin-bottom: 6px;">
                                            إذا لم يعمل الزر أعلاه، انسخ الرابط الآمن التالي والصقه في متصفحك:
                                        </div>
                                        <div style="font-size: 11.5px; word-break: break-all; color: #38bdf8; background: rgba(15, 23, 42, 0.8); padding: 10px 12px; border-radius: 8px; border: 1px solid rgba(56, 189, 248, 0.2); direction: ltr; text-align: left; font-family: monospace;">
                                            {{ $resetUrl }}
                                        </div>
                                    </td>
                                </tr>
                            </table>

                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td align="center" style="background-color: #0f172a; padding: 22px 30px; border-top: 1px solid rgba(255, 255, 255, 0.06); text-align: center;">
                            <div style="font-size: 12px; color: #64748b; line-height: 1.7;">
                                تم إرسال هذه الرسالة تلقائياً إلى <strong style="color: #94a3b8;">{{ $user->email }}</strong> بناءً على طلبك.<br>
                                © {{ date('Y') }} مصروفي Pro - جميع الحقوق محفوظة.
                            </div>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>
</html>
