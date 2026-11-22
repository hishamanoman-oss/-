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

// 3. جلب سجل الطلبات والمبيعات مع بيانات العميل والمستخدم وعدد البنود
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
    GROUP BY o.id
    ORDER BY o.created_at DESC
";
$ordersStmt = $pdo->query($ordersQuery);
$orders = $ordersStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../fonts/cairo.css?v=5">
    <link rel="stylesheet" href="../fontawesome/css/all.min.css?v=5">
    <link rel="stylesheet" href="css/orders.css?v=5">
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
                    <tr><td colspan="8">لا توجد طلبات مسجلة بعد</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- تضمين بيانات المنتجات للكتالوج اللحظي -->
    <script type="application/json" id="products_data">
        <?php echo json_encode($productsCatalog, JSON_UNESCAPED_UNICODE); ?>
    </script>
    <script src="js/orders.js?v=5"></script>
</body>
</html>
