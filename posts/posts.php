<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_auth();
$currentUser = current_user();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../fonts/cairo.css?v=4">
    <link rel="stylesheet" href="../fontawesome/css/all.min.css?v=4">
    <link rel="stylesheet" href="css/posts.css?v=4">
    <title>المنشورات</title>
</head>
<body>
    <?php
    $activePage = 'posts';
    $basePath   = '../';
    require __DIR__ . '/../includes/sidebar.php';
    ?>

     <div class="content">
        <div class="title-info">
            <p>المنشورات</p>
            <i class="fas fa-pen"></i>
        </div>
        <div class="container">
            <!-- حاوية المنشورات -->
            <div class="posts-grid" id="postsContainer">
                <!-- Sample Post 1: Product Update -->
                <div class="post-card">
                    <div class="post-header">
                        <div class="user-info">
                            <div class="user-icon"><i class="fas fa-boxes"></i></div>
                            <div>
                                <h3>مدير المخزون</h3>
                                <span>منذ 30 دقيقة</span>
                            </div>
                        </div>
                        <i class="fas fa-ellipsis-h"></i>
                    </div>
                    <div class="post-content rtl-text">
                        <p>تم تحديث قائمة المنتجات بنجاح. تم إضافة 50 وحدة جديدة من "سماعات بلوتوث برو" إلى المخزون. يرجى مراجعة قسم المنتجات للتفاصيل.</p>
                    </div>
                    <div class="post-actions">
                        <div class="action-btn"><i class="far fa-heart"></i> إعجاب</div>
                        <div class="action-btn"><i class="far fa-comment"></i> تعليق</div>
                        <div class="action-btn"><i class="fas fa-share"></i> مشاركة</div>
                    </div>
                </div>

                <!-- Sample Post 2: Sales Chart Update -->
                 <div class="post-card">
                    <div class="post-header">
                        <div class="user-info">
                            <div class="user-icon"><i class="fas fa-chart-line"></i></div>
                            <div>
                                <h3>فريق المبيعات</h3>
                                <span>منذ 3 ساعات</span>
                            </div>
                        </div>
                        <i class="fas fa-ellipsis-h"></i>
                    </div>
                    <div class="post-content rtl-text">
                        <p>تقرير رائع! 🚀 مبيعات هذا الشهر تجاوزت التوقعات وحققنا زيادة بنسبة 15% مقارنة بالشهر الماضي. الرسم البياني يوضح نمواً ممتازاً في الإيرادات.</p>
                    </div>
                    <div class="post-actions">
                        <div class="action-btn"><i class="far fa-heart"></i> إعجاب</div>
                        <div class="action-btn"><i class="far fa-comment"></i> تعليق</div>
                        <div class="action-btn"><i class="fas fa-share"></i> مشاركة</div>
                    </div>
                </div>

                <!-- Sample Post 3: New Client Welcome -->
                 <div class="post-card">
                    <div class="post-header">
                        <div class="user-info">
                            <div class="user-icon"><i class="fas fa-user-check"></i></div>
                            <div>
                                <h3>خدمة العملاء</h3>
                                <span>أمس الساعة 4:00 م</span>
                            </div>
                        </div>
                        <i class="fas fa-ellipsis-h"></i>
                    </div>
                    <div class="post-content rtl-text">
                        <p>نرحب بانضمام "شركة الأفق للتجارة" إلى قائمة عملائنا المميزين. نتطلع لشراكة مثمرة وناجحة معكم! 🤝</p>
                    </div>
                    <div class="post-actions">
                        <div class="action-btn"><i class="far fa-heart"></i> إعجاب</div>
                        <div class="action-btn"><i class="far fa-comment"></i> تعليق</div>
                        <div class="action-btn"><i class="fas fa-share"></i> مشاركة</div>
                    </div>
                </div>
            </div>
        </div>
     </div>
<script src="../js/jquery-3.7.1.js"></script>
</body>
</html>
