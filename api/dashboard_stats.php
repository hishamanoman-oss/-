<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// التحقق من تسجيل الدخول وإرجاع 401 في حال عدم وجود جلسة نشطة
require_auth(true);

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

try {
    $pdo = get_db_connection();

    // 1. جلب إعدادات المتجر والعملة
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('store_name', 'currency')");
    $settingsRows = $settingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $storeName = !empty($settingsRows['store_name']) ? $settingsRows['store_name'] : 'متجر المبيعات الذكي';
    $currency  = !empty($settingsRows['currency']) ? $settingsRows['currency'] : 'ر.س';

    // 2. مبيعات وطلبات اليوم (غير الملغاة)
    $todayStmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) AS today_sales, COUNT(*) AS today_orders FROM orders WHERE status != 'cancelled' AND DATE(created_at) = CURDATE()");
    $todayData = $todayStmt->fetch(PDO::FETCH_ASSOC);

    // 3. إجمالي المبيعات والطلبات الكلية (غير الملغاة)
    $totalOrdersStmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) AS total_sales, COUNT(*) AS total_orders FROM orders WHERE status != 'cancelled'");
    $totalOrdersData = $totalOrdersStmt->fetch(PDO::FETCH_ASSOC);

    // 4. عملاء اليوم وإجمالي العملاء
    $todayClientsStmt = $pdo->query("SELECT COUNT(*) AS today_clients FROM clients WHERE DATE(created_at) = CURDATE()");
    $todayClientsCount = (int)$todayClientsStmt->fetchColumn();

    $totalClientsStmt = $pdo->query("SELECT COUNT(*) AS total_clients FROM clients");
    $totalClientsCount = (int)$totalClientsStmt->fetchColumn();

    // 5. المنتجات ذات المخزون المنخفض وإجمالي المنتجات
    $lowStockStmt = $pdo->query("SELECT COUNT(*) AS low_stock FROM products WHERE count <= min_stock_alert");
    $lowStockCount = (int)$lowStockStmt->fetchColumn();

    $totalProductsStmt = $pdo->query("SELECT COUNT(*) AS total_products FROM products");
    $totalProductsCount = (int)$totalProductsStmt->fetchColumn();

    // 6. آخر 5 طلبات مكتملة أو معلقة
    $recentOrdersStmt = $pdo->query("
        SELECT 
            o.id,
            o.order_number,
            c.name AS client_name,
            c.is_vip,
            u.full_name AS user_name,
            o.total_amount,
            o.status,
            o.created_at
        FROM orders o
        JOIN clients c ON o.client_id = c.id
        JOIN users u ON o.user_id = u.id
        ORDER BY o.created_at DESC, o.id DESC
        LIMIT 5
    ");
    $recentOrders = $recentOrdersStmt->fetchAll(PDO::FETCH_ASSOC);

    // 7. المنتجات ذات الرصيد الحرج (حتى 5 منتجات)
    $lowStockProductsStmt = $pdo->query("
        SELECT 
            id,
            name,
            sku,
            category,
            price,
            count,
            min_stock_alert
        FROM products
        WHERE count <= min_stock_alert
        ORDER BY count ASC, id ASC
        LIMIT 5
    ");
    $lowStockProducts = $lowStockProductsStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'                 => true,
        'today_sales_amount'      => (float)$todayData['today_sales'],
        'today_orders_count'      => (int)$todayData['today_orders'],
        'total_sales_amount'      => (float)$totalOrdersData['total_sales'],
        'total_orders_count'      => (int)$totalOrdersData['total_orders'],
        'today_new_clients_count' => $todayClientsCount,
        'total_clients_count'     => $totalClientsCount,
        'low_stock_count'         => $lowStockCount,
        'total_products_count'    => $totalProductsCount,
        'recent_orders'           => $recentOrders,
        'low_stock_products'      => $lowStockProducts,
        'store_name'              => $storeName,
        'currency'                => $currency,
        'last_updated'            => date('H:i:s')
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('Dashboard API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ داخلي أثناء جلب إحصائيات لوحة التحكم'
    ], JSON_UNESCAPED_UNICODE);
}
