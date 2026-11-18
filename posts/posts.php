<?php
session_start();
if(!isset($_SESSION['email'])){
    header("Location: ../Login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/posts.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>Posts</title>
</head>
<body>
    <div class="menu">
        <ul>
            <li class="profile">
                <div class="img-box">
                    <img src="../work.jpg" alt="profile">
                </div>
                <h2><?php echo $_SESSION['email']; ?></h2>
            </li>
            <li><a href="../index.php"><i class="fas fa-home"></i><p>dashboard</p></a></li>
            <li><a href="../clients/clients.php"><i class="fas fa-user-group"></i><p>clients</p></a></li>
            <li><a href="../Products/products.php"><i class="fas fa-table"></i><p>products</p></a></li>
            <li><a href="../charts/charts.php"><i class="fas fa-chart-pie"></i><p>charts</p></a></li>
            <li><a href="posts.php"><i class="fas fa-pen"></i><p>posts</p></a></li>
            <li><a href="../favorite/favorite.php"><i class="fas fa-star"></i><p>favorite</p></a></li>
            <li><a href="../settings/settings.php"><i class="fas fa-cog"></i><p>settings</p></a></li>
            <li class="log-out"><a href="../logout.php"><i class="fas fa-sign-out"></i><p>log out</p></a></li>
        </ul>
    </div>
     <div class="content">
        <div class="title-info">
            <p>Posts</p>
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
                </div>            </div>
        </div>
     </div>
<script src="../js/jquery-3.7.1.js"></script>
</body>
</html>
