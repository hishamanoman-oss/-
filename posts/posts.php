<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

require_auth();
$currentUser = current_user();

$pdo = get_db_connection();

// جلب التعميمات والتوجيهات من قاعدة البيانات مع بيانات الناشر
$stmt = $pdo->query("
    SELECT 
        p.id,
        p.user_id,
        p.title,
        p.content,
        p.status,
        p.created_at,
        u.full_name AS author_name,
        u.role AS author_role
    FROM posts p
    JOIN users u ON p.user_id = u.id
    ORDER BY p.created_at DESC, p.id DESC
");
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../fonts/cairo.css?v=8">
    <link rel="stylesheet" href="../fontawesome/css/all.min.css?v=8">
    <link rel="stylesheet" href="../Css/sidebar.css?v=8">
    <link rel="stylesheet" href="../Css/ui.css?v=8">
    <link rel="stylesheet" href="../Css/responsive.css?v=8">
    <link rel="stylesheet" href="css/posts.css?v=8">
    <title>التوجيهات والتعميمات الإدارية</title>
</head>
<body>
    <?php
    $activePage = 'posts';
    $basePath   = '../';
    require __DIR__ . '/../includes/sidebar.php';
    ?>

    <div class="content">
        <div class="title-info">
            <p><i class="fas fa-bullhorn" style="margin-left:8px;"></i> التوجيهات والتعميمات الإدارية للمتجر</p>
            <i class="fas fa-pen-nib"></i>
        </div>

        <div class="container">
            <!-- رسائل النجاح أو الخطأ -->
            <?php if ($flashSuccess): ?>
                <div class="alert-flash alert-success">
                    <i class="fas fa-check-circle" style="margin-left:6px;"></i> <?= e($flashSuccess) ?>
                </div>
            <?php endif; ?>

            <?php if ($flashError): ?>
                <div class="alert-flash alert-danger">
                    <i class="fas fa-exclamation-triangle" style="margin-left:6px;"></i> <?= e($flashError) ?>
                </div>
            <?php endif; ?>

            <!-- نموذج إضافة تعميم جديد (متاح للمدير فقط) -->
            <?php if (has_role('admin')): ?>
                <div class="create-post-card">
                    <h3><i class="fas fa-plus-circle"></i> نشر تعميم / توجيه إداري جديد</h3>
                    <form action="posts_process.php" method="POST">
                        <?= csrf_field() ?>
                        <div class="form-group">
                            <label for="postTitle">عنوان التعميم</label>
                            <input type="text" id="postTitle" name="title" placeholder="مثال: تعليمات جرد المخزون لشهر نوفمبر" required maxlength="200">
                        </div>
                        <div class="form-group">
                            <label for="postContent">نص وتفاصيل التعميم</label>
                            <textarea id="postContent" name="content" rows="4" placeholder="اكتب التعليمات والتوجيهات الموجهة لفريق العمل..." required></textarea>
                        </div>
                        <button type="submit" class="btn-publish">
                            <i class="fas fa-paper-plane"></i> نشر التعميم فوراً
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- قائمة التعميمات المنشورة -->
            <div class="posts-grid" id="postsContainer">
                <?php if (empty($posts)): ?>
                    <div class="post-card" style="text-align:center; padding:40px; color:#555;">
                        <i class="fas fa-inbox" style="font-size:40px; color:#0481ff; margin-bottom:15px; display:block;"></i>
                        <h3 style="color:#333; margin-bottom:8px;">لا توجد تعميمات أو توجيهات منشورة حتى الآن</h3>
                        <p style="color:#777;">سيتم عرض أي تعميمات إدارية جديدة هنا فور اعتمادها ونشرها من الإدارة.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($posts as $post): ?>
                        <div class="post-card">
                            <div class="post-header" style="display:flex; justify-content:space-between; align-items:center;">
                                <div class="user-info" style="display:flex; align-items:center; gap:15px;">
                                    <div class="user-icon">
                                        <i class="fas <?= $post['author_role'] === 'admin' ? 'fa-user-shield' : 'fa-user' ?>"></i>
                                    </div>
                                    <div>
                                        <h3 style="display:inline-block;"><?= e($post['author_name']) ?></h3>
                                        <span class="badge-role <?= $post['author_role'] === 'admin' ? 'badge-admin' : 'badge-staff' ?>">
                                            <?= $post['author_role'] === 'admin' ? 'مدير عام' : 'موظف مبيعات' ?>
                                        </span>
                                        <div style="font-size:12px; color:#666; margin-top:3px;">
                                            <i class="far fa-clock"></i> <?= date('Y-m-d H:i', strtotime($post['created_at'])) ?>
                                        </div>
                                    </div>
                                </div>

                                <?php if (has_role('admin')): ?>
                                    <form action="delete_post.php" method="POST" style="margin:0;" data-confirm="هل أنت متأكد من رغبتك في حذف هذا التعميم نهائياً؟">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
                                        <button type="submit" class="btn-delete-post" title="حذف التعميم">
                                            <i class="fas fa-trash-alt"></i> حذف
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>

                            <div class="post-content rtl-text">
                                <h4 style="font-size:1.15rem; color:#0481ff; margin-bottom:10px; font-weight:700;">
                                    <i class="fas fa-tag" style="margin-left:6px; font-size:14px;"></i> <?= e($post['title']) ?>
                                </h4>
                                <p style="color:#222; font-size:1rem; line-height:1.8;"><?= nl2br(e($post['content'])) ?></p>
                            </div>

                            <div class="post-actions" style="justify-content:flex-end;">
                                <span style="font-size:12px; color:#777; display:flex; align-items:center; gap:5px;">
                                    <i class="fas fa-check-double" style="color:#28a745;"></i> تعميم إداري معتمد
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="../js/ui.js?v=8"></script>
</body>
</html>
