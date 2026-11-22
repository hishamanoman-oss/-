<?php
/**
 * تحديث حالة الطلب واسترجاع المخزون التلقائي عند الإلغاء
 * Order Status Manager & Automatic Stock Restoration (Admin Only + PDO Transaction)
 */

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// 1. حظر غير طريقة POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $_SESSION['flash_error'] = 'طريقة الطلب غير مسموح بها.';
    header('Location: orders.php');
    exit();
}

// 2. حصر الصلاحية على المشرف العام (Admin Only) - إرجاع 403 للموظف
require_role(['admin']);

// 3. التحقق من توكن الحماية ضد CSRF
$csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf_token($csrfToken)) {
    http_response_code(403);
    $_SESSION['flash_error'] = 'خطأ أمني: رمز الأمان (CSRF) غير صالح أو انتهت صلاحية الجلسة.';
    header('Location: orders.php');
    exit();
}

// 4. استلام والتحقق من المعطيات
$orderId   = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$newStatus = trim($_POST['new_status'] ?? '');

if ($orderId <= 0 || !in_array($newStatus, ['completed', 'cancelled'], true)) {
    $_SESSION['flash_error'] = 'بيانات تحديث حالة الطلب غير صالحة.';
    header('Location: orders.php');
    exit();
}

$pdo = get_db_connection();

// 5. تنفيذ المعاملة الذرية
try {
    $pdo->beginTransaction();

    // قفل سطر الطلب وفحص حالته الحالية
    $lockStmt = $pdo->prepare("SELECT id, order_number, status FROM orders WHERE id = :id FOR UPDATE");
    $lockStmt->execute([':id' => $orderId]);
    $order = $lockStmt->fetch();

    if (!$order) {
        throw new RuntimeException("الطلب المطلوب غير موجود في النظام.");
    }

    $currentStatus = $order['status'];

    // لا يمكن تعديل طلب ملغي مسبقاً
    if ($currentStatus === 'cancelled') {
        throw new RuntimeException("لا يمكن تعديل حالة طلب ملغي مسبقاً.");
    }

    // إذا لم تتغير الحالة
    if ($currentStatus === $newStatus) {
        $pdo->commit();
        $_SESSION['flash_success'] = "الطلب رقم ({$order['order_number']}) هو بالفعل بحالة '{$newStatus}'.";
        header('Location: orders.php');
        exit();
    }

    // إذا كان التحويل إلى "ملغي" (cancelled): استرجاع كميات جميع البنود إلى المخزون تلقائياً
    if ($newStatus === 'cancelled') {
        // جلب بنود الطلب
        $itemsStmt = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = :order_id");
        $itemsStmt->execute([':order_id' => $orderId]);
        $items = $itemsStmt->fetchAll();

        $restoreStockStmt = $pdo->prepare("
            UPDATE products 
            SET count = count + :qty 
            WHERE id = :product_id
        ");

        foreach ($items as $item) {
            $restoreStockStmt->execute([
                ':qty'        => (int)$item['quantity'],
                ':product_id' => (int)$item['product_id'],
            ]);
        }

        // تحديث حالة الطلب
        $updateStmt = $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = :id");
        $updateStmt->execute([':id' => $orderId]);

        $pdo->commit();
        $_SESSION['flash_success'] = "تم إلغاء الطلب رقم ({$order['order_number']}) بنجاح واسترجاع كميات كافة المنتجات إلى المخزن.";
        header('Location: orders.php');
        exit();
    }

    // إذا كان التحويل من "قيد الانتظار" إلى "مكتمل"
    if ($newStatus === 'completed') {
        $updateStmt = $pdo->prepare("UPDATE orders SET status = 'completed' WHERE id = :id");
        $updateStmt->execute([':id' => $orderId]);

        $pdo->commit();
        $_SESSION['flash_success'] = "تم إكمال وتأكيد الطلب رقم ({$order['order_number']}) بنجاح.";
        header('Location: orders.php');
        exit();
    }

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error updating order status: ' . $e->getMessage());
    $_SESSION['flash_error'] = $e->getMessage();
    header('Location: orders.php');
    exit();
}
