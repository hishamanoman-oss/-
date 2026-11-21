<?php
/**
 * معالجة تحديث بيانات عميل قائم
 * Update Existing Client (PDO + CSRF + RBAC)
 */

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

// 1. التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'طريقة الطلب غير مسموح بها.'], JSON_UNESCAPED_UNICODE);
    exit();
}

// 2. التحقق من صلاحيات الدور (Admin أو Staff)
require_role(['admin', 'staff'], true);

// 3. التحقق من توكن الحماية ضد CSRF
$csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf_token($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'رمز الأمان (CSRF) غير صالح أو انتهت صلاحية الجلسة.'], JSON_UNESCAPED_UNICODE);
    exit();
}

// 4. استلام وفلترة البيانات
$id      = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$name    = trim($_POST['name'] ?? '');
$phone   = trim($_POST['phone'] ?? '');
$email   = trim($_POST['email'] ?? '');
$address = trim($_POST['address'] ?? '');
$is_vip  = !empty($_POST['is_vip']) ? 1 : 0;

if ($id <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'معرف العميل غير صالح.'], JSON_UNESCAPED_UNICODE);
    exit();
}

if ($name === '' || $phone === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'يرجى إدخال اسم العميل ورقم الهاتف.'], JSON_UNESCAPED_UNICODE);
    exit();
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'صيغة البريد الإلكتروني المدخلة غير صحيحة.'], JSON_UNESCAPED_UNICODE);
    exit();
}

// 5. تحديث البيانات عبر PDO
try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("
        UPDATE clients 
        SET name = :name, phone = :phone, email = :email, address = :address, is_vip = :is_vip
        WHERE id = :id
    ");
    $stmt->execute([
        ':name'    => $name,
        ':phone'   => $phone,
        ':email'   => $email !== '' ? $email : null,
        ':address' => $address !== '' ? $address : null,
        ':is_vip'  => $is_vip,
        ':id'      => $id,
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'تم تحديث بيانات العميل بنجاح.',
        'data'    => [
            'id'      => $id,
            'name'    => $name,
            'phone'   => $phone,
            'email'   => $email,
            'address' => $address,
            'is_vip'  => $is_vip,
        ]
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    error_log('Error updating client: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'حدث خطأ داخلي أثناء تحديث بيانات العميل.'], JSON_UNESCAPED_UNICODE);
}
