<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

require_auth();
$currentUser = current_user();
$pdo = get_db_connection();

// جلب قائمة التصنيفات / الأقسام الحالية ديناميكياً للفلتر
$catStmt = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category ASC");
$categoriesList = $catStmt->fetchAll(PDO::FETCH_COLUMN);

// معايير البحث والفلترة وترقيم الصفحات
$q        = trim($_GET['q'] ?? '');
$category = trim($_GET['category'] ?? '');
$stock    = $_GET['stock'] ?? '';
$limit    = 10;
$page     = max(1, (int)($_GET['page'] ?? 1));

$where  = [];
$params = [];

if ($q !== '') {
    $where[] = "(name LIKE :q_name OR sku LIKE :q_sku)";
    $params[':q_name'] = '%' . $q . '%';
    $params[':q_sku']  = '%' . $q . '%';
}

if ($category !== '') {
    $where[] = "category = :category";
    $params[':category'] = $category;
}

if ($stock === 'low') {
    $where[] = "count <= min_stock_alert";
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// 1. حساب إجمالي عدد السجلات المطابقة
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM products $whereClause");
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();

$totalPages = max(1, (int)ceil($totalRecords / $limit));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $limit;

// 2. جلب المنتجات باستخدام PDO للصفحة الحالية
$dataSql = "SELECT id, name, sku, category, price, count, min_stock_alert, status, created_at 
            FROM products 
            $whereClause 
            ORDER BY id DESC 
            LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($dataSql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v, PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
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
    <link rel="stylesheet" href="css/products.css?v=15">
    <link rel="stylesheet" href="../Css/theme.css?v=15">
    <link rel="stylesheet" href="../Css/responsive.css?v=15">
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

                <div class="form-group">
                    <label for="name">اسم المنتج <span style="color:#ff4d4f;">*</span></label>
                    <input type="text" id="name" name="name" placeholder="أدخل اسم المنتج" required>
                </div>

                <div class="form-group">
                    <label for="sku">رمز المنتج SKU <span style="color:#ff4d4f;">*</span></label>
                    <input type="text" id="sku" name="sku" placeholder="مثال: PRD-1001" required>
                </div>

                <div class="form-group">
                    <label for="category">القسم / التصنيف</label>
                    <input type="text" id="category" name="category" placeholder="مثال: إلكترونيات، عطور">
                </div>

                <div class="form-group">
                    <label for="price">السعر (ر.س) <span style="color:#ff4d4f;">*</span></label>
                    <input type="number" id="price" name="price" step="0.01" min="0" placeholder="0.00" required>
                </div>

                <div class="form-group">
                    <label for="count">الكمية بالمخزن <span style="color:#ff4d4f;">*</span></label>
                    <input type="number" id="count" name="count" min="0" placeholder="0" required>
                </div>

                <div class="form-group">
                    <label for="min_stock_alert">حد تنبيه النقص</label>
                    <input type="number" id="min_stock_alert" name="min_stock_alert" min="0" value="5" placeholder="5" required>
                </div>

                <div class="form-group form-group-buttons">
                    <button type="submit" id="submit_btn">إضافة منتج</button>
                    <button type="button" id="cancel_btn" style="display: none; background: #555;">إلغاء التعديل</button>
                </div>
            </form>
        </div>

        <!-- شريط البحث والتصفية -->
        <div class="filter-bar">
            <form method="GET" action="products.php" class="filter-form">
                <input type="text" name="q" class="filter-input" placeholder="بحث باسم المنتج أو رمز SKU..." value="<?php echo e($q); ?>">
                
                <select name="category" class="filter-select">
                    <option value="">جميع الأقسام</option>
                    <?php foreach ($categoriesList as $cat): ?>
                        <option value="<?php echo e($cat); ?>" <?php echo $category === $cat ? 'selected' : ''; ?>>
                            <?php echo e($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="stock" class="filter-select">
                    <option value="">كافة مستويات المخزون</option>
                    <option value="low" <?php echo $stock === 'low' ? 'selected' : ''; ?>>مخزون منخفض فقط (حرج) ⚠️</option>
                </select>

                <div class="filter-actions">
                    <button type="submit" class="btn-filter">
                        <i class="fas fa-search"></i> بحث
                    </button>
                    <?php if ($q !== '' || $category !== '' || $stock !== ''): ?>
                        <a href="products.php" class="btn-filter-reset">
                            <i class="fas fa-times"></i> إلغاء التصفية
                        </a>
                    <?php endif; ?>
                </div>
            </form>

            <div class="filter-summary">
                إجمالي النتائج: <strong><?php echo $totalRecords; ?></strong> منتج
            </div>
        </div>

        <div class="table-responsive">
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
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 25px; color: #8899aa;">
                                <?php if ($q !== '' || $category !== '' || $stock !== ''): ?>
                                    <i class="fas fa-search" style="font-size: 24px; margin-bottom: 8px; display: block; color: #0481ff;"></i>
                                    لا توجد منتجات مطابقة لبحثك. <a href="products.php" style="color: #00c6ff; text-decoration: underline;">إعادة ضبط البحث</a>
                                <?php else: ?>
                                    لا توجد منتجات مسجلة في المخزن بعد.
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- شريط ترقيم الصفحات -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination-container">
            <div class="pagination-info">
                عرض الصفحة <strong><?php echo $page; ?></strong> من إجمالي <strong><?php echo $totalPages; ?></strong> صفحات (<?php echo $totalRecords; ?> منتج)
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

    <script src="../js/jquery-3.7.1.js"></script>
    <script src="../js/ui.js?v=15"></script>
    <script src="js/products.js?v=15"></script>
</body>
</html>
