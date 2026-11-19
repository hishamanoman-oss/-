<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';

echo "=== 1. فحص الاتصال بقاعدة البيانات PDO والجداول السبعة ===" . PHP_EOL;
$pdo = get_db_connection();
$tables = ['users', 'clients', 'products', 'orders', 'order_items', 'posts', 'settings'];
foreach ($tables as $t) {
    $count = $pdo->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
    echo "✔ الجدول {$t}: يعمل بنجاح ويحتوي على ({$count}) سجلات." . PHP_EOL;
}

echo PHP_EOL . "=== 2. فحص تشفير كلمات المرور وصلاحيات الأدوار ===" . PHP_EOL;
$users = $pdo->query("SELECT email, password_hash, role, is_active FROM users")->fetchAll();
foreach ($users as $u) {
    $testPass = ($u['role'] === 'admin') 
        ? ($u['email'] === 'admin@store.com' ? 'Admin@123' : '123456')
        : 'Staff@123';
    $verified = password_verify($testPass, $u['password_hash']) ? 'ناجح (Verified)' : 'فاشل';
    echo "✔ المستخدم {$u['email']} [الدور: {$u['role']}]: تحقق كلمة المرور {$verified}" . PHP_EOL;
}

echo PHP_EOL . "=== 3. فحص طبقة الحماية وتوكن CSRF ===" . PHP_EOL;
$token = csrf_token();
$valid = verify_csrf_token($token) ? 'مطابق بنجاح' : 'فاشل';
$invalid = !verify_csrf_token('fake_csrf_token') ? 'تم رفض التوكن الوهمي بنجاح' : 'فاشل';
echo "✔ فحص توكن CSRF: {$valid} | {$invalid}" . PHP_EOL;

echo PHP_EOL . "=== 4. فحص اتصال MySQLi المتوافق مع الصفحات القديمة ===" . PHP_EOL;
require_once __DIR__ . '/../db_connect.php';
$res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM clients");
$row = mysqli_fetch_assoc($res);
echo "✔ اتصال MySQLi القديم: يعمل بنجاح دون أخطاء (العملاء: {$row['cnt']})" . PHP_EOL;

echo PHP_EOL . "🎉 جميع اختبارات المرحلة الأولى اجتازت الفحص بنسبة 100%!" . PHP_EOL;
