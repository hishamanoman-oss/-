<?php
/**
 * حذف منتج من المخزون (مخصص للمديرين فقط)
 * Delete Product (Admin Only + PDO + CSRF + POST)
 */

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

// 1. حظر الحذف بغير طريقة POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'طريقة الطلب غير مسموح بها. لا يمكن الحذف عبر الرابط المباشر.'], JSON_UNESCAPED_UNICODE);
    exit();
}

// 2. حصر الصلاحية على المشرف / المدير (Admin Only)
require_role(['admin'], true);

// 3. التحقق من توكن الحماية ضد CSRF
$csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf_token($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'رمز الأمان (CSRF) غير صالح أو انتهت صلاحية الجلسة.'], JSON_UNESCAPED_UNICODE);
    exit();
}

// 4. استلام والتحقق من معرف المنتج
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'معرف المنتج غير صالح.'], JSON_UNESCAPED_UNICODE);
    exit();
}

// 5. الحذف بأمان عبر PDO Prepared Statement
try {
    $pdo = get_db_connection();

    // التحقق مما إذا كان المنتج مرتبطاً ببنود طلبات مبيعات سابقة
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = :id");
    $checkStmt->execute([':id' => $id]);
    if ($checkStmt->fetchColumn() > 0) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error'   => 'لا يمكن حذف هذا المنتج لوجوده ضمن بنود طلبات وفواتير مبيعات سابقة.'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $stmt = $pdo->prepare("DELETE FROM products WHERE id = :id");
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'تم حذف المنتج من المخزون بنجاح.'], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'المنتج غير موجود أو تم حذفه مسبقاً.'], JSON_UNESCAPED_UNICODE);
    }
} catch (PDOException $e) {
    error_log('Error deleting product: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'حدث خطأ داخلي أثناء محاولة حذف المنتج.'], JSON_UNESCAPED_UNICODE);
}
