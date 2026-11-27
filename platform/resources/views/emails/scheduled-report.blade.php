<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>{{ $reportName }}</title>
</head>
<body style="font-family: Arial, Tahoma, sans-serif; background:#f8fafc; padding:24px; color:#0f172a;">
    <div style="max-width:600px; margin:0 auto; background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; padding:24px;">
        <p style="margin:0 0 8px; font-size:12px; color:#64748b;">{{ $tenantName }}</p>
        <h2 style="margin:0 0 10px; font-size:18px;">{{ $reportName }}</h2>
        <p style="margin:0 0 8px; font-size:14px; color:#334155;">{{ $reportTitle }} — {{ $subtitle }}</p>
        <p style="margin:0; font-size:12px; color:#64748b;">مرفق نسخة التقرير المجدول: {{ $fileName }}</p>
    </div>
</body>
</html>
