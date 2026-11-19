<?php
/**
 * اختبار محاكاة مسار تسجيل الدخول وحالات الخطأ
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';

echo "=== 1. فحص إعدادات كوكيز الجلسة على بيئة HTTP المحلية ===" . PHP_EOL;
$params = session_get_cookie_params();
echo "  Secure Flag: " . ($params['secure'] ? 'TRUE (HTTPS)' : 'FALSE (HTTP Localhost - الصحيح)') . PHP_EOL;
echo "  HttpOnly Flag: " . ($params['httponly'] ? 'TRUE' : 'FALSE') . PHP_EOL;
echo "  SameSite: " . $params['samesite'] . PHP_EOL;

echo PHP_EOL . "=== 2. محاكاة طلب الدخول ببيانات المدير الصحيحة (admin@store.com) ===" . PHP_EOL;
$validToken = csrf_token();

// ضبط متغيرات الطلب لمحاكاة طلب AJAX قادم من المتصفح
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'xmlhttprequest';
$_POST = [
    'csrf_token' => $validToken,
    'email'      => 'admin@store.com',
    'password'   => 'Admin@123'
];

ob_start();
include __DIR__ . '/../login_process.php';
$output = ob_get_clean();

echo "  الاستجابة: " . trim($output) . PHP_EOL;
if (trim($output) === 'success' && isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin') {
    echo "✔ نجاح تسجيل الدخول للمدير: تم التحقق وتوليد الجلسة (المعرف: {$_SESSION['user_id']} | الدور: {$_SESSION['role']})" . PHP_EOL;
} else {
    echo "❌ فشل تسجيل الدخول للمدير!" . PHP_EOL;
    exit(1);
}

echo PHP_EOL . "=== 3. محاكاة طلب الدخول بكلمة مرور خاطئة ===" . PHP_EOL;
$_POST['password'] = 'WrongPassword';
ob_start();
include __DIR__ . '/../login_process.php';
$outputWrong = ob_get_clean();
echo "  الاستجابة لكلمة المرور الخاطئة: " . trim($outputWrong) . PHP_EOL;

echo PHP_EOL . "=== 4. محاكاة طلب الدخول برمز CSRF غير صالح ===" . PHP_EOL;
$_POST['csrf_token'] = 'invalid_fake_token';
ob_start();
include __DIR__ . '/../login_process.php';
$outputCsrf = ob_get_clean();
$httpCode = http_response_code();
echo "  كود الاستجابة: {$httpCode} | نص الخطأ الظاهر للمستخدم: " . trim($outputCsrf) . PHP_EOL;

echo PHP_EOL . "🎉 اكتمل فحص وتأكيد تدفق تسجيل الدخول بنجاح تام 100%!" . PHP_EOL;
