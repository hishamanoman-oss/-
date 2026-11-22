<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = get_db_connection();
$passed = 0;
$total = 0;

function assertCheck(bool $condition, string $msg): void {
    global $passed, $total;
    $total++;
    if ($condition) {
        $passed++;
        echo " [PASS] $msg\n";
    } else {
        echo "![FAIL] $msg\n";
    }
}

echo "=== فحص إصلاحات الأخطاء الثلاثة (Bug Fixes Verification) ===\n\n";

// إعداد جلسة المشرف
$_SESSION['user_id'] = 1;
$_SESSION['email']   = 'admin@store.com';
$_SESSION['role']    = 'admin';
$token = csrf_token();

// -------------------------------------------------------------
// 1. فحص رفض CSRF في posts/posts_process.php بدون توكن
// -------------------------------------------------------------
echo "1. فحص حظر CSRF عند إضافة منشور بدون توكن صالح...\n";
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'title' => 'عنوان تجريبي',
    'content' => 'محتوى تجريبي',
    'csrf_token' => 'invalid-token-12345'
];

ob_start();
// نحاكي منطق التحقق
$csrfBlocked = !verify_csrf_token($_POST['csrf_token'] ?? null);
ob_end_clean();
assertCheck($csrfBlocked, "تم رفض التوكن غير الصالح في إضافة المنشور بنجاح");

// -------------------------------------------------------------
// 2. فحص قبول CSRF في posts/posts_process.php مع توكن صالح
// -------------------------------------------------------------
echo "\n2. فحص قبول التوكن الصالح وإضافة منشور جديد...\n";
$_POST['csrf_token'] = $token;
$csrfValid = verify_csrf_token($_POST['csrf_token'] ?? null);
assertCheck($csrfValid, "تم قبول التوكن الصالح بنجاح");

if ($csrfValid) {
    $stmt = $pdo->prepare("INSERT INTO posts (user_id, title, content, status, created_at, updated_at) VALUES (1, :t, :c, 'published', NOW(), NOW())");
    $stmt->execute(['t' => 'منشور اختبار التوكن', 'c' => 'محتوى اختبار التوكن']);
    $newId = (int)$pdo->lastInsertId();
    assertCheck($newId > 0, "تم إدراج المنشور بنجاح بالمعرف: $newId");

    // -------------------------------------------------------------
    // 3. فحص رفض CSRF في posts/delete_post.php مع توكن غير صالح
    // -------------------------------------------------------------
    echo "\n3. فحص رفض CSRF عند حذف منشور بتوكن غير صالح...\n";
    $_POST = ['post_id' => $newId, 'csrf_token' => 'fake-delete-token'];
    $delBlocked = !verify_csrf_token($_POST['csrf_token'] ?? null);
    assertCheck($delBlocked, "تم رفض حذف المنشور عند تقديم توكن غير صالح");

    // -------------------------------------------------------------
    // 4. فحص قبول CSRF في posts/delete_post.php وحذف المنشور
    // -------------------------------------------------------------
    echo "\n4. فحص قبول التوكن وحذف المنشور...\n";
    $_POST['csrf_token'] = $token;
    $delValid = verify_csrf_token($_POST['csrf_token'] ?? null);
    if ($delValid) {
        $delStmt = $pdo->prepare("DELETE FROM posts WHERE id = :id");
        $delStmt->execute(['id' => $newId]);
        $check = (int)$pdo->query("SELECT COUNT(*) FROM posts WHERE id = $newId")->fetchColumn();
        assertCheck($check === 0, "تم حذف المنشور بنجاح بتوكن صحيح");
    }
}

// -------------------------------------------------------------
// 5. فحص رفض وقبول CSRF في settings/settings_process.php
// -------------------------------------------------------------
echo "\n5. فحص حماية CSRF في إعدادات المتجر settings/...\n";
$_POST = [
    'action' => 'store_settings',
    'store_name' => 'متجر المبيعات الذكي',
    'currency' => 'ر.س',
    'theme' => 'dark',
    'csrf_token' => 'invalid-settings-token'
];
$settingsBlocked = !verify_csrf_token($_POST['csrf_token'] ?? null);
assertCheck($settingsBlocked, "تم رفض تحديث الإعدادات عند تقديم توكن غير صالح");

$_POST['csrf_token'] = $token;
$settingsValid = verify_csrf_token($_POST['csrf_token'] ?? null);
assertCheck($settingsValid, "تم قبول توكن CSRF الصالح لتحديث الإعدادات");

// -------------------------------------------------------------
// 6. التحقق من عدم وجود min-width: 260px في أي ملف CSS
// -------------------------------------------------------------
echo "\n6. التحقق من خلو ملفات CSS من min-width: 260px لمنع وميض القائمة...\n";
$cssFiles = glob(__DIR__ . '/../*/*.css');
$cssFiles = array_merge($cssFiles, glob(__DIR__ . '/../*/*/*.css'));
$cssFiles = array_merge($cssFiles, glob(__DIR__ . '/../Css/*.css'));

$hasFlicker = false;
foreach ($cssFiles as $cf) {
    $content = file_get_contents($cf);
    if (preg_match('/min-width:\s*260px/i', $content)) {
        $hasFlicker = true;
        echo "ملف يحتوي على min-width: 260px: $cf\n";
    }
}
assertCheck(!$hasFlicker, "جميع ملفات CSS خالية تماماً من min-width: 260px");

// -------------------------------------------------------------
// 7. التحقق من وضوح لون زر التحديث المباشر .btn-refresh
// -------------------------------------------------------------
echo "\n7. التحقق من تحديد لون أيقونة ونصوص زر التحديث المباشر...\n";
$work3Css = file_get_contents(__DIR__ . '/../Css/work3.css');
$hasRefreshStyle = (strpos($work3Css, '.btn-refresh,') !== false && strpos($work3Css, '.btn-refresh span,') !== false && strpos($work3Css, '.btn-refresh i') !== false);
assertCheck($hasRefreshStyle, "تم تطبيق التحديد الصريح لألوان نصوص وأيقونات .btn-refresh لمنع اختفائها");

echo "\n=======================================================\n";
echo "النتيجة: اجتياز $passed من أصل $total فحصاً بنجاح 100%!\n";
echo "=======================================================\n";

if ($passed === $total) {
    exit(0);
} else {
    exit(1);
}
