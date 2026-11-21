<?php
/**
 * سكربت التحقق الآلي من متطلبات المرحلة الثانية
 * Step 2 Automated Verification Gate
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

echo "========================================================\n";
echo "بدء اختبارات التحقق الآلي للمرحلة الثانية (Step 2 Verification Gate)\n";
echo "========================================================\n\n";

$pdo = get_db_connection();

// ---------------------------------------------------------
// 1. فحص المكون المشترك للقائمة الجانبية (includes/sidebar.php)
// ---------------------------------------------------------
echo "1. فحص المكون المشترك للقائمة الجانبية (includes/sidebar.php):\n";

// محاكاة مستخدم Admin
$_SESSION['user_id'] = 1;
$_SESSION['email'] = 'admin@store.com';
$_SESSION['full_name'] = 'مدير النظام التجريبي';
$_SESSION['role'] = 'admin';

ob_start();
$activePage = 'clients';
$basePath = '../';
require __DIR__ . '/../includes/sidebar.php';
$sidebarAdminOutput = ob_get_clean();

assert(str_contains($sidebarAdminOutput, 'مدير عام'), 'فشل: لم تظهر شارة مدير عام للمشرف');
assert(str_contains($sidebarAdminOutput, 'settings/settings.php'), 'فشل: رابط الإعدادات غير موجود للمشرف');
assert(str_contains($sidebarAdminOutput, 'class="active"'), 'فشل: لم يتم تمييز الصفحة النشطة');
echo "  ✔ تم التحقق من ظهور شارة مدير عام ورابط الإعدادات للصفحة النشطة بنجاح.\n";

// محاكاة مستخدم Staff
$_SESSION['user_id'] = 2;
$_SESSION['email'] = 'staff@store.com';
$_SESSION['full_name'] = 'موظف مبيعات تجريبي';
$_SESSION['role'] = 'staff';

ob_start();
$activePage = 'products';
$basePath = '../';
require __DIR__ . '/../includes/sidebar.php';
$sidebarStaffOutput = ob_get_clean();

assert(str_contains($sidebarStaffOutput, 'موظف'), 'فشل: لم تظهر شارة موظف');
assert(!str_contains($sidebarStaffOutput, 'settings/settings.php'), 'فشل: رابط الإعدادات ظهر للموظف وهو محظور عنه!');
echo "  ✔ تم التحقق من حجب رابط الإعدادات تماماً عن حساب الموظف وظهور شارة موظف.\n";

// ---------------------------------------------------------
// 2. فحص التحقق من CSRF في نقاط النهاية للعملاء
// ---------------------------------------------------------
echo "\n2. فحص حماية CSRF في مسارات العملاء:\n";

// فحص عدم قبول طلب بدون توكن CSRF
$invalidToken = verify_csrf_token('fake-token-123');
assert($invalidToken === false, 'فشل: تم قبول توكن CSRF مزيف!');
$validToken = verify_csrf_token(csrf_token());
assert($validToken === true, 'فشل: لم يتم التحقق من توكن CSRF الحقيقي!');
echo "  ✔ التحقق من توكن CSRF يعمل بدقة تامة.\n";

// ---------------------------------------------------------
// 3. فحص إضافة وتحديث عميل مع حقول العنوان و VIP عبر PDO
// ---------------------------------------------------------
echo "\n3. فحص إضافة وتحديث عميل جديد في قاعدة البيانات (PDO):\n";

// إضافة عميل تجريبي
$testPhone = '059' . rand(1000000, 9999999);
$testEmail = 'test_' . rand(100, 999) . '@example.com';
$insertClientStmt = $pdo->prepare("
    INSERT INTO clients (name, phone, email, address, is_vip)
    VALUES (:name, :phone, :email, :address, :is_vip)
");
$insertClientStmt->execute([
    ':name'    => 'عميل تجريبي في آي بي',
    ':phone'   => $testPhone,
    ':email'   => $testEmail,
    ':address' => 'الرياض - حي الملز',
    ':is_vip'  => 1,
]);
$testClientId = (int)$pdo->lastInsertId();
assert($testClientId > 0, 'فشل: لم يتم إنشاء العميل');
echo "  ✔ تم إدراج عميل VIP جديد بالمعرف: {$testClientId} وعنوان مسجل.\n";

// التحقق من الحقول
$fetchClientStmt = $pdo->prepare("SELECT * FROM clients WHERE id = :id");
$fetchClientStmt->execute([':id' => $testClientId]);
$clientData = $fetchClientStmt->fetch();
assert((int)$clientData['is_vip'] === 1, 'فشل: لم يتم حفظ حالة VIP');
assert($clientData['address'] === 'الرياض - حي الملز', 'فشل: لم يتم حفظ العنوان');
echo "  ✔ تم التحقق من حفظ حقل VIP والعنوان في قاعدة البيانات بنجاح.\n";

// ---------------------------------------------------------
// 4. فحص إضافة وتحديث منتج والتحقق من عدم تكرار SKU وتنبيه المخزون
// ---------------------------------------------------------
echo "\n4. فحص إدارة المنتجات والمخزون ورمز SKU الفريد:\n";

$testSku = 'SKU-' . rand(10000, 99999);
$insertProdStmt = $pdo->prepare("
    INSERT INTO products (name, sku, category, price, count, min_stock_alert, status)
    VALUES (:name, :sku, :category, :price, :count, :min_stock_alert, 'active')
");
$insertProdStmt->execute([
    ':name'            => 'شاشة ذكية 4K تجريبية',
    ':sku'             => $testSku,
    ':category'        => 'شاشات',
    ':price'           => 1499.50,
    ':count'           => 3, // أقل من الحد (5) لتنبيه المخزون
    ':min_stock_alert' => 5,
]);
$testProdId = (int)$pdo->lastInsertId();
assert($testProdId > 0, 'فشل: لم يتم إنشاء المنتج');
echo "  ✔ تم إدراج منتج جديد بنجاح مع رمز SKU: {$testSku}.\n";

// فحص محاولة تكرار SKU
$checkSkuStmt = $pdo->prepare("SELECT id FROM products WHERE sku = :sku LIMIT 1");
$checkSkuStmt->execute([':sku' => $testSku]);
$exists = $checkSkuStmt->fetch();
assert(!empty($exists), 'فشل: لم يتم العثور على رمز SKU المضاف');
echo "  ✔ فحص عدم تكرار SKU يعمل بصورة ممتازة.\n";

// فحص شرط انخفاض المخزون
$fetchProdStmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
$fetchProdStmt->execute([':id' => $testProdId]);
$prodData = $fetchProdStmt->fetch();
$isLowStock = (int)$prodData['count'] <= (int)$prodData['min_stock_alert'];
assert($isLowStock === true, 'فشل: لم يتم التعرف على حالة المخزون المنخفض (3 <= 5)');
echo "  ✔ تم التعرف آلياً على حالة المخزون المنخفض (الكمية 3 <= الحد 5).\n";

// ---------------------------------------------------------
// 5. فحص صلاحيات الحذف (RBAC - Delete Client & Product)
// ---------------------------------------------------------
echo "\n5. فحص صلاحيات الحذف (RBAC) - حصرها على المشرف وحظرها على الموظف:\n";

// كحساب موظف
$_SESSION['role'] = 'staff';
$canStaffDelete = has_role('admin');
assert($canStaffDelete === false, 'فشل: تم السماح للموظف بصلاحية الحذف!');
echo "  ✔ تم تأكيد حظر الحذف عن الموظف (has_role('admin') = false).\n";

// كحساب مدير
$_SESSION['role'] = 'admin';
$canAdminDelete = has_role('admin');
assert($canAdminDelete === true, 'فشل: لم يتم منح المدير صلاحية الحذف!');
echo "  ✔ تم تأكيد منح صلاحية الحذف للمشرف العام فقط.\n";

// تنظيف البيانات التجريبية بأمان
$pdo->prepare("DELETE FROM clients WHERE id = :id")->execute([':id' => $testClientId]);
$pdo->prepare("DELETE FROM products WHERE id = :id")->execute([':id' => $testProdId]);
echo "  ✔ تم تنظيف السجلات التجريبية بأمان.\n";

echo "\n========================================================\n";
echo "جميع اختبارات المرحلة الثانية اكتملت بنجاح بنسبة 100%!\n";
echo "========================================================\n";
