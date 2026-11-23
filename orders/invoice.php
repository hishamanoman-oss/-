<?php
/**
 * صفحة عرض وطباعة الفاتورة العربية
 * Printable Arabic Invoice Page (PDO + Role Protected + Print Friendly)
 */

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

require_auth();
require_role(['admin', 'staff']);
$currentUser = current_user();

$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($orderId <= 0) {
    http_response_code(404);
    die('
        <div dir="rtl" style="font-family: Cairo, sans-serif; text-align:center; padding:50px; background:#001; color:#fff; min-height:100vh;">
            <h2 style="color:#ff4d4f;">رقم الفاتورة غير محدد أو غير صالح</h2>
            <a href="orders.php" style="color:#00c6ff; text-decoration:none; display:inline-block; margin-top:15px;">العودة إلى قائمة الطلبات</a>
        </div>
    ');
}

$pdo = get_db_connection();

// 1. جلب بيانات إعدادات المتجر
$settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('store_name', 'currency')");
$settingsMap = [];
while ($row = $settingsStmt->fetch()) {
    $settingsMap[$row['setting_key']] = $row['setting_value'];
}
$storeName = $settingsMap['store_name'] ?? 'متجر المبيعات';
$currency  = $settingsMap['currency'] ?? 'ر.س';

// 2. جلب بيانات الطلب والعميل والمستخدم
$orderStmt = $pdo->prepare("
    SELECT 
        o.id,
        o.order_number,
        o.total_amount,
        o.status,
        o.created_at,
        c.name AS client_name,
        c.phone AS client_phone,
        c.email AS client_email,
        c.address AS client_address,
        c.is_vip AS client_is_vip,
        u.full_name AS cashier_name
    FROM orders o
    JOIN clients c ON o.client_id = c.id
    JOIN users u ON o.user_id = u.id
    WHERE o.id = :id
    LIMIT 1
");
$orderStmt->execute([':id' => $orderId]);
$order = $orderStmt->fetch();

if (!$order) {
    http_response_code(404);
    die('
        <div dir="rtl" style="font-family: Cairo, sans-serif; text-align:center; padding:50px; background:#001; color:#fff; min-height:100vh;">
            <h2 style="color:#ff4d4f;">الفاتورة المطلوبة غير موجودة في النظام</h2>
            <a href="orders.php" style="color:#00c6ff; text-decoration:none; display:inline-block; margin-top:15px;">العودة إلى قائمة الطلبات</a>
        </div>
    ');
}

// 3. جلب بنود الطلب بالتفصيل
$itemsStmt = $pdo->prepare("
    SELECT 
        oi.id,
        oi.quantity,
        oi.unit_price,
        oi.subtotal,
        p.name AS product_name,
        p.sku AS product_sku,
        p.category AS product_category
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = :order_id
    ORDER BY oi.id ASC
");
$itemsStmt->execute([':order_id' => $orderId]);
$items = $itemsStmt->fetchAll();

$statusArabic = match($order['status']) {
    'completed' => 'مكتمل ومسدد',
    'pending'   => 'قيد الانتظار',
    'cancelled' => 'ملغي',
    default     => $order['status']
};
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../fonts/cairo.css?v=8">
    <link rel="stylesheet" href="../fontawesome/css/all.min.css?v=8">
    <title>فاتورة ضريبية - <?php echo e($order['order_number']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Cairo', sans-serif;
        }
        body {
            background-color: #0d1117;
            color: #e6edf3;
            padding: 20px;
            display: flex;
            justify-content: center;
        }
        .invoice-wrapper {
            width: 100%;
            max-width: 850px;
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 12px;
            padding: 35px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.5);
        }
        /* أزرار التحكم العلوية */
        .no-print-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #30363d;
        }
        .btn-print {
            background: #238636;
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 6px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background 0.2s;
        }
        .btn-print:hover { background: #2ea043; }
        .btn-back {
            background: #21262d;
            color: #58a6ff;
            border: 1px solid #30363d;
            padding: 9px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-back:hover { background: #30363d; }
        
        /* ترويسة الفاتورة */
        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #30363d;
        }
        .store-brand h1 {
            font-size: 26px;
            color: #58a6ff;
            margin-bottom: 6px;
        }
        .store-brand p {
            color: #8b949e;
            font-size: 13px;
        }
        .invoice-meta {
            text-align: left;
        }
        .invoice-meta h2 {
            font-size: 20px;
            color: #e6edf3;
            margin-bottom: 6px;
        }
        .meta-line {
            font-size: 13px;
            color: #8b949e;
            margin-bottom: 4px;
        }
        .meta-line strong {
            color: #e6edf3;
        }

        /* شبكة معلومات العميل والمسؤول */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            background: rgba(255,255,255,0.03);
            border: 1px solid #30363d;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .info-col h3 {
            font-size: 15px;
            color: #58a6ff;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .info-col p {
            font-size: 14px;
            color: #c9d1d9;
            margin-bottom: 6px;
        }

        /* جدول البنود */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }
        .items-table th, .items-table td {
            padding: 12px 14px;
            text-align: center;
            border-bottom: 1px solid #30363d;
        }
        .items-table th {
            background-color: #21262d;
            color: #58a6ff;
            font-size: 14px;
            font-weight: bold;
        }
        .items-table td {
            font-size: 14px;
            color: #e6edf3;
        }
        .items-table tr:hover td {
            background-color: rgba(255,255,255,0.02);
        }

        /* الإجمالي والخاتمة */
        .invoice-summary {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 30px;
        }
        .summary-card {
            width: 300px;
            background: #21262d;
            border: 1px solid #30363d;
            border-radius: 8px;
            padding: 15px 20px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            color: #8b949e;
            margin-bottom: 8px;
        }
        .summary-row.total {
            font-size: 18px;
            font-weight: bold;
            color: #3fb950;
            padding-top: 10px;
            border-top: 1px solid #30363d;
            margin-bottom: 0;
        }

        .invoice-footer {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #30363d;
            color: #8b949e;
            font-size: 13px;
        }

        /* إعدادات الطباعة الرسمية */
        @media print {
            body {
                background: white !important;
                color: black !important;
                padding: 0;
            }
            .invoice-wrapper {
                background: white !important;
                color: black !important;
                border: none !important;
                box-shadow: none !important;
                padding: 15px;
                max-width: 100%;
            }
            .no-print-bar {
                display: none !important;
            }
            .store-brand h1 {
                color: #000 !important;
            }
            .invoice-meta h2 {
                color: #000 !important;
            }
            .info-grid {
                background: white !important;
                border: 1px solid #ccc !important;
                color: #000 !important;
            }
            .info-col h3 {
                color: #000 !important;
            }
            .info-col p {
                color: #222 !important;
            }
            .items-table th {
                background: #f0f0f0 !important;
                color: black !important;
                border: 1px solid #ccc !important;
            }
            .items-table td {
                color: black !important;
                border: 1px solid #ccc !important;
            }
            .summary-card {
                background: #f9f9f9 !important;
                border: 1px solid #ccc !important;
                color: black !important;
            }
            .summary-row.total {
                color: black !important;
            }
        }
    </style>
</head>
<body>

<div class="invoice-wrapper">
    <!-- شريط الإجراءات (يختفي عند الطباعة) -->
    <div class="no-print-bar">
        <a href="orders.php" class="btn-back">
            <i class="fas fa-arrow-right"></i> العودة للطلبات
        </a>
        <button type="button" class="btn-print" onclick="window.print()">
            <i class="fas fa-print"></i> طباعة الفاتورة
        </button>
    </div>

    <!-- ترويسة الفاتورة -->
    <div class="invoice-header">
        <div class="store-brand">
            <h1><i class="fas fa-store"></i> <?php echo e($storeName); ?></h1>
            <p>نظام إدارة المبيعات والمخزون المتكامل</p>
        </div>
        <div class="invoice-meta">
            <h2>فاتورة مبيعات</h2>
            <div class="meta-line">رقم الفاتورة: <strong><?php echo e($order['order_number']); ?></strong></div>
            <div class="meta-line">التاريخ: <strong><?php echo date('Y-m-d H:i', strtotime($order['created_at'])); ?></strong></div>
            <div class="meta-line">الحالة: <strong><?php echo e($statusArabic); ?></strong></div>
        </div>
    </div>

    <!-- بيانات العميل والموظف -->
    <div class="info-grid">
        <div class="info-col">
            <h3><i class="fas fa-user"></i> بيانات العميل</h3>
            <p><strong>الاسم:</strong> <?php echo e($order['client_name']); ?> <?php echo !empty($order['client_is_vip']) ? '⭐ [عميل VIP]' : ''; ?></p>
            <p><strong>الهاتف:</strong> <?php echo e($order['client_phone']); ?></p>
            <?php if (!empty($order['client_email'])): ?>
                <p><strong>البريد:</strong> <?php echo e($order['client_email']); ?></p>
            <?php endif; ?>
            <?php if (!empty($order['client_address'])): ?>
                <p><strong>العنوان:</strong> <?php echo e($order['client_address']); ?></p>
            <?php endif; ?>
        </div>
        <div class="info-col">
            <h3><i class="fas fa-cash-register"></i> تفاصيل العملية</h3>
            <p><strong>المسؤول / الكاشير:</strong> <?php echo e($order['cashier_name']); ?></p>
            <p><strong>طريقة السداد:</strong> نقدي / شبكة</p>
            <p><strong>عدد البنود:</strong> <?php echo count($items); ?> صنف</p>
        </div>
    </div>

    <!-- جدول البنود المباعة -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 50px;">#</th>
                <th style="text-align: right;">المنتج</th>
                <th>رمز SKU</th>
                <th>السعر الفردي</th>
                <th>الكمية</th>
                <th style="text-align: left;">الإجمالي</th>
            </tr>
        </thead>
        <tbody>
            <?php $counter = 1; ?>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?php echo $counter++; ?></td>
                    <td style="text-align: right;">
                        <strong><?php echo e($item['product_name']); ?></strong>
                        <?php if (!empty($item['product_category'])): ?>
                            <span style="font-size: 11px; color: #8b949e; display: block;"><?php echo e($item['product_category']); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><code><?php echo e($item['product_sku']); ?></code></td>
                    <td><?php echo number_format((float)$item['unit_price'], 2); ?> <?php echo e($currency); ?></td>
                    <td><strong><?php echo (int)$item['quantity']; ?></strong></td>
                    <td style="text-align: left; font-weight: bold; color: #3fb950;">
                        <?php echo number_format((float)$item['subtotal'], 2); ?> <?php echo e($currency); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- ملخص المبالغ -->
    <div class="invoice-summary">
        <div class="summary-card">
            <div class="summary-row">
                <span>المجموع الفرعي:</span>
                <span><?php echo number_format((float)$order['total_amount'], 2); ?> <?php echo e($currency); ?></span>
            </div>
            <div class="summary-row">
                <span>الضريبة المضافة:</span>
                <span>مشمولة</span>
            </div>
            <div class="summary-row total">
                <span>الإجمالي النهائي:</span>
                <span><?php echo number_format((float)$order['total_amount'], 2); ?> <?php echo e($currency); ?></span>
            </div>
        </div>
    </div>

    <!-- خاتمة الفاتورة -->
    <div class="invoice-footer">
        <p>شكراً لتعاملكم مع <?php echo e($storeName); ?>. نتطلع لخدمتكم دائماً!</p>
    </div>
</div>

</body>
</html>
