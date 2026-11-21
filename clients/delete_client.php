<?php
/**
 * حذف عميل (مخصص للمديرين فقط)
 * Delete Client (Admin Only + PDO + CSRF + POST)
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

// 4. استلام والتحقق من معرف العميل
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'معرف العميل غير صالح.'], JSON_UNESCAPED_UNICODE);
    exit();
}

// 5. الحذف بأمان عبر PDO Prepared Statement
try {
    $pdo = get_db_connection();

    // التحقق مما إذا كان العميل مرتبطاً بطلبات سابقة
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE client_id = :id");
    $checkStmt->execute([':id' => $id]);
    if ($checkStmt->fetchColumn() > 0) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error'   => 'لا يمكن حذف هذا العميل لوجود فواتير وطلبات مبيعات مسجلة باسمه في النظام.'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $stmt = $pdo->prepare("DELETE FROM clients WHERE id = :id");
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'تم حذف العميل بنجاح.'], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'العميل غير موجود أو تم حذفه مسبقاً.'], JSON_UNESCAPED_UNICODE);
    }
} catch (PDOException $e) {
    error_log('Error deleting client: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'حدث خطأ داخلي أثناء محاولة حذف العميل.'], JSON_UNESCAPED_UNICODE);
}
