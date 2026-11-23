<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

require_auth();
$currentUser = current_user();
$pdo = get_db_connection();

// جلب المنتجات باستخدام PDO
$stmt = $pdo->query("SELECT id, name, sku, category, price, count, min_stock_alert, status, created_at FROM products ORDER BY id DESC");
$products = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <link rel="stylesheet" href="../fonts/cairo.css?v=7">
    <link rel="stylesheet" href="../fontawesome/css/all.min.css?v=7">
    <link rel="stylesheet" href="../Css/sidebar.css?v=7">
    <link rel="stylesheet" href="css/products.css?v=7">
    <title>إدارة المنتجات والمخزون</title>
</head>
<body>
    <?php
    $activePage = 'products';
    $basePath   = '../';
    require __DIR__ . '/../includes/sidebar.php';
    ?>

    <div class="content">
        <div class="title-info">
            <p>إدارة المنتجات والمخزون</p>
            <i class="fas fa-table"></i>
        </div>

        <div class="body">
            <form id="form" method="post">
                <?php echo csrf_field(); ?>
                <input type="hidden" id="product_id" name="id" value="">

                <div>
                    <label for="name">اسم المنتج <span style="color:#ff4d4f;">*</span></label>
                    <input type="text" id="name" name="name" placeholder="أدخل اسم المنتج" required>
                </div>

                <div>
                    <label for="sku">رمز المنتج SKU <span style="color:#ff4d4f;">*</span></label>
                    <input type="text" id="sku" name="sku" placeholder="مثال: PRD-1001" required>
                </div>

                <div>
                    <label for="category">القسم / التصنيف</label>
                    <input type="text" id="category" name="category" placeholder="مثال: إلكترونيات، عطور">
                </div>

                <div>
                    <label for="price">السعر (ر.س) <span style="color:#ff4d4f;">*</span></label>
                    <input type="number" id="price" name="price" step="0.01" min="0" placeholder="0.00" required>
                </div>

                <div>
                    <label for="count">الكمية بالمخزن <span style="color:#ff4d4f;">*</span></label>
                    <input type="number" id="count" name="count" min="0" placeholder="0" required>
                </div>

                <div>
                    <label for="min_stock_alert">حد تنبيه النقص</label>
                    <input type="number" id="min_stock_alert" name="min_stock_alert" min="0" value="5" placeholder="5" required>
                </div>

                <div style="flex: 0 0 auto; display: flex; gap: 8px;">
                    <button type="submit" id="submit_btn">إضافة منتج</button>
                    <button type="button" id="cancel_btn" style="display: none; background: #555;">إلغاء التعديل</button>
                </div>
            </form>
        </div>

        <table id="table">
            <thead>
                <tr>
                    <th>المنتج</th>
                    <th>رمز SKU</th>
                    <th>القسم</th>
                    <th>السعر</th>
                    <th>الكمية</th>
                    <th>حالة المخزون</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($products)): ?>
                    <?php foreach ($products as $product): ?>
                        <?php 
                        $isLowStock = (int)$product['count'] <= (int)$product['min_stock_alert'];
                        ?>
                        <tr data-id="<?php echo (int)$product['id']; ?>">
                            <td><strong><?php echo e($product['name']); ?></strong></td>
                            <td><code style="background: rgba(0,0,0,0.4); padding: 3px 8px; border-radius: 4px; color: #00c6ff; font-weight: bold;"><?php echo e($product['sku']); ?></code></td>
                            <td><?php echo e($product['category'] ?: 'عام'); ?></td>
                            <td><span class="price"><?php echo number_format((float)$product['price'], 2); ?> ر.س</span></td>
                            <td><span class="count"><?php echo (int)$product['count']; ?></span></td>
                            <td>
                                <?php if ($isLowStock): ?>
                                    <span class="badge-low-stock"><i class="fas fa-exclamation-triangle"></i> منخفض (≤ <?php echo (int)$product['min_stock_alert']; ?>)</span>
                                <?php else: ?>
                                    <span class="badge-in-stock"><i class="fas fa-check-circle"></i> متوفر</span>
                                <?php endif; ?>
                            </td>
                            <td class="actions">
                                <button type="button" class="edit"
                                    data-id="<?php echo (int)$product['id']; ?>"
                                    data-name="<?php echo e($product['name']); ?>"
                                    data-sku="<?php echo e($product['sku']); ?>"
                                    data-category="<?php echo e($product['category']); ?>"
                                    data-price="<?php echo (float)$product['price']; ?>"
                                    data-count="<?php echo (int)$product['count']; ?>"
                                    data-min-stock="<?php echo (int)$product['min_stock_alert']; ?>">
                                    تعديل
                                </button>
                                <?php if (has_role('admin')): ?>
                                    <button type="button" class="delete" data-id="<?php echo (int)$product['id']; ?>">حذف</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7">لا توجد منتجات مسجلة في المخزن</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script src="../js/jquery-3.7.1.js"></script>
    <script src="js/products.js?v=7"></script>
</body>
</html>
