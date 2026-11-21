<?php
/**
 * تحديث بيانات منتج في المخزون
 * Update Product (PDO + CSRF + RBAC + Unique SKU Check)
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
$id             = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$name           = trim($_POST['name'] ?? '');
$sku            = strtoupper(trim($_POST['sku'] ?? ''));
$category       = trim($_POST['category'] ?? '');
$priceRaw       = trim($_POST['price'] ?? '');
$countRaw       = trim($_POST['count'] ?? '');
$minAlertRaw    = trim($_POST['min_stock_alert'] ?? '');

if ($id <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'معرف المنتج غير صالح.'], JSON_UNESCAPED_UNICODE);
    exit();
}

if ($name === '' || $sku === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'يرجى إدخال اسم المنتج ورمز SKU.'], JSON_UNESCAPED_UNICODE);
    exit();
}

if (!is_numeric($priceRaw) || (float)$priceRaw < 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'يرجى إدخال سعر صحيح (رقم موجب).'], JSON_UNESCAPED_UNICODE);
    exit();
}

if (!is_numeric($countRaw) || (int)$countRaw < 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'يرجى إدخال كمية صحيحة في المخزن (صفر أو أكثر).'], JSON_UNESCAPED_UNICODE);
    exit();
}

$price          = round((float)$priceRaw, 2);
$count          = (int)$countRaw;
$minStockAlert  = (is_numeric($minAlertRaw) && (int)$minAlertRaw >= 0) ? (int)$minAlertRaw : 5;

// 5. التحديث في قاعدة البيانات عبر PDO
try {
    $pdo = get_db_connection();

    // التأكد من عدم تكرار SKU لمنتج آخر
    $checkStmt = $pdo->prepare("SELECT id FROM products WHERE sku = :sku AND id != :id LIMIT 1");
    $checkStmt->execute([':sku' => $sku, ':id' => $id]);
    if ($checkStmt->fetch()) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error'   => 'رمز SKU مسجل مسبقاً لمنتج آخر. يرجى اختيار رمز فريد.'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $stmt = $pdo->prepare("
        UPDATE products 
        SET name = :name, sku = :sku, category = :category, price = :price, count = :count, min_stock_alert = :min_stock_alert
        WHERE id = :id
    ");
    $stmt->execute([
        ':name'            => $name,
        ':sku'             => $sku,
        ':category'        => $category !== '' ? $category : null,
        ':price'           => $price,
        ':count'           => $count,
        ':min_stock_alert' => $minStockAlert,
        ':id'              => $id,
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'تم تحديث بيانات المنتج بنجاح.',
        'data'    => [
            'id'              => $id,
            'name'            => $name,
            'sku'             => $sku,
            'category'        => $category,
            'price'           => $price,
            'count'           => $count,
            'min_stock_alert' => $minStockAlert,
        ]
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    error_log('Error updating product: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'حدث خطأ داخلي أثناء تحديث بيانات المنتج.'], JSON_UNESCAPED_UNICODE);
}
