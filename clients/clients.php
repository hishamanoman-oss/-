<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

require_auth();
$currentUser = current_user();
$pdo = get_db_connection();

// جلب قائمة العملاء باستخدام PDO
$stmt = $pdo->query("SELECT id, name, phone, email, address, is_vip, notes, created_at FROM clients ORDER BY id DESC");
$clients = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <link rel="stylesheet" href="../fonts/cairo.css?v=5">
    <link rel="stylesheet" href="../fontawesome/css/all.min.css?v=5">
    <link rel="stylesheet" href="css/clients.css?v=5">
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

                <div>
                    <label for="name">اسم العميل <span style="color:#ff4d4f;">*</span></label>
                    <input type="text" id="name" name="name" placeholder="أدخل اسم العميل" required>
                </div>

                <div>
                    <label for="phone">رقم الهاتف <span style="color:#ff4d4f;">*</span></label>
                    <input type="text" id="phone" name="phone" placeholder="أدخل رقم الهاتف" required>
                </div>

                <div>
                    <label for="email">البريد الإلكتروني</label>
                    <input type="email" id="email" name="email" placeholder="example@store.com">
                </div>

                <div>
                    <label for="address">العنوان / المدينة</label>
                    <input type="text" id="address" name="address" placeholder="المدينة، الحي، الشارع">
                </div>

                <div style="flex: 0 0 auto; display: flex; align-items: center; justify-content: center; padding-bottom: 8px;">
                    <label for="is_vip" style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer; margin: 0;">
                        <input type="checkbox" id="is_vip" name="is_vip" value="1">
                        <span style="color: #ffd700; font-weight: bold;"><i class="fas fa-crown"></i> عميل مميز (VIP)</span>
                    </label>
                </div>

                <div style="flex: 0 0 auto; display: flex; gap: 8px;">
                    <button type="submit" id="submit_btn">إضافة عميل</button>
                    <button type="button" id="cancel_btn" style="display: none; background: #555;">إلغاء التعديل</button>
                </div>
            </form>
        </div>

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
                    <tr><td colspan="6">لا يوجد عملاء مسجلين بعد</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script src="../js/jquery-3.7.1.js"></script>
    <script src="js/clients.js?v=5"></script>
</body>
</html>
