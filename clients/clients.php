<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

require_auth();
$currentUser = current_user();
$pdo = get_db_connection();

// معايير البحث والفلترة وترقيم الصفحات
$q     = trim($_GET['q'] ?? '');
$vip   = $_GET['vip'] ?? '';
$limit = 10;
$page  = max(1, (int)($_GET['page'] ?? 1));

$where  = [];
$params = [];

if ($q !== '') {
    $where[] = "(name LIKE :q_name OR phone LIKE :q_phone OR email LIKE :q_email)";
    $params[':q_name']  = '%' . $q . '%';
    $params[':q_phone'] = '%' . $q . '%';
    $params[':q_email'] = '%' . $q . '%';
}

if ($vip === '1') {
    $where[] = "is_vip = 1";
} elseif ($vip === '0') {
    $where[] = "is_vip = 0";
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// 1. حساب إجمالي عدد السجلات المطابقة
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM clients $whereClause");
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();

$totalPages = max(1, (int)ceil($totalRecords / $limit));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $limit;

// 2. جلب قائمة العملاء للصفحة الحالية باستخدام PDO
$dataSql = "SELECT id, name, phone, email, address, is_vip, notes, created_at 
            FROM clients 
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
$clients = $stmt->fetchAll();
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
    <link rel="stylesheet" href="../fonts/cairo.css?v=13">
    <link rel="stylesheet" href="../fontawesome/css/all.min.css?v=13">
    <link rel="stylesheet" href="../Css/sidebar.css?v=13">
    <link rel="stylesheet" href="../Css/ui.css?v=13">
    <link rel="stylesheet" href="css/clients.css?v=13">
    <link rel="stylesheet" href="../Css/theme.css?v=13">
    <link rel="stylesheet" href="../Css/responsive.css?v=13">
    <title>إدارة العملاء</title>
</head>
<body>
    <?php
    $activePage = 'clients';
    $basePath   = '../';
    require __DIR__ . '/../includes/sidebar.php';
    ?>

    <div class="content">
        <div class="title-info">
            <p>إدارة العملاء</p>
            <i class="fas fa-user-group"></i>
        </div>

        <div class="body">
            <form id="form" method="post">
                <?php echo csrf_field(); ?>
                <input type="hidden" id="client_id" name="id" value="">

                <div class="form-group">
                    <label for="name">اسم العميل <span style="color:#ff4d4f;">*</span></label>
                    <input type="text" id="name" name="name" placeholder="أدخل اسم العميل" required>
                </div>

                <div class="form-group">
                    <label for="phone">رقم الهاتف <span style="color:#ff4d4f;">*</span></label>
                    <input type="text" id="phone" name="phone" placeholder="أدخل رقم الهاتف" required>
                </div>

                <div class="form-group">
                    <label for="email">البريد الإلكتروني</label>
                    <input type="email" id="email" name="email" placeholder="example@store.com">
                </div>

                <div class="form-group">
                    <label for="address">العنوان / المدينة</label>
                    <input type="text" id="address" name="address" placeholder="المدينة، الحي، الشارع">
                </div>

                <div class="form-group form-group-checkbox">
                    <label for="is_vip" style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer; margin: 0;">
                        <input type="checkbox" id="is_vip" name="is_vip" value="1">
                        <span style="color: #ffd700; font-weight: bold;"><i class="fas fa-crown"></i> عميل مميز (VIP)</span>
                    </label>
                </div>

                <div class="form-group form-group-buttons">
                    <button type="submit" id="submit_btn">إضافة عميل</button>
                    <button type="button" id="cancel_btn" style="display: none; background: #555;">إلغاء التعديل</button>
                </div>
            </form>
        </div>

        <!-- شريط البحث والتصفية -->
        <div class="filter-bar">
            <form method="GET" action="clients.php" class="filter-form">
                <input type="text" name="q" class="filter-input" placeholder="بحث بالاسم، رقم الهاتف، أو البريد..." value="<?php echo e($q); ?>">
                
                <select name="vip" class="filter-select">
                    <option value="">جميع فئات العملاء</option>
                    <option value="1" <?php echo $vip === '1' ? 'selected' : ''; ?>>عملاء VIP فقط ⭐</option>
                    <option value="0" <?php echo $vip === '0' ? 'selected' : ''; ?>>عملاء عاديين</option>
                </select>

                <div class="filter-actions">
                    <button type="submit" class="btn-filter">
                        <i class="fas fa-search"></i> بحث
                    </button>
                    <?php if ($q !== '' || $vip !== ''): ?>
                        <a href="clients.php" class="btn-filter-reset">
                            <i class="fas fa-times"></i> إلغاء التصفية
                        </a>
                    <?php endif; ?>
                </div>
            </form>

            <div class="filter-summary">
                إجمالي النتائج: <strong><?php echo $totalRecords; ?></strong> عميل
            </div>
        </div>

        <div class="table-responsive">
            <table id="table">
                <thead>
                    <tr>
                        <th>الاسم</th>
                        <th>الهاتف</th>
                        <th>البريد الإلكتروني</th>
                        <th>العنوان</th>
                        <th>التصنيف</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($clients)): ?>
                        <?php foreach ($clients as $client): ?>
                            <tr data-id="<?php echo (int)$client['id']; ?>">
                                <td><?php echo e($client['name']); ?></td>
                                <td><?php echo e($client['phone']); ?></td>
                                <td><?php echo e($client['email'] ?: '—'); ?></td>
                                <td><?php echo e($client['address'] ?: '—'); ?></td>
                                <td>
                                    <?php if (!empty($client['is_vip'])): ?>
                                        <span class="badge-vip"><i class="fas fa-crown"></i> VIP</span>
                                    <?php else: ?>
                                        <span style="color: #888; font-size: 12px;">عادي</span>
                                    <?php endif; ?>
                                </td>
                                <td class="actions">
                                    <button type="button" class="edit"
                                        data-id="<?php echo (int)$client['id']; ?>"
                                        data-name="<?php echo e($client['name']); ?>"
                                        data-phone="<?php echo e($client['phone']); ?>"
                                        data-email="<?php echo e($client['email']); ?>"
                                        data-address="<?php echo e($client['address']); ?>"
                                        data-vip="<?php echo (int)$client['is_vip']; ?>">
                                        تعديل
                                    </button>
                                    <?php if (has_role('admin')): ?>
                                        <button type="button" class="delete" data-id="<?php echo (int)$client['id']; ?>">حذف</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 25px; color: #8899aa;">
                                <?php if ($q !== '' || $vip !== ''): ?>
                                    <i class="fas fa-search" style="font-size: 24px; margin-bottom: 8px; display: block; color: #0481ff;"></i>
                                    لا توجد نتائج مطابقة لبحثك. <a href="clients.php" style="color: #00c6ff; text-decoration: underline;">إعادة ضبط البحث</a>
                                <?php else: ?>
                                    لا يوجد عملاء مسجلين بعد.
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
                عرض الصفحة <strong><?php echo $page; ?></strong> من إجمالي <strong><?php echo $totalPages; ?></strong> صفحات (<?php echo $totalRecords; ?> عميل)
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
    <script src="../js/ui.js?v=13"></script>
    <script src="js/clients.js?v=13"></script>
</body>
</html>
