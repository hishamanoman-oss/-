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
    <link rel="stylesheet" href="fonts/cairo.css?v=4">
    <link rel="stylesheet" href="fontawesome/css/all.min.css?v=4">
    <link rel="stylesheet" href="Css/work3.css?v=4">
    <title>لوحة التحكم</title>
</head>
<body>
    <?php
    $activePage = 'dashboard';
    $basePath = '';
    require __DIR__ . '/includes/sidebar.php';
    ?>

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
