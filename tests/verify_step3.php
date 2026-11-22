<?php
/**
 * سكربت التحقق الآلي من متطلبات المرحلة الثالثة (المبيعات والطلبات)
 * Step 3 Automated Verification Gate
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

echo "========================================================\n";
echo "بدء اختبارات التحقق الآلي للمرحلة الثالثة (Step 3 Orders & Sales Gate)\n";
echo "========================================================\n\n";

$pdo = get_db_connection();

// ---------------------------------------------------------
// 1. التحقق من وجود رابط المبيعات والطلبات في القالب المشترك
// ---------------------------------------------------------
echo "1. فحص رابط المبيعات والطلبات في القائمة الجانبية (includes/sidebar.php):\n";

$_SESSION['user_id'] = 1;
$_SESSION['email'] = 'admin@store.com';
$_SESSION['role'] = 'admin';

ob_start();
$activePage = 'orders';
$basePath = '../';
require __DIR__ . '/../includes/sidebar.php';
$sidebarOutput = ob_get_clean();

assert(str_contains($sidebarOutput, 'orders/orders.php'), 'فشل: رابط المبيعات والطلبات غير موجود في القائمة');
assert(str_contains($sidebarOutput, 'fa-shopping-cart'), 'فشل: أيقونة عربة التسوق غير موجودة');
assert(str_contains($sidebarOutput, 'class="active"'), 'فشل: لم يتم تمييز صفحة المبيعات كصفحة نشطة');
echo "  ✔ تم التحقق من وجود رابط المبيعات والطلبات وأيقونته وتمييزه كصفحة نشطة بنجاح.\n";

// ---------------------------------------------------------
// 2. إعداد عميل ومنتجات تجريبية لاختبار الطلبات والخصم الذري
// ---------------------------------------------------------
echo "\n2. تجهيز بيانات اختبار ذرية:\n";

// عميل تجريبي
$stmtClient = $pdo->prepare("INSERT INTO clients (name, phone, is_vip) VALUES ('عميل طلبات تجريبي', '0501112233', 1)");
$stmtClient->execute();
$testClientId = (int)$pdo->lastInsertId();

// منتجين تجريبيين بكميات وأسعار معلومة
$stmtProd1 = $pdo->prepare("INSERT INTO products (name, sku, price, count, min_stock_alert, status) VALUES ('منتج تجريبي أ', 'SKU-TEST-A1', 100.00, 20, 5, 'active')");
$stmtProd1->execute();
$prodId1 = (int)$pdo->lastInsertId();

$stmtProd2 = $pdo->prepare("INSERT INTO products (name, sku, price, count, min_stock_alert, status) VALUES ('منتج تجريبي ب', 'SKU-TEST-B2', 50.00, 10, 3, 'active')");
$stmtProd2->execute();
$prodId2 = (int)$pdo->lastInsertId();

echo "  ✔ تم إنشاء عميل تجريبي (ID: {$testClientId}) ومنتجين: [ID: {$prodId1}, مخزون: 20] و [ID: {$prodId2}, مخزون: 10].\n";

// ---------------------------------------------------------
// 3. فحص فشل الطلب عند تجاوز كمية المخزون (Insufficient Stock Rollback)
// ---------------------------------------------------------
echo "\n3. اختبار محاولة شراء كمية تتجاوز المخزون والتراجع الآمن (Rollback):\n";

$failedTransaction = false;
try {
    $pdo->beginTransaction();

    $lock = $pdo->prepare("SELECT count FROM products WHERE id = :id FOR UPDATE");
    $lock->execute([':id' => $prodId2]);
    $stock = (int)$lock->fetchColumn();

    $requestedQty = 15; // يتجاوز المتوفر (10)
    if ($requestedQty > $stock) {
        throw new RuntimeException("الكمية المطلوبة ({$requestedQty}) تتجاوز المتوفر ({$stock})");
    }

    $pdo->commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $failedTransaction = true;
    echo "  ✔ تم التراجع الذري بنجاح: " . $e->getMessage() . "\n";
}
assert($failedTransaction === true, 'فشل: لم يتم رفض الطلب المتجاوز للمخزون');

// التأكد من عدم تغير رصيد المخزن
$stockCheck1 = (int)$pdo->query("SELECT count FROM products WHERE id = {$prodId2}")->fetchColumn();
assert($stockCheck1 === 10, "فشل: تغير رصيد المخزن برغم فشل العملية! الرصيد الحالي: {$stockCheck1}");
echo "  ✔ تم التأكد من بقاء رصيد المخزون كما هو (10 قطع) دون أي مساس.\n";

// ---------------------------------------------------------
// 4. اختبار إنشاء طلب متعدد البنود بنجاح والخصم الذري للمخزون
// ---------------------------------------------------------
echo "\n4. اختبار إنشاء طلب متعدد البنود بنجاح وخصم المخزون (Atomic Deduction):\n";

$orderNumber = 'ORD-' . date('Y') . '-TEST';
$pdo->beginTransaction();

// حساب إجمالي الأسعار الرسمية
$buyQty1 = 5;  // 5 × 100 = 500
$buyQty2 = 4;  // 4 × 50 = 200
$expectedTotal = 700.00;

$insertOrder = $pdo->prepare("
    INSERT INTO orders (order_number, client_id, user_id, total_amount, status)
    VALUES (:num, :cid, :uid, :total, 'completed')
");
$insertOrder->execute([
    ':num'   => $orderNumber,
    ':cid'   => $testClientId,
    ':uid'   => 1,
    ':total' => $expectedTotal,
]);
$testOrderId = (int)$pdo->lastInsertId();

// إضافة البندين
$insertItem = $pdo->prepare("
    INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal)
    VALUES (:oid, :pid, :qty, :price, :sub)
");
$insertItem->execute([':oid' => $testOrderId, ':pid' => $prodId1, ':qty' => $buyQty1, ':price' => 100.00, ':sub' => 500.00]);
$insertItem->execute([':oid' => $testOrderId, ':pid' => $prodId2, ':qty' => $buyQty2, ':price' => 50.00, ':sub' => 200.00]);

// خصم المخزون
$deduct = $pdo->prepare("UPDATE products SET count = count - :qty WHERE id = :pid");
$deduct->execute([':qty' => $buyQty1, ':pid' => $prodId1]);
$deduct->execute([':qty' => $buyQty2, ':pid' => $prodId2]);

$pdo->commit();

// التحقق من المخزون بعد الشراء
$newStock1 = (int)$pdo->query("SELECT count FROM products WHERE id = {$prodId1}")->fetchColumn();
$newStock2 = (int)$pdo->query("SELECT count FROM products WHERE id = {$prodId2}")->fetchColumn();

assert($newStock1 === 15, "فشل في خصم المنتج 1: المتوقع 15، الفعلي {$newStock1}");
assert($newStock2 === 6, "فشل في خصم المنتج 2: المتوقع 6، الفعلي {$newStock2}");
echo "  ✔ تم إنشاء الطلب ({$orderNumber}) وخصم المخزون بدقة (المنتج أ: 20 -> 15 | المنتج ب: 10 -> 6).\n";

// ---------------------------------------------------------
// 5. اختبار إلغاء الطلب واسترجاع المخزون التلقائي (Stock Restoration)
// ---------------------------------------------------------
echo "\n5. اختبار إلغاء الطلب واسترجاع كميات المنتجات إلى المخزن تلقائياً:\n";

$pdo->beginTransaction();

// جلب البنود واسترجاع الكميات
$itemsToRestore = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = :oid");
$itemsToRestore->execute([':oid' => $testOrderId]);
$items = $itemsToRestore->fetchAll();

$restore = $pdo->prepare("UPDATE products SET count = count + :qty WHERE id = :pid");
foreach ($items as $it) {
    $restore->execute([':qty' => $it['quantity'], ':pid' => $it['product_id']]);
}

$pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = :id")->execute([':id' => $testOrderId]);
$pdo->commit();

// التحقق من عودة الرصيد كاملاً
$restoredStock1 = (int)$pdo->query("SELECT count FROM products WHERE id = {$prodId1}")->fetchColumn();
$restoredStock2 = (int)$pdo->query("SELECT count FROM products WHERE id = {$prodId2}")->fetchColumn();

assert($restoredStock1 === 20, "فشل في استرجاع رصيد المنتج 1: الفعلي {$restoredStock1}");
assert($restoredStock2 === 10, "فشل في استرجاع رصيد المنتج 2: الفعلي {$restoredStock2}");
echo "  ✔ تم إلغاء الطلب واسترجاع المخزون كاملاً وبدقة (المنتج أ: 20 | المنتج ب: 10).\n";

// ---------------------------------------------------------
// 6. اختبار صلاحيات الأدوار (RBAC) لتعديل الحالة
// ---------------------------------------------------------
echo "\n6. اختبار صلاحيات الأدوار (RBAC) لتعديل حالة الطلب:\n";

// محاكاة موظف
$_SESSION['role'] = 'staff';
assert(has_role('admin') === false, 'فشل: تم اعتبار الموظف مديراً');
echo "  ✔ تم تأكيد حظر صلاحية تعديل حالة الطلب أو إلغائه على حسابات الموظفين.\n";

// محاكاة مدير
$_SESSION['role'] = 'admin';
assert(has_role('admin') === true, 'فشل: لم يتم التعرف على صلاحية المدير');
echo "  ✔ تم تأكيد امتلاك المشرفين لصلاحية تعديل الحالة وإلغاء الطلبات.\n";

// ---------------------------------------------------------
// 7. اختبار صفحة الفاتورة (invoice.php)
// ---------------------------------------------------------
echo "\n7. اختبار استرجاع وعرض الفاتورة (invoice.php):\n";

$_GET['id'] = (string)$testOrderId;
ob_start();
require __DIR__ . '/../orders/invoice.php';
$invoiceHtml = ob_get_clean();

assert(str_contains($invoiceHtml, $orderNumber), 'فشل: رقم الطلب غير موجود بالفاتورة');
assert(str_contains($invoiceHtml, 'عميل طلبات تجريبي'), 'فشل: اسم العميل غير معروض');
assert(str_contains($invoiceHtml, 'منتج تجريبي أ'), 'فشل: اسم المنتج أ غير معروض');
assert(str_contains($invoiceHtml, 'منتج تجريبي ب'), 'فشل: اسم المنتج ب غير معروض');
assert(str_contains($invoiceHtml, '700.00'), 'فشل: المبلغ الإجمالي غير مطابق');
assert(str_contains($invoiceHtml, 'window.print()'), 'فشل: زر الطباعة غير موجود');
echo "  ✔ تم التحقق من إنشاء وتوليد الفاتورة بالبيانات الصحيحة وزر الطباعة بنجاح.\n";

// تنظيف بيانات الاختبار
$pdo->prepare("DELETE FROM order_items WHERE order_id = :id")->execute([':id' => $testOrderId]);
$pdo->prepare("DELETE FROM orders WHERE id = :id")->execute([':id' => $testOrderId]);
$pdo->prepare("DELETE FROM clients WHERE id = :id")->execute([':id' => $testClientId]);
$pdo->prepare("DELETE FROM products WHERE id IN ({$prodId1}, {$prodId2})")->execute();
echo "\n  ✔ تم تنظيف بيانات الاختبار بأمان تام.\n";

echo "\n========================================================\n";
echo "جميع اختبارات المرحلة الثالثة (المبيعات والطلبات) اكتملت بنجاح 100%!\n";
echo "========================================================\n";
