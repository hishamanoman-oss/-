<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

require_auth();
require_role(['admin', 'staff']);
$currentUser = current_user();
$pdo = get_db_connection();

// استلام رسائل الجلسة التنبيهية (Flash Messages)
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// 1. جلب قائمة العملاء
$clientsStmt = $pdo->query("SELECT id, name, phone, is_vip FROM clients ORDER BY name ASC");
$clients = $clientsStmt->fetchAll();

// 2. جلب قائمة المنتجات المتوفرة في المخزن
$productsStmt = $pdo->query("
    SELECT id, name, sku, price, count 
    FROM products 
    WHERE count > 0 AND status = 'active' 
    ORDER BY name ASC
");
$productsCatalog = $productsStmt->fetchAll();

// معايير البحث والفلترة وترقيم الصفحات
$q      = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$limit  = 10;
$page   = max(1, (int)($_GET['page'] ?? 1));

$where  = [];
$params = [];

if ($q !== '') {
    $where[] = "(o.order_number LIKE :q_num OR c.name LIKE :q_cname)";
    $params[':q_num']   = '%' . $q . '%';
    $params[':q_cname'] = '%' . $q . '%';
}

if (in_array($status, ['completed', 'pending', 'cancelled'], true)) {
    $where[] = "o.status = :status";
    $params[':status'] = $status;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// 3. حساب إجمالي عدد الطلبات المطابقة
$countSql = "SELECT COUNT(*) FROM orders o JOIN clients c ON o.client_id = c.id $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();

$totalPages = max(1, (int)ceil($totalRecords / $limit));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $limit;

// 4. جلب سجل الطلبات والمبيعات مع بيانات العميل والمستخدم وعدد البنود للصفحة الحالية
$ordersQuery = "
    SELECT 
        o.id,
        o.order_number,
        o.total_amount,
        o.status,
        o.created_at,
        c.name AS client_name,
        c.phone AS client_phone,
        c.is_vip AS client_is_vip,
        u.full_name AS user_name,
        COUNT(oi.id) AS items_count,
        COALESCE(SUM(oi.quantity), 0) AS total_quantity
    FROM orders o
    JOIN clients c ON o.client_id = c.id
    JOIN users u ON o.user_id = u.id
    LEFT JOIN order_items oi ON o.id = oi.order_id
    $whereClause
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT :limit OFFSET :offset
";
$ordersStmt = $pdo->prepare($ordersQuery);
foreach ($params as $k => $v) {
    $ordersStmt->bindValue($k, $v, PDO::PARAM_STR);
}
$ordersStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$ordersStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$ordersStmt->execute();
$orders = $ordersStmt->fetchAll();
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
    <link rel="stylesheet" href="../fonts/cairo.css?v=14">
    <link rel="stylesheet" href="../fontawesome/css/all.min.css?v=14">
    <link rel="stylesheet" href="../Css/sidebar.css?v=14">
    <link rel="stylesheet" href="../Css/ui.css?v=14">
    <link rel="stylesheet" href="css/orders.css?v=14">
    <link rel="stylesheet" href="../Css/theme.css?v=14">
    <link rel="stylesheet" href="../Css/responsive.css?v=14">
    <title>المبيعات والطلبات</title>
</head>
<body>
    <?php
    $activePage = 'orders';
    $basePath   = '../';
    require __DIR__ . '/../includes/sidebar.php';
    ?>

    <div class="content">
        <div class="title-info">
            <p>إدارة المبيعات والطلبات</p>
            <i class="fas fa-shopping-cart"></i>
        </div>

        <?php if ($flashSuccess): ?>
            <div class="alert-box alert-success">
                <i class="fas fa-check-circle"></i> <?php echo e($flashSuccess); ?>
            </div>
        <?php endif; ?>

        <?php if ($flashError): ?>
            <div class="alert-box alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo e($flashError); ?>
            </div>
        <?php endif; ?>

        <!-- نموذج إنشاء طلب جديد -->
        <div class="body">
            <div class="section-subtitle">
                <i class="fas fa-plus-circle"></i> تسجيل طلب مبيعات جديد
            </div>

            <form id="order_form" method="post" action="order_process.php">
                <?php echo csrf_field(); ?>

                <div class="order-header-fields">
                    <div class="form-group" style="flex: 2 1 300px;">
                        <label for="client_id">العميل <span style="color:#ff4d4f;">*</span></label>
                        <select name="client_id" id="client_id" required>
                            <option value="">-- اختر العميل --</option>
                            <?php foreach ($clients as $c): ?>
                                <option value="<?php echo (int)$c['id']; ?>">
                                    <?php echo e($c['name']); ?> (<?php echo e($c['phone']); ?>) <?php echo !empty($c['is_vip']) ? '⭐ [VIP]' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="flex: 1 1 200px;">
                        <label for="order_status">حالة الطلب</label>
                        <select name="status" id="order_status" required>
                            <option value="completed" selected>مكتمل (خصم فوري للمخزون)</option>
                            <option value="pending">قيد الانتظار</option>
                        </select>
                    </div>
                </div>

                <!-- حاوية بنود المنتجات المتعددة -->
                <div class="items-card">
                    <div class="section-subtitle" style="font-size: 14px; margin-bottom: 10px;">
                        <i class="fas fa-boxes-stacked"></i> بنود المنتجات والكميات
                    </div>

                    <div id="items_container">
                        <!-- تُضاف أسطر المنتجات ديناميكياً عبر orders.js -->
                    </div>

                    <div class="items-controls">
                        <button type="button" id="btn_add_item" class="btn-add-item">
                            <i class="fas fa-plus"></i> إضافة منتج آخر
                        </button>

                        <div class="order-summary-box">
                            <div class="summary-item">
                                <span>إجمالي القطع:</span>
                                <strong id="total_items_count" style="color: #00c6ff;">0</strong>
                            </div>
                            <div class="summary-item">
                                <span>المبلغ الإجمالي:</span>
                                <span id="grand_total_amount" class="grand-total-amount">0.00 ر.س</span>
                            </div>
                        </div>

                        <button type="submit" class="btn-submit-order">
                            <i class="fas fa-check-circle"></i> إنشاء وتأكيد الطلب
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- جدول سجل الطلبات والمبيعات -->
        <div class="title-info" style="background: linear-gradient(135deg, #1f4068, #162447); margin-top: 10px;">
            <p>سجل الفواتير والطلبات السابقة</p>
            <i class="fas fa-history"></i>
        </div>

        <!-- شريط البحث والتصفية للطلبات -->
        <div class="filter-bar">
            <form method="GET" action="orders.php" class="filter-form">
                <input type="text" name="q" class="filter-input" placeholder="بحث برقم الطلب أو اسم العميل..." value="<?php echo e($q); ?>">
                
                <select name="status" class="filter-select">
                    <option value="">جميع حالات الطلبات</option>
                    <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>مكتمل</option>
                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>قيد الانتظار</option>
                    <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>ملغي</option>
                </select>

                <div class="filter-actions">
                    <button type="submit" class="btn-filter">
                        <i class="fas fa-search"></i> بحث
                    </button>
                    <?php if ($q !== '' || $status !== ''): ?>
                        <a href="orders.php" class="btn-filter-reset">
                            <i class="fas fa-times"></i> إلغاء التصفية
                        </a>
                    <?php endif; ?>
                </div>
            </form>

            <div class="filter-summary">
                إجمالي النتائج: <strong><?php echo $totalRecords; ?></strong> طلب
            </div>
        </div>

        <div class="table-responsive">
            <table id="table">
                <thead>
                    <tr>
                        <th>رقم الطلب</th>
                        <th>العميل</th>
                        <th>المسؤول</th>
                        <th>البنود / القطع</th>
                        <th>المبلغ الإجمالي</th>
                        <th>حالة الطلب</th>
                        <th>التاريخ والوقت</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($orders)): ?>
                        <?php foreach ($orders as $order): ?>
                            <?php
                            $statusClass = 'status-' . $order['status'];
                            $statusArabic = match($order['status']) {
                                'completed' => 'مكتمل',
                                'pending'   => 'قيد الانتظار',
                                'cancelled' => 'ملغي',
                                default     => $order['status']
                            };
                            ?>
                            <tr>
                                <td>
                                    <strong style="color: #00c6ff; font-family: monospace; font-size: 14px;">
                                        <?php echo e($order['order_number']); ?>
                                    </strong>
                                </td>
                                <td>
                                    <?php echo e($order['client_name']); ?>
                                    <?php if (!empty($order['client_is_vip'])): ?>
                                        <span style="color:#ffd700; font-size: 11px;">⭐ VIP</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo e($order['user_name']); ?></td>
                                <td>
                                    <?php echo (int)$order['items_count']; ?> صنف (<?php echo (int)$order['total_quantity']; ?> قطعة)
                                </td>
                                <td>
                                    <strong style="color: #51cf66;">
                                        <?php echo number_format((float)$order['total_amount'], 2); ?> ر.س
                                    </strong>
                                </td>
                                <td>
                                    <span class="badge-status <?php echo $statusClass; ?>">
                                        <?php echo $statusArabic; ?>
                                    </span>
                                </td>
                                <td style="font-size: 13px; color: #aaa;">
                                    <?php echo date('Y-m-d H:i', strtotime($order['created_at'])); ?>
                                </td>
                                <td class="actions">
                                    <!-- رابط الفاتورة (متاح للمدير والموظف) -->
                                    <a href="invoice.php?id=<?php echo (int)$order['id']; ?>" class="btn-action btn-invoice" title="عرض وطباعة الفاتورة">
                                        <i class="fas fa-file-invoice"></i> الفاتورة
                                    </a>

                                    <?php if (has_role('admin')): ?>
                                        <!-- إكمال الطلب المعلق -->
                                        <?php if ($order['status'] === 'pending'): ?>
                                            <form method="post" action="update_order_status.php" style="display:inline;">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
                                                <input type="hidden" name="new_status" value="completed">
                                                <button type="submit" class="btn-action btn-complete" title="تأكيد إكمال الطلب">
                                                    <i class="fas fa-check"></i> إكمال
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <!-- إلغاء الطلب (مع استرجاع المخزون تلقائياً) -->
                                        <?php if ($order['status'] !== 'cancelled'): ?>
                                            <form method="post" action="update_order_status.php" class="form-cancel-order" style="display:inline;">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
                                                <input type="hidden" name="new_status" value="cancelled">
                                                <button type="submit" class="btn-action btn-cancel" title="إلغاء الطلب واسترجاع الكميات للمخزن">
                                                    <i class="fas fa-times-circle"></i> إلغاء
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span style="color: #666; font-size: 11px;">ملغي نهائياً</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 25px; color: #8899aa;">
                                <?php if ($q !== '' || $status !== ''): ?>
                                    <i class="fas fa-search" style="font-size: 24px; margin-bottom: 8px; display: block; color: #0481ff;"></i>
                                    لا توجد طلبات مطابقة لبحثك. <a href="orders.php" style="color: #00c6ff; text-decoration: underline;">إعادة ضبط البحث</a>
                                <?php else: ?>
                                    لا توجد طلبات مسجلة بعد.
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- شريط ترقيم الصفحات للطلبات -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination-container">
            <div class="pagination-info">
                عرض الصفحة <strong><?php echo $page; ?></strong> من إجمالي <strong><?php echo $totalPages; ?></strong> صفحات (<?php echo $totalRecords; ?> طلب)
            </div>
            <ul class="pagination">
                <li>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => max(1, $page - 1)])); ?>" 
                       class="page-link <?php echo $page <= 1 ? 'disabled' : ''; ?>" aria-label="السابق">
                        <i class="fas fa-chevron-right"></i> السابق
                    </a>
                </li>

                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <?php if ($p == 1 || $p == $totalPages || ($p >= $page - 2 && $p <= $page + 2)): ?>
                        <li>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $p])); ?>" 
                               class="page-link <?php echo $p === $page ? 'active' : ''; ?>">
                                <?php echo $p; ?>
                            </a>
                        </li>
                    <?php elseif ($p == $page - 3 || $p == $page + 3): ?>
                        <li style="padding: 0 4px; color: #64748b;">...</li>
                    <?php endif; ?>
                <?php endfor; ?>

                <li>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => min($totalPages, $page + 1)])); ?>" 
                       class="page-link <?php echo $page >= $totalPages ? 'disabled' : ''; ?>" aria-label="التالي">
                        التالي <i class="fas fa-chevron-left"></i>
                    </a>
                </li>
            </ul>
        </div>
        <?php endif; ?>
    </div>

    <!-- تضمين بيانات المنتجات للكتالوج اللحظي -->
    <script type="application/json" id="products_data">
        <?php echo json_encode($productsCatalog, JSON_UNESCAPED_UNICODE); ?>
    </script>
    <script src="../js/jquery-3.7.1.js"></script>
    <script src="../js/ui.js?v=14"></script>
    <script src="js/orders.js?v=14"></script>
</body>
</html>
