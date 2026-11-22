<?php
/**
 * معالج إنشاء الطلبات والمبيعات (معاملات ذرية وخصم المخزون)
 * Atomic Order Creation Processor (PDO Transactions + Stock Deduction + CSRF)
 */

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// 1. التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $_SESSION['flash_error'] = 'طريقة الطلب غير مسموح بها.';
    header('Location: orders.php');
    exit();
}

// 2. التحقق من صلاحيات الدور (Admin أو Staff)
require_role(['admin', 'staff']);

// 3. التحقق من توكن الحماية ضد CSRF
$csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf_token($csrfToken)) {
    http_response_code(403);
    $_SESSION['flash_error'] = 'خطأ أمني: رمز الأمان (CSRF) غير صالح أو انتهت صلاحية الجلسة.';
    header('Location: orders.php');
    exit();
}

// 4. استلام والتحقق من الحقول الأساسية
$clientId = isset($_POST['client_id']) ? (int)$_POST['client_id'] : 0;
$status   = trim($_POST['status'] ?? 'completed');
$status   = in_array($status, ['completed', 'pending'], true) ? $status : 'completed';
$rawItems = $_POST['items'] ?? [];

if ($clientId <= 0) {
    $_SESSION['flash_error'] = 'يرجى اختيار العميل المسجل له الطلب.';
    header('Location: orders.php');
    exit();
}

if (!is_array($rawItems) || empty($rawItems)) {
    $_SESSION['flash_error'] = 'يرجى إضافة منتج واحد على الأقل للطلب.';
    header('Location: orders.php');
    exit();
}

// تجميع كميات المنتجات المتكررة لمنع التحايل
$aggregatedItems = []; // [product_id => quantity]
foreach ($rawItems as $item) {
    if (!isset($item['product_id']) || empty($item['product_id'])) {
        continue;
    }
    $pId = (int)$item['product_id'];
    $qty = isset($item['quantity']) ? (int)$item['quantity'] : 0;

    if ($pId > 0 && $qty > 0) {
        $aggregatedItems[$pId] = ($aggregatedItems[$pId] ?? 0) + $qty;
    }
}

if (empty($aggregatedItems)) {
    $_SESSION['flash_error'] = 'الطلب لا يحتوي على أي بنود منتجات صالحة بكميات مقبولة.';
    header('Location: orders.php');
    exit();
}

$pdo = get_db_connection();

// 5. التحقق من وجود العميل
$clientStmt = $pdo->prepare("SELECT id, name FROM clients WHERE id = :id LIMIT 1");
$clientStmt->execute([':id' => $clientId]);
$client = $clientStmt->fetch();
if (!$client) {
    $_SESSION['flash_error'] = 'العميل المحدد غير موجود في النظام.';
    header('Location: orders.php');
    exit();
}

$currentUser = current_user();
$userId = (int)($currentUser['id'] ?? 0);

// 6. تنفيذ المعاملة الذرية (Atomic PDO Transaction)
try {
    $pdo->beginTransaction();

    $grandTotal = 0.0;
    $orderLines = [];

    // قفل وفحص كل منتج عبر FOR UPDATE وحساب الأسعار الرسمية من قاعدة البيانات حصراً
    $productLockStmt = $pdo->prepare("
        SELECT id, name, price, count 
        FROM products 
        WHERE id = :id 
        FOR UPDATE
    ");

    foreach ($aggregatedItems as $productId => $quantity) {
        $productLockStmt->execute([':id' => $productId]);
        $product = $productLockStmt->fetch();

        if (!$product) {
            throw new RuntimeException("المنتج ذو المعرف ({$productId}) غير موجود في النظام.");
        }

        $availableStock = (int)$product['count'];
        if ($availableStock < $quantity) {
            throw new RuntimeException("عذراً، الكمية المتوفرة في المخزن للمنتج '{$product['name']}' هي ({$availableStock}) قطعة فقط، بينما طلبت ({$quantity}) قطعة.");
        }

        $unitPrice = (float)$product['price'];
        $subtotal  = round($unitPrice * $quantity, 2);
        $grandTotal += $subtotal;

        $orderLines[] = [
            'product_id' => $productId,
            'quantity'   => $quantity,
            'unit_price' => $unitPrice,
            'subtotal'   => $subtotal,
        ];
    }

    $grandTotal = round($grandTotal, 2);

    // توليد رقم طلب فريد
    do {
        $orderNumber = sprintf('ORD-%s-%04d', date('Y'), random_int(1000, 9999));
        $checkStmt = $pdo->prepare("SELECT id FROM orders WHERE order_number = :num LIMIT 1");
        $checkStmt->execute([':num' => $orderNumber]);
    } while ($checkStmt->fetch());

    // إدراج الطلب الرئيسي
    $insertOrderStmt = $pdo->prepare("
        INSERT INTO orders (order_number, client_id, user_id, total_amount, status)
        VALUES (:order_number, :client_id, :user_id, :total_amount, :status)
    ");
    $insertOrderStmt->execute([
        ':order_number' => $orderNumber,
        ':client_id'    => $clientId,
        ':user_id'      => $userId,
        ':total_amount' => $grandTotal,
        ':status'       => $status,
    ]);

    $orderId = (int)$pdo->lastInsertId();

    // إدراج بنود الطلب وخصم الكميات من المخزون
    $insertItemStmt = $pdo->prepare("
        INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal)
        VALUES (:order_id, :product_id, :quantity, :unit_price, :subtotal)
    ");

    $deductStockStmt = $pdo->prepare("
        UPDATE products 
        SET count = count - :qty 
        WHERE id = :id
    ");

    foreach ($orderLines as $line) {
        // إدراج بند الطلب
        $insertItemStmt->execute([
            ':order_id'   => $orderId,
            ':product_id' => $line['product_id'],
            ':quantity'   => $line['quantity'],
            ':unit_price' => $line['unit_price'],
            ':subtotal'   => $line['subtotal'],
        ]);

        // خصم المخزون تلقائياً
        $deductStockStmt->execute([
            ':qty' => $line['quantity'],
            ':id'  => $line['product_id'],
        ]);
    }

    // تأكيد المعاملة بنجاح
    $pdo->commit();

    $_SESSION['flash_success'] = "تم إنشاء الطلب رقم ({$orderNumber}) بنجاح بقيمة إجمالية " . number_format($grandTotal, 2) . " ر.س.";
    header('Location: orders.php');
    exit();

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Order creation transaction failed: ' . $e->getMessage());
    $_SESSION['flash_error'] = $e->getMessage();
    header('Location: orders.php');
    exit();
}
