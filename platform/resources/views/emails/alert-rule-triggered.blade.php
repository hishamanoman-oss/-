<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>{{ $alertTitle }}</title>
</head>
<body style="font-family: Arial, Tahoma, sans-serif; background:#f8fafc; padding:24px; color:#0f172a;">
    <div style="max-width:600px; margin:0 auto; background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; padding:24px;">
        <p style="margin:0 0 8px; font-size:12px; color:#64748b;">{{ $tenantName }} • {{ strtoupper($severity) }}</p>
        <h2 style="margin:0 0 12px; font-size:18px; color:#0f172a;">{{ $alertTitle }}</h2>
        <p style="margin:0 0 20px; font-size:14px; line-height:1.6; color:#334155;">{{ $alertBody }}</p>
        @if ($actionUrl)
            <p style="margin:0;">
                <a href="{{ url($actionUrl) }}" style="display:inline-block; padding:10px 16px; background:#0f4c81; color:#ffffff; text-decoration:none; border-radius:8px; font-size:13px;">
                    فتح التفاصيل في المنصة
                </a>
            </p>
        @endif
    </div>
</body>
</html>
