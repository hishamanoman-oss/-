<?php
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/auth.php';

require_auth();
$currentUser = current_user();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="fonts/cairo.css?v=3">
    <link rel="stylesheet" href="fontawesome/css/all.min.css?v=3">
    <link rel="stylesheet" href="Css/work3.css?v=3">
    <title>لوحة التحكم</title>
</head>
<body>
    <div class="menu">
        <ul>
            <li class="profile">
                <div class="img-box">
                    <img src="work.jpg" alt="profile">
                </div>
                <!-- نعرض الاسم أو البريد مع الدور من الجلسة بأمان -->
                <div>
                    <h2><?php echo e($currentUser['full_name'] ?: $currentUser['email']); ?></h2>
                    <span style="font-size: 11px; color: #00c6ff; display: block;"><?php echo $currentUser['role'] === 'admin' ? 'مدير النظام' : 'موظف مبيعات'; ?></span>
                </div>
            </li>
            <li>
                <a href="index.php">
                    <i class="fas fa-home"></i>
                    <p>لوحة التحكم</p>
                </a>
            </li>
            <li>
                <a href="clients/clients.php">
                    <i class="fas fa-user-group"></i>
                    <p>العملاء</p>
                </a>
            </li>
            <li>
                <a href="Products/products.php">
                    <i class="fas fa-table"></i>
                    <p>المنتجات</p>
                </a>
            </li>
            <li>
                <a href="charts/charts.php">
                    <i class="fas fa-chart-pie"></i>
                    <p>الإحصائيات</p>
                </a>
            </li>
            <li>
                <a href="posts/posts.php">
                    <i class="fas fa-pen"></i>
                    <p>المنشورات</p>
                </a>
            </li>
            <li>
                <a href="favorite/favorite.php">
                    <i class="fas fa-star"></i>
                    <p>المفضلة</p>
                </a>
            </li>
            <li>
                <a href="settings/settings.php">
                    <i class="fas fa-cog"></i>
                    <p>الإعدادات</p>
                </a>
            </li>
            <li class="log-out">
                <a href="logout.php" id="log-out">
                    <i class="fas fa-sign-out-alt"></i>
                    <p>تسجيل الخروج</p>
                </a>
            </li>
        </ul>
    </div>

    <div class="content">
        <div class="title-info">
            <p>لوحة التحكم</p>
            <i class="fas fa-chart-bar"></i>
        </div>

        <div class="data-info">
            <div class="box">
                <i class="fas fa-user"></i>
                <div class="data">
                    <p>المستخدمين</p>
                    <span>100</span>
                </div>
            </div>
            <div class="box">
                <i class="fas fa-pen"></i>
                <div class="data">
                    <p>المنشورات</p>
                    <span>50</span>
                </div>
            </div>
            <div class="box">
                <i class="fas fa-table"></i>
                <div class="data">
                    <p>المنتجات</p>
                    <span>35</span>
                </div>
            </div>
            <div class="box">
                <i class="fas fa-dollar"></i>
                <div class="data">
                    <p>الإيرادات</p>
                    <span>$1040</span>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
