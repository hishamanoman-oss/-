<?php
/**
 * إضافة منتج جديد إلى المخزون
 * Add New Product (PDO + CSRF + RBAC + Unique SKU Check)
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
$name           = trim($_POST['name'] ?? '');
$sku            = strtoupper(trim($_POST['sku'] ?? ''));
$category       = trim($_POST['category'] ?? '');
$priceRaw       = trim($_POST['price'] ?? '');
$countRaw       = trim($_POST['count'] ?? '');
$minAlertRaw    = trim($_POST['min_stock_alert'] ?? '');

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

// 5. الحفظ في قاعدة البيانات عبر PDO مع التحقق من عدم تكرار SKU
try {
    $pdo = get_db_connection();

    // فحص تكرار رمز SKU
    $checkStmt = $pdo->prepare("SELECT id FROM products WHERE sku = :sku LIMIT 1");
    $checkStmt->execute([':sku' => $sku]);
    if ($checkStmt->fetch()) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error'   => 'رمز SKU مسجل مسبقاً لمنتج آخر. يرجى اختيار رمز فريد.'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $stmt = $pdo->prepare("
        INSERT INTO products (name, sku, category, price, count, min_stock_alert, status)
        VALUES (:name, :sku, :category, :price, :count, :min_stock_alert, 'active')
    ");
    $stmt->execute([
        ':name'            => $name,
        ':sku'             => $sku,
        ':category'        => $category !== '' ? $category : null,
        ':price'           => $price,
        ':count'           => $count,
        ':min_stock_alert' => $minStockAlert,
    ]);

    $newId = (int)$pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => 'تمت إضافة المنتج بنجاح إلى المخزون.',
        'data'    => [
            'id'              => $newId,
            'name'            => $name,
            'sku'             => $sku,
            'category'        => $category,
            'price'           => $price,
            'count'           => $count,
            'min_stock_alert' => $minStockAlert,
        ]
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    error_log('Error adding product: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'حدث خطأ داخلي أثناء إضافة المنتج.'], JSON_UNESCAPED_UNICODE);
}
