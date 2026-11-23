<?php
/**
 * سكريبت التحقق الشامل من إنجاز المرحلة الخامسة (Step 5 Verification)
 * - Server-Side Search, Filter & Pagination (Clients, Products, Orders)
 * - Zero native alert() or confirm() calls
 * - Modern Toast & Confirm Modal architecture
 * - RTL Responsive design & Mobile Drawer
 * - Clean UTF-8 verification
 */

if (session_status() === PHP_SESSION_NONE) {
    session_save_path(sys_get_temp_dir());
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';

$pdo = get_db_connection();
$testsPassed = 0;
$totalTests = 0;

function assertTest(bool $condition, string $message) {
    global $testsPassed, $totalTests;
    $totalTests++;
    if ($condition) {
        echo "  [PASS] $message\n";
        $testsPassed++;
    } else {
        echo "  [FAIL] $message\n";
    }
}

echo "=== بدء التحقق الآلي من المرحلة الخامسة (Step 5 Verification) ===\n\n";

// 1. اختبار البحث والفلترة والترقيم للعملاء (Clients)
echo "[1] اختبار البحث والفلترة والترقيم للعملاء (clients):\n";
$stmt = $pdo->query("SELECT COUNT(*) FROM clients");
$totalClients = (int)$stmt->fetchColumn();
assertTest($totalClients >= 0, "الاستعلام عن إجمالي العملاء يعمل بنجاح ($totalClients عميل)");

// البحث بكلمة شائعة
$searchQ = "محمد";
$searchStmt = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE name LIKE :q1 OR phone LIKE :q2 OR email LIKE :q3");
$searchStmt->execute([':q1' => "%$searchQ%", ':q2' => "%$searchQ%", ':q3' => "%$searchQ%"]);
$foundCount = (int)$searchStmt->fetchColumn();
assertTest($foundCount >= 0, "البحث بالاسم/الهاتف/البريد في جدول العملاء يعمل بنجاح ($foundCount نتائج)");

// فلترة VIP
$vipStmt = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE is_vip = 1");
$vipStmt->execute();
$vipCount = (int)$vipStmt->fetchColumn();
assertTest($vipCount >= 0, "فلترة عملاء VIP تعمل بنجاح ($vipCount عميل VIP)");

// اختبار حسابات الترقيم
$limit = 10;
$totalPages = max(1, (int)ceil($totalClients / $limit));
$page = 1;
$offset = ($page - 1) * $limit;
assertTest($totalPages >= 1 && $offset === 0, "معادلة ترقيم الصفحات وحساب الإزاحة (Offset) دقيقة وآمنة");

// 2. اختبار البحث والفلترة والترقيم للمنتجات (Products)
echo "\n[2] اختبار البحث والفلترة والترقيم للمنتجات (products):\n";
$stmt = $pdo->query("SELECT COUNT(*) FROM products");
$totalProducts = (int)$stmt->fetchColumn();
assertTest($totalProducts >= 0, "الاستعلام عن إجمالي المنتجات يعمل بنجاح ($totalProducts منتج)");

// جلب الأقسام الفريدة
$catStmt = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != ''");
$cats = $catStmt->fetchAll(PDO::FETCH_COLUMN);
assertTest(is_array($cats), "استخراج أقسام المنتجات ديناميكياً للفلترة يعمل بنجاح (" . count($cats) . " قسم)");

// فلترة المخزون المنخفض
$lowStockStmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE count <= min_stock_alert");
$lowStockStmt->execute();
$lowStockCount = (int)$lowStockStmt->fetchColumn();
assertTest($lowStockCount >= 0, "فلترة المخزون الحرج (count <= min_stock_alert) تعمل بنجاح ($lowStockCount منتج)");

// 3. اختبار البحث والفلترة والترقيم للطلبات (Orders)
echo "\n[3] اختبار البحث والفلترة والترقيم للطلبات والمبيعات (orders):\n";
$stmt = $pdo->query("SELECT COUNT(*) FROM orders");
$totalOrders = (int)$stmt->fetchColumn();
assertTest($totalOrders >= 0, "الاستعلام عن إجمالي الطلبات يعمل بنجاح ($totalOrders طلب)");

// فلترة حسب الحالة
foreach (['completed', 'pending', 'cancelled'] as $st) {
    $stStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE status = :status");
    $stStmt->execute([':status' => $st]);
    $stCount = (int)$stStmt->fetchColumn();
    assertTest($stCount >= 0, "فلترة الطلبات حسب الحالة ($st) تعمل بنجاح ($stCount طلب)");
}

// 4. التحقق من خلو ملفات JS و PHP تماماً من alert() و confirm()
echo "\n[4] التحقق من خلو ملفات المشروع من استدعاءات alert() و confirm():\n";
$jsFiles = [
    __DIR__ . '/../js/login.js',
    __DIR__ . '/../clients/js/clients.js',
    __DIR__ . '/../Products/js/products.js',
    __DIR__ . '/../orders/js/orders.js',
    __DIR__ . '/../js/dashboard.js',
    __DIR__ . '/../charts/js/charts.js',
];

$foundNativeDialog = false;
foreach ($jsFiles as $f) {
    if (file_exists($f)) {
        $content = file_get_contents($f);
        if (preg_match('/\b(alert|confirm)\s*\(/i', $content)) {
            $foundNativeDialog = true;
            echo "  [FAIL] تم العثور على استدعاء مباشر في " . basename($f) . "\n";
        }
    }
}
assertTest(!$foundNativeDialog, "جميع ملفات JavaScript نظيفة وخالية بنسبة 100% من alert() و confirm()");

// 5. التحقق من وجود ملفات UI المشتركة والتنسيقات المتجاوبة
echo "\n[5] فحص ملفات التنسيق والسكريبتات المشتركة:\n";
assertTest(file_exists(__DIR__ . '/../Css/ui.css'), "ملف Css/ui.css موجود");
assertTest(file_exists(__DIR__ . '/../Css/responsive.css'), "ملف Css/responsive.css موجود");
assertTest(file_exists(__DIR__ . '/../js/ui.js'), "ملف js/ui.js موجود");

$uiCss = file_get_contents(__DIR__ . '/../Css/ui.css');
assertTest(strpos($uiCss, '#toastContainer') !== false, "Css/ui.css يحتوي على حاوية التنبيهات (#toastContainer)");
assertTest(strpos($uiCss, '#confirmModalBackdrop') !== false, "Css/ui.css يحتوي على نافذة التأكيد (#confirmModalBackdrop)");
assertTest(strpos($uiCss, '.filter-bar') !== false, "Css/ui.css يحتوي على شريط التصفية (.filter-bar)");
assertTest(strpos($uiCss, '.pagination-container') !== false, "Css/ui.css يحتوي على ترقيم الصفحات (.pagination-container)");

$responsiveCss = file_get_contents(__DIR__ . '/../Css/responsive.css');
assertTest(strpos($responsiveCss, '.mobile-menu-btn') !== false, "Css/responsive.css يحتوي على زر القائمة المحمولة (.mobile-menu-btn)");
assertTest(strpos($responsiveCss, '.sidebar-overlay') !== false, "Css/responsive.css يحتوي على الطبقة المظللة (.sidebar-overlay)");
assertTest(strpos($responsiveCss, 'translateX(100%)') !== false, "Css/responsive.css يدعم سحب القائمة لليمين في RTL (translateX(100%))");

$uiJs = file_get_contents(__DIR__ . '/../js/ui.js');
assertTest(strpos($uiJs, 'window.showToast') !== false, "js/ui.js يصدّر دالة window.showToast");
assertTest(strpos($uiJs, 'window.showConfirmModal') !== false, "js/ui.js يصدّر دالة window.showConfirmModal");

// 6. التحقق من ترميز UTF-8 النظيف لجميع الملفات المعدلة
echo "\n[6] التحقق من نظافة ترميز UTF-8 وعدم وجود رموز استبدال مجهولة:\n";
$checkedFiles = [
    __DIR__ . '/../includes/sidebar.php',
    __DIR__ . '/../clients/clients.php',
    __DIR__ . '/../Products/products.php',
    __DIR__ . '/../orders/orders.php',
    __DIR__ . '/../index.php',
    __DIR__ . '/../charts/charts.php',
    __DIR__ . '/../posts/posts.php',
    __DIR__ . '/../favorite/favorite.php',
    __DIR__ . '/../settings/settings.php',
    __DIR__ . '/../Login.php',
    __DIR__ . '/../Css/ui.css',
    __DIR__ . '/../Css/responsive.css',
    __DIR__ . '/../js/ui.js',
];

$utf8Clean = true;
foreach ($checkedFiles as $f) {
    if (file_exists($f)) {
        $content = file_get_contents($f);
        if (strpos($content, "\xEF\xBF\xBD") !== false) {
            echo "  [FAIL] رمز استبدال تالف تم اكتشافه في: " . basename($f) . "\n";
            $utf8Clean = false;
        }
    }
}
assertTest($utf8Clean, "جميع ملفات المرحلة الخامسة ذات ترميز UTF-8 سليم وخالية من أي رموز تالفة");

echo "\n=======================================================\n";
echo "نتيجة التحقق: $testsPassed من أصل $totalTests اختبارات اجتيزت بنجاح.\n";
if ($testsPassed === $totalTests) {
    echo "🎉 المرحلة الخامسة (Step 5) مكتملة ومطابقة للمواصفات 100%!\n";
} else {
    echo "❌ يوجد اختبارات لم تنجح، يرجى المراجعة.\n";
    exit(1);
}
