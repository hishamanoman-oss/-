<?php
/**
 * معالجة تسجيل الدخول الآمن
 * Secure Authentication Processor
 */

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

// 1. التحقق من طريقة الطلب (يجب أن تكون POST حصراً)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "طريقة الطلب غير مسموح بها";
    exit();
}

// 2. التحقق من رمز الحماية CSRF
$csrfToken = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrfToken)) {
    http_response_code(403);
    echo "خطأ أمني: رمز الأمان (CSRF Token) غير صالح أو انتهت صلاحية الجلسة";
    exit();
}

// 3. التحقق من المدخلات
$email    = trim($_POST['email'] ?? '');
$password = (string)($_POST['password'] ?? '');

if ($email === '' || $password === '') {
    echo "الرجاء إدخال البريد الإلكتروني وكلمة المرور";
    exit();
}

try {
    $pdo = get_db_connection();

    // 4. استعلام مجهز بالكامل للحماية من حقن SQL
    $stmt = $pdo->prepare("
        SELECT `id`, `full_name`, `email`, `password_hash`, `role`, `is_active`
        FROM `users`
        WHERE `email` = :email
        LIMIT 1
    ");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    // 5. التحقق من كلمة المرور عبر التشفير الموثوق
    if ($user && password_verify($password, $user['password_hash'])) {
        // التحقق من حالة الحساب
        if ((int)$user['is_active'] !== 1) {
            echo "هذا الحساب معطل حالياً، يرجى التواصل مع إدارة النظام";
            exit();
        }

        // 6. تجديد معرف الجلسة لمنع هجمات تثبيت الجلسة (Session Fixation)
        if (!headers_sent()) {
            session_regenerate_id(true);
        }

        // 7. تخزين بيانات المستخدم والأدوار في الجلسة
        $_SESSION['user_id']   = (int)$user['id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['email']     = $user['email'];
        $_SESSION['role']      = $user['role'];

        // دعم التحويل المباشر في حال إرسال النموذج بدون AJAX
        if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'xmlhttprequest') {
            header("Location: index.php");
            exit();
        }

        echo "success";
    } else {
        echo "البريد الإلكتروني أو كلمة المرور غير صحيحة";
    }
} catch (Throwable $e) {
    error_log("Login processing error: " . $e->getMessage());
    echo "حدث خطأ غير متوقع أثناء معالجة تسجيل الدخول. يرجى المحاولة لاحقاً.";
}
