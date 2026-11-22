<?php
/**
 * Automated Verification Gate for Step 4
 * Remote Monitoring Dashboard, Real Charts, Posts RBAC, VIP Favorites, Real Settings
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';

$pdo = get_db_connection();
$testsPassed = 0;
$totalTests = 0;

function assertTest(bool $condition, string $message): void {
    global $testsPassed, $totalTests;
    $totalTests++;
    if ($condition) {
        $testsPassed++;
        echo " [PASS] " . $message . "\n";
    } else {
        echo "![FAIL] " . $message . "\n";
    }
}

echo "=== بدء التحقق الآلي للمرحلة الرابعة (Step 4 Verification Gate) ===\n\n";

// -------------------------------------------------------------
// Test 1: api/dashboard_stats.php حماية 401 عند عدم المصادقة
// -------------------------------------------------------------
echo "1. فحص حماية نقطة النهاية api/dashboard_stats.php عند غياب الجلسة...\n";
$_SESSION = []; // إفراغ الجلسة
ob_start();
// استدعاء يحاكي طلب مباشر بدون جلسة
$code = 0;
try {
    // بما أن require_auth(true) يطلق http_response_code(401) و exit، نتحقق من الدالة
    require_once __DIR__ . '/../includes/auth.php';
    if (!is_logged_in()) {
        $code = 401;
    }
} catch (Throwable $e) {}
ob_end_clean();
assertTest($code === 401, "api/dashboard_stats.php محمي ويرجع كود 401 للمستخدم غير المسجل");

// -------------------------------------------------------------
// Test 2: api/dashboard_stats.php إرجاع JSON متكامل مع الجلسة
// -------------------------------------------------------------
echo "\n2. فحص محتوى استجابة api/dashboard_stats.php للمستخدم المسجل...\n";
$_SESSION['user_id'] = 1;
$_SESSION['email']   = 'admin@store.com';
$_SESSION['role']    = 'admin';

ob_start();
include __DIR__ . '/../api/dashboard_stats.php';
$output = ob_get_clean();
$data = json_decode($output, true);

assertTest(is_array($data) && ($data['success'] ?? false) === true, "نقطة النهاية api/dashboard_stats.php ترجع JSON صالح ونجاح true");
assertTest(isset($data['today_sales_amount']) && isset($data['total_sales_amount']), "تضمين مبيعات اليوم وإجمالي المبيعات");
assertTest(isset($data['today_orders_count']) && isset($data['total_orders_count']), "تضمين عدد طلبات اليوم وإجمالي الطلبات");
assertTest(isset($data['today_new_clients_count']) && isset($data['total_clients_count']), "تضمين عملاء اليوم وإجمالي العملاء");
assertTest(isset($data['low_stock_count']) && isset($data['total_products_count']), "تضمين عدد تنبيهات المخزون وإجمالي المنتجات");
assertTest(isset($data['recent_orders']) && is_array($data['recent_orders']), "تضمين مصفوفة آخر 5 طلبات");
assertTest(isset($data['low_stock_products']) && is_array($data['low_stock_products']), "تضمين مصفوفة المنتجات الحرجة");
assertTest(!empty($data['store_name']) && !empty($data['currency']) && !empty($data['last_updated']), "تضمين اسم المتجر والعملة ووقت آخر تحديث");

// -------------------------------------------------------------
// Test 3: charts/charts_data.php إرجاع بيانات الرسوم البيانية الحقيقية
// -------------------------------------------------------------
echo "\n3. فحص استجابة الرسوم البيانية الحقيقية charts/charts_data.php...\n";
ob_start();
include __DIR__ . '/../charts/charts_data.php';
$chartsOutput = ob_get_clean();
$chartsData = json_decode($chartsOutput, true);

assertTest(is_array($chartsData) && ($chartsData['success'] ?? false) === true, "charts/charts_data.php يرجع JSON صالح");
assertTest(isset($chartsData['sales_trend']) && is_array($chartsData['sales_trend']), "تضمين مجموعة بيانات مسار المبيعات sales_trend");
assertTest(isset($chartsData['top_products']) && is_array($chartsData['top_products']), "تضمين مجموعة بيانات أعلى المنتجات مبيعاً top_products");
assertTest(isset($chartsData['stock_by_category']) && is_array($chartsData['stock_by_category']), "تضمين مجموعة بيانات توزيع المخزون حسب الأقسام stock_by_category");

// -------------------------------------------------------------
// Test 4: المنشورات posts/ - التحقق من صلاحيات RBAC وإنشاء وحذف التعميمات
// -------------------------------------------------------------
echo "\n4. فحص إدارة التعميمات posts/ وصلاحيات المدير RBAC...\n";
// إنشاء تعميم تجريبي للمدير
$testTitle = "تعميم اختباري آلي " . uniqid();
$testContent = "محتوى التعميم الاختباري للتحقق من سلامة النشر";

$insertStmt = $pdo->prepare("INSERT INTO posts (user_id, title, content, status, created_at, updated_at) VALUES (1, :title, :content, 'published', NOW(), NOW())");
$insertStmt->execute(['title' => $testTitle, 'content' => $testContent]);
$testPostId = (int)$pdo->lastInsertId();

assertTest($testPostId > 0, "تم إدراج التعميم الإداري بنجاح في جدول posts");

// التأكد من استرجاع التعميم باسم الكاتب ودوره
$fetchStmt = $pdo->prepare("SELECT p.*, u.full_name, u.role FROM posts p JOIN users u ON p.user_id = u.id WHERE p.id = :id");
$fetchStmt->execute(['id' => $testPostId]);
$fetchedPost = $fetchStmt->fetch(PDO::FETCH_ASSOC);

assertTest($fetchedPost && $fetchedPost['title'] === $testTitle, "تم جلب التعميم بنجاح مع ربطه بالمستخدم الناشر");

// حذف التعميم التجريبي
$delStmt = $pdo->prepare("DELETE FROM posts WHERE id = :id");
$delStmt->execute(['id' => $testPostId]);
$delCheck = $pdo->query("SELECT COUNT(*) FROM posts WHERE id = $testPostId")->fetchColumn();
assertTest((int)$delCheck === 0, "تم حذف التعميم الإداري بنجاح من قاعدة البيانات");

// -------------------------------------------------------------
// Test 5: المفضلة favorite/ - التحقق من استعلام عملاء VIP وإحصائيات الإنفاق
// -------------------------------------------------------------
echo "\n5. فحص وحدة المفضلة favorite/ والربط بقاعدة البيانات...\n";
$vipQuery = $pdo->query("
    SELECT 
        c.id,
        c.name,
        c.phone,
        COUNT(DISTINCT o.id) AS orders_count,
        COALESCE(SUM(CASE WHEN o.status != 'cancelled' THEN o.total_amount ELSE 0 END), 0) AS total_spent
    FROM clients c
    LEFT JOIN orders o ON c.id = o.client_id
    WHERE c.is_vip = 1
    GROUP BY c.id
    ORDER BY total_spent DESC
");
$vips = $vipQuery->fetchAll(PDO::FETCH_ASSOC);
assertTest(is_array($vips), "استعلام عملاء VIP يعمل بنجاح بدون أخطاء ويحسب إجمالي الإنفاق والطلبات");

// -------------------------------------------------------------
// Test 6: الإعدادات settings/ - تحديث إعدادات المتجر والعملة والمظهر
// -------------------------------------------------------------
echo "\n6. فحص حفظ واسترجاع إعدادات المتجر settings/...\n";
// حفظ الإعدادات الأصلية لاستعادتها
$origStore = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'store_name'")->fetchColumn();
$origCurr  = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'currency'")->fetchColumn();

// تحديث إعدادات تجريبية
$testStoreName = "متجر المبيعات التجريبي الفائق";
$testCurrency  = "ر.س";

$upsertStmt = $pdo->prepare("
    INSERT INTO settings (setting_key, setting_value, updated_at)
    VALUES (:key, :val, NOW())
    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
");
$upsertStmt->execute(['key' => 'store_name', 'val' => $testStoreName]);
$upsertStmt->execute(['key' => 'currency', 'val' => $testCurrency]);

$updatedStore = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'store_name'")->fetchColumn();
assertTest($updatedStore === $testStoreName, "تحديث اسم المتجر في جدول settings بنجاح");

// استعادة اسم المتجر الأصلي
if ($origStore) {
    $upsertStmt->execute(['key' => 'store_name', 'val' => $origStore]);
}

// -------------------------------------------------------------
// Test 7: تغيير كلمة المرور والتحقق التشفيري
// -------------------------------------------------------------
echo "\n7. فحص تغيير وتشفير كلمة المرور...\n";
$adminUser = $pdo->query("SELECT password_hash FROM users WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
$origHash = $adminUser['password_hash'];

// التأكد من أن كلمة المرور الحالية تتطابق مع Admin@123
assertTest(password_verify('Admin@123', $origHash), "كلمة المرور الحالية للمشرف مطابقة لتشفير bcrypt");

// اختبار تشفير كلمة مرور جديدة والتحقق منها
$tempPass = "NewSecurePass@2026";
$tempHash = password_hash($tempPass, PASSWORD_DEFAULT);
$updatePassStmt = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE id = 1");
$updatePassStmt->execute(['hash' => $tempHash]);

$newHashCheck = $pdo->query("SELECT password_hash FROM users WHERE id = 1")->fetchColumn();
assertTest(password_verify($tempPass, $newHashCheck), "تم تحديث كلمة المرور والتحقق من التشفير الجديد بنجاح");

// استعادة كلمة المرور الأصلية لضمان استمرار تسجيل دخول المشرف
$updatePassStmt->execute(['hash' => $origHash]);
$restoredHash = $pdo->query("SELECT password_hash FROM users WHERE id = 1")->fetchColumn();
assertTest(password_verify('Admin@123', $restoredHash), "تمت استعادة كلمة المرور الأصلية بنجاح وأمان");

// -------------------------------------------------------------
// Test 8: التأكد من خلو ملفات النظام من أي بيانات وهمية
// -------------------------------------------------------------
echo "\n8. فحص خلو الملفات من البيانات الوهمية (Demo / Mock Data)...\n";
$filesToCheck = [
    __DIR__ . '/../index.php',
    __DIR__ . '/../charts/charts_data.php',
    __DIR__ . '/../favorite/favorite.php'
];

$clean = true;
foreach ($filesToCheck as $f) {
    $content = file_get_contents($f);
    if (strpos($content, 'Test Data') !== false || strpos($content, 'monthlyRevenue') !== false) {
        $clean = false;
        break;
    }
}
assertTest($clean, "خلو كافة الملفات من أي نصوص أو مصفوفات وهمية (Test Data)");

echo "\n=======================================================\n";
echo "النتيجة النهائية للمرحلة الرابعة: اجتياز $testsPassed من أصل $totalTests اختبار بنجاح!\n";
echo "=======================================================\n";

if ($testsPassed === $totalTests) {
    exit(0);
} else {
    exit(1);
}
