<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// التحقق من المصادقة وإرجاع 401 في حال عدم وجود جلسة
require_auth(true);

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

try {
    $pdo = get_db_connection();

    // 1. مسار نمو المبيعات (المبيعات اليومية للطلبات غير الملغاة)
    $salesTrendStmt = $pdo->query("
        SELECT 
            DATE(created_at) AS order_date,
            COALESCE(SUM(total_amount), 0) AS total_sales,
            COUNT(*) AS orders_count
        FROM orders
        WHERE status != 'cancelled'
        GROUP BY DATE(created_at)
        ORDER BY order_date ASC
        LIMIT 14
    ");
    $salesTrend = $salesTrendStmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. المنتجات الأكثر مبيعاً (أعلى 5 منتجات مبيعاً في الطلبات غير الملغاة)
    $topProductsStmt = $pdo->query("
        SELECT 
            p.id,
            p.name,
            COALESCE(SUM(oi.quantity), 0) AS total_qty,
            COALESCE(SUM(oi.subtotal), 0) AS total_revenue
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        JOIN orders o ON oi.order_id = o.id
        WHERE o.status != 'cancelled'
        GROUP BY p.id, p.name
        ORDER BY total_qty DESC
        LIMIT 5
    ");
    $topProducts = $topProductsStmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. توزيع المخزون حسب الأقسام والتصنيفات
    $stockByCategoryStmt = $pdo->query("
        SELECT 
            COALESCE(NULLIF(TRIM(category), ''), 'غير مصنف') AS category_name,
            SUM(count) AS total_stock,
            COUNT(*) AS products_count
        FROM products
        GROUP BY category_name
        ORDER BY total_stock DESC
    ");
    $stockByCategory = $stockByCategoryStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'           => true,
        'sales_trend'       => $salesTrend,
        'top_products'      => $topProducts,
        'stock_by_category' => $stockByCategory
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('Charts API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ أثناء جلب بيانات الرسوم البيانية'
    ], JSON_UNESCAPED_UNICODE);
}
