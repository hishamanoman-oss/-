<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

require_auth();
$currentUser = current_user();

$pdo = get_db_connection();

// 1. جلب العملة من الإعدادات
$currency = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'currency'")->fetchColumn() ?: 'ر.س';

// 2. جلب عملاء VIP مع إحصائيات مشترياتهم وطلباتهم الحقيقية من المبيعات
$vipStmt = $pdo->query("
    SELECT 
        c.id,
        c.name,
        c.phone,
        c.email,
        c.address,
        c.created_at,
        COUNT(DISTINCT o.id) AS orders_count,
        COALESCE(SUM(CASE WHEN o.status != 'cancelled' THEN o.total_amount ELSE 0 END), 0) AS total_spent
    FROM clients c
    LEFT JOIN orders o ON c.id = o.client_id
    WHERE c.is_vip = 1
    GROUP BY c.id
    ORDER BY total_spent DESC, c.name ASC
");
$vipClients = $vipStmt->fetchAll(PDO::FETCH_ASSOC);

// 3. جلب منتجات تحت المراقبة (المنتجات الحرجة في المخزون أو الأعلى قيمة)
$monitoredStmt = $pdo->query("
    SELECT 
        id,
        name,
        sku,
        category,
        price,
        count,
        min_stock_alert
    FROM products
    ORDER BY (count <= min_stock_alert) DESC, price DESC
    LIMIT 8
");
$monitoredProducts = $monitoredStmt->fetchAll(PDO::FETCH_ASSOC);
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
    <link rel="stylesheet" href="../fonts/cairo.css?v=15">
    <link rel="stylesheet" href="../fontawesome/css/all.min.css?v=15">
    <link rel="stylesheet" href="../Css/sidebar.css?v=15">
    <link rel="stylesheet" href="../Css/ui.css?v=15">
    <link rel="stylesheet" href="css/favorite.css?v=15">
    <link rel="stylesheet" href="../Css/theme.css?v=15">
    <link rel="stylesheet" href="../Css/responsive.css?v=15">
    <title>المفضلة وقائمة المراقبة</title>
</head>
<body>
    <?php
    $activePage = 'favorite';
    $basePath   = '../';
    require __DIR__ . '/../includes/sidebar.php';
    ?>

    <div class="content">
        <div class="title-info">
            <p>المفضلة وقائمة المراقبة الخاصة</p>
            <i class="fas fa-star"></i>
        </div>
        
        <div class="favorites-container">
            <!-- قسم عملاء VIP والشركاء المميزين -->
            <div style="display:flex; justify-content:space-between; align-items:center; margin: 25px 0 15px;">
                <h3 class="section-title" style="margin:0;"><i class="fas fa-crown" style="color:#ffd700;"></i> كبار العملاء (VIP Clients)</h3>
                <a href="../clients/clients.php" class="fav-link">
                    إدارة العملاء <i class="fas fa-arrow-left"></i>
                </a>
            </div>

            <div class="cards-grid">
                <?php if (empty($vipClients)): ?>
                    <div class="fav-empty">
                        <i class="fas fa-user-slash" style="font-size:32px; color:#ffd700; margin-bottom:10px; display:block;"></i>
                        <p>لا يوجد عملاء مصنفين كـ VIP حالياً في النظام.</p>
                        <p style="font-size:12px; margin-top:5px;">يمكنك تعيين شارة VIP لأي عميل مباشرة من صفحة <a href="../clients/clients.php" style="color:#0481ff;">العملاء</a>.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($vipClients as $client): ?>
                        <div class="card client-card">
                            <div class="card-icon client-icon" style="background: linear-gradient(135deg, #ffd700, #ffaa00); color:#000;">
                                <i class="fas fa-crown"></i>
                            </div>
                            <div class="card-details">
                                <h4 style="font-size:15px; font-weight:700;"><?= e($client['name']) ?></h4>
                                <div style="margin: 4px 0;">
                                    <span class="badge" style="background:#ffd700; color:#000; font-size:11px; padding:2px 8px; border-radius:10px;">
                                        <i class="fas fa-gem"></i> شريك VIP
                                    </span>
                                </div>
                                <div class="fav-meta">
                                    <span>إجمالي المشتريات: </span>
                                    <strong class="fav-amount"><?= number_format((float)$client['total_spent'], 2) ?></strong>
                                    <small><?= e($currency) ?></small>
                                </div>
                                <div class="fav-submeta">
                                    <span>الطلبات المنفذة: <?= (int)$client['orders_count'] ?> طلب</span>
                                </div>
                            </div>
                            <div class="card-actions">
                                <?php if (!empty($client['phone'])): ?>
                                    <a href="tel:<?= e($client['phone']) ?>" class="btn-icon" title="اتصال: <?= e($client['phone']) ?>">
                                        <i class="fas fa-phone"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($client['email'])): ?>
                                    <a href="mailto:<?= e($client['email']) ?>" class="btn-icon" title="مراسلة: <?= e($client['email']) ?>">
                                        <i class="fas fa-envelope"></i>
                                    </a>
                                <?php endif; ?>
                                <a href="../orders/orders.php" class="btn-icon" title="إنشاء طلب مبيعات">
                                    <i class="fas fa-cart-plus"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- قسم المنتجات المراقبة في المخزون -->
            <div style="display:flex; justify-content:space-between; align-items:center; margin: 35px 0 15px;">
                <h3 class="section-title" style="margin:0;"><i class="fas fa-boxes-stacked" style="color:#00c6ff;"></i> منتجات قيد المراقبة المستمرة</h3>
                <a href="../Products/products.php" class="fav-link">
                    كافة المنتجات <i class="fas fa-arrow-left"></i>
                </a>
            </div>

            <div class="cards-grid">
                <?php if (empty($monitoredProducts)): ?>
                    <div class="fav-empty">
                        <i class="fas fa-box-open" style="font-size:32px; color:#00c6ff; margin-bottom:10px; display:block;"></i>
                        <p>لا توجد منتجات مسجلة في قاعدة البيانات حالياً.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($monitoredProducts as $prod): ?>
                        <?php 
                        $isLowStock = ((int)$prod['count'] <= (int)$prod['min_stock_alert']);
                        ?>
                        <div class="card product-card">
                            <div class="card-icon product-icon" style="background: <?= $isLowStock ? 'linear-gradient(135deg, #ff4d4f, #d63031)' : 'linear-gradient(135deg, #0481ff, #0056b3)' ?>;">
                                <i class="fas <?= $isLowStock ? 'fa-exclamation-triangle' : 'fa-box' ?>"></i>
                            </div>
                            <div class="card-details">
                                <h4 style="font-size:14px; font-weight:700;"><?= e($prod['name']) ?></h4>
                                <p class="specs" style="color:#aaa; font-size:12px;">
                                     <?= e($prod['category'] ?: 'عام') ?> | <code><?= e($prod['sku']) ?></code>
                                </p>
                                <div style="margin-top:4px;">
                                    <?php if ($isLowStock): ?>
                                        <span class="fav-stock-low">
                                            <i class="fas fa-exclamation-circle"></i> المخزون حرج: <?= (int)$prod['count'] ?> قطعة
                                        </span>
                                    <?php else: ?>
                                        <span class="fav-stock-ok">
                                            <i class="fas fa-check"></i> متوفر: <?= (int)$prod['count'] ?> قطعة
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="price" style="margin-top:4px;">
                                    <?= number_format((float)$prod['price'], 2) ?> <?= e($currency) ?>
                                </div>
                            </div>
                            <div class="card-actions">
                                <a href="../Products/products.php" class="btn-icon" title="تعديل وتفقد المخزون">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="../js/ui.js?v=15"></script>
</body>
</html>
