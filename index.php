<?php
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

require_auth();
$currentUser = current_user();

$pdo = get_db_connection();

// 1. جلب إعدادات المتجر والعملة
$settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('store_name', 'currency')");
$settingsMap  = $settingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);
$storeName    = !empty($settingsMap['store_name']) ? $settingsMap['store_name'] : 'متجر المبيعات الذكي';
$currency     = !empty($settingsMap['currency']) ? $settingsMap['currency'] : 'ر.س';

// 2. مبيعات وطلبات اليوم
$todayStmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) AS today_sales, COUNT(*) AS today_orders FROM orders WHERE status != 'cancelled' AND DATE(created_at) = CURDATE()");
$todayStats = $todayStmt->fetch(PDO::FETCH_ASSOC);

// 3. إجمالي المبيعات والطلبات الكلية
$totalStmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) AS total_sales, COUNT(*) AS total_orders FROM orders WHERE status != 'cancelled'");
$totalStats = $totalStmt->fetch(PDO::FETCH_ASSOC);

// 4. إحصائيات العملاء
$todayClientsCount = (int)$pdo->query("SELECT COUNT(*) FROM clients WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$totalClientsCount = (int)$pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();

// 5. إحصائيات المخزون
$lowStockCount     = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE count <= min_stock_alert")->fetchColumn();
$totalProductsCount = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();

// 6. آخر 5 مبيعات
$recentOrdersStmt = $pdo->query("
    SELECT 
        o.id,
        o.order_number,
        COALESCE(c.name, 'عميل محذوف') AS client_name,
        COALESCE(c.is_vip, 0) AS is_vip,
        COALESCE(u.full_name, 'غير معروف') AS user_name,
        o.total_amount,
        o.status,
        o.created_at
    FROM orders o
    LEFT JOIN clients c ON o.client_id = c.id
    LEFT JOIN users u ON o.user_id = u.id
    ORDER BY o.created_at DESC, o.id DESC
    LIMIT 5
");
$recentOrders = $recentOrdersStmt->fetchAll(PDO::FETCH_ASSOC);

// 7. المنتجات الحرجة في المخزون
$lowStockStmt = $pdo->query("
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
$lowStockProducts = $lowStockStmt->fetchAll(PDO::FETCH_ASSOC);

$lastUpdated = date('H:i:s');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>
        (function() {
            var theme = localStorage.getItem('store_theme') || 'dark';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <link rel="stylesheet" href="fonts/cairo.css?v=15">
    <link rel="stylesheet" href="fontawesome/css/all.min.css?v=15">
    <link rel="stylesheet" href="Css/sidebar.css?v=15">
    <link rel="stylesheet" href="Css/ui.css?v=15">
    <link rel="stylesheet" href="Css/work3.css?v=15">
    <link rel="stylesheet" href="Css/theme.css?v=15">
    <link rel="stylesheet" href="Css/responsive.css?v=15">
    <title>لوحة التحكم والمتابعة - <?= e($storeName) ?></title>
</head>
<body>
    <?php
    $activePage = 'dashboard';
    $basePath   = '';
    require __DIR__ . '/includes/sidebar.php';
    ?>

    <div class="content">
        <!-- شريط الترويسة وأدوات المراقبة الحية -->
        <div class="title-info">
            <div>
                <p style="font-size:18px; font-weight:700;"><i class="fas fa-store-alt" style="margin-left:8px;"></i> <span id="storeNameDisplay"><?= e($storeName) ?></span> - لوحة المراقبة الحية</p>
            </div>
            <div class="live-controls">
                <div class="live-badge">
                    <span class="pulse-dot"></span>
                    <span>مباشر</span>
                    <span style="opacity:0.7; margin-right:4px;">(آخر تحديث: <span id="lastUpdated"><?= e($lastUpdated) ?></span>)</span>
                </div>
                <button type="button" id="refreshBtn" class="btn-refresh" title="تحديث البيانات لحظياً">
                    <i class="fas fa-sync-alt" id="refreshIcon"></i>
                    <span>تحديث مباشر</span>
                </button>
            </div>
        </div>

        <!-- بطاقات مؤشرات الأداء الحية (KPIs) -->
        <div class="data-info">
            <!-- 1. مبيعات اليوم -->
            <div class="box">
                <i class="fas fa-cash-register"></i>
                <div class="data">
                    <p>مبيعات اليوم</p>
                    <span><span id="kpiTodaySales"><?= number_format((float)$todayStats['today_sales'], 2) ?></span> <small style="display:inline; color:#00c6ff;"><?= e($currency) ?></small></span>
                    <small>الإجمالي: <span id="kpiTotalSales"><?= number_format((float)$totalStats['total_sales'], 2) ?></span> <?= e($currency) ?></small>
                </div>
            </div>

            <!-- 2. طلبات اليوم -->
            <div class="box">
                <i class="fas fa-shopping-cart"></i>
                <div class="data">
                    <p>طلبات اليوم</p>
                    <span id="kpiTodayOrders"><?= (int)$todayStats['today_orders'] ?></span>
                    <small>إجمالي الطلبات: <span id="kpiTotalOrders"><?= (int)$totalStats['total_orders'] ?></span> طلب</small>
                </div>
            </div>

            <!-- 3. العملاء المسجلين -->
            <div class="box">
                <i class="fas fa-users"></i>
                <div class="data">
                    <p>قاعدة العملاء</p>
                    <span id="kpiTotalClients"><?= $totalClientsCount ?></span>
                    <small>انضم اليوم: <span id="kpiTodayClients"><?= $todayClientsCount ?></span> عميل</small>
                </div>
            </div>

            <!-- 4. تنبيهات المخزون -->
            <div class="box">
                <i class="fas fa-boxes-stacked"></i>
                <div class="data">
                    <p>تنبيهات المخزون</p>
                    <span id="kpiLowStock" class="<?= $lowStockCount > 0 ? 'text-warning' : 'text-success' ?>"><?= $lowStockCount ?></span>
                    <small>من إجمالي <span id="kpiTotalProducts"><?= $totalProductsCount ?></span> صنف</small>
                </div>
            </div>
        </div>

        <!-- شبكة جداول المراقبة المباشرة -->
        <div class="dashboard-grid">
            <!-- جدول آخر المبيعات -->
            <div class="monitoring-card">
                <div class="monitoring-card-header">
                    <h3><i class="fas fa-receipt"></i> آخر المبيعات والطلبات</h3>
                    <a href="orders/orders.php">عرض كافة المبيعات <i class="fas fa-arrow-left"></i></a>
                </div>
                <div class="table-responsive">
                    <table class="dash-table">
                        <thead>
                            <tr>
                                <th>رقم الطلب</th>
                                <th>العميل</th>
                                <th>الكاشير</th>
                                <th>الإجمالي</th>
                                <th>الحالة</th>
                                <th>التاريخ</th>
                                <th>الإجراء</th>
                            </tr>
                        </thead>
                        <tbody id="recentOrdersBody">
                            <?php if (empty($recentOrders)): ?>
                                <tr>
                                    <td colspan="7" style="text-align:center; color:#8899aa; padding:15px;">لا توجد طلبات مسجلة حتى الآن</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentOrders as $order): ?>
                                    <?php
                                    $statusClass = 'badge-completed';
                                    $statusText  = 'مكتمل';
                                    if ($order['status'] === 'pending') {
                                        $statusClass = 'badge-pending';
                                        $statusText  = 'معلق';
                                    } elseif ($order['status'] === 'cancelled') {
                                        $statusClass = 'badge-cancelled';
                                        $statusText  = 'ملغي';
                                    }
                                    ?>
                                    <tr>
                                        <td><strong><?= e($order['order_number']) ?></strong></td>
                                        <td>
                                            <?= e($order['client_name']) ?>
                                            <?php if ((int)$order['is_vip'] === 1): ?>
                                                <span class="badge-vip"><i class="fas fa-crown"></i> VIP</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= e($order['user_name']) ?></td>
                                        <td><strong><?= number_format((float)$order['total_amount'], 2) ?></strong> <small><?= e($currency) ?></small></td>
                                        <td><span class="badge-status <?= $statusClass ?>"><?= $statusText ?></span></td>
                                        <td><small><?= e($order['created_at']) ?></small></td>
                                        <td>
                                            <a href="orders/invoice.php?id=<?= urlencode((string)$order['id']) ?>" class="btn-sm-action">
                                                <i class="fas fa-file-invoice"></i> الفاتورة
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- جدول تنبيهات المخزون المنخفض -->
            <div class="monitoring-card">
                <div class="monitoring-card-header">
                    <h3><i class="fas fa-exclamation-triangle" style="color:#ffaa00;"></i> تنبيهات المخزون المنخفض</h3>
                    <a href="Products/products.php">إدارة المخزون <i class="fas fa-arrow-left"></i></a>
                </div>
                <div class="table-responsive">
                    <table class="dash-table">
                        <thead>
                            <tr>
                                <th>المنتج</th>
                                <th>رمز SKU</th>
                                <th>التصنيف</th>
                                <th>الرصيد الحالي</th>
                                <th>حد التنبيه</th>
                                <th>الإجراء</th>
                            </tr>
                        </thead>
                        <tbody id="lowStockBody">
                            <?php if (empty($lowStockProducts)): ?>
                                <tr>
                                    <td colspan="6" style="text-align:center; color:#00ff88; padding:15px;">
                                        <i class="fas fa-check-circle"></i> جميع المنتجات بمستويات مخزون آمنة
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($lowStockProducts as $prod): ?>
                                    <tr>
                                        <td><strong><?= e($prod['name']) ?></strong></td>
                                        <td><code><?= e($prod['sku']) ?></code></td>
                                        <td><?= e($prod['category'] ?: 'عام') ?></td>
                                        <td><strong class="text-danger"><?= (int)$prod['count'] ?></strong></td>
                                        <td><span style="color:#aaa;"><?= (int)$prod['min_stock_alert'] ?></span></td>
                                        <td>
                                            <a href="Products/products.php" class="btn-sm-action">
                                                <i class="fas fa-boxes"></i> تفقد المخزن
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="js/ui.js?v=15"></script>
    <script src="js/dashboard.js?v=15"></script>
</body>
</html>
