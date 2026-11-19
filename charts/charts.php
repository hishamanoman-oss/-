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
    <link rel="stylesheet" href="../fonts/cairo.css?v=2">
    <link rel="stylesheet" href="../fontawesome/css/all.min.css?v=2">
    <link rel="stylesheet" href="css/charts.css?v=2">
    <title>الإحصائيات</title>
</head>
<body>
    <div class="menu">
        <ul>
            <li class="profile">
                <div class="img-box">
                    <img src="../work.jpg" alt="profile">
                </div>
                <div>
                    <h2><?php echo e($currentUser['full_name'] ?: $currentUser['email']); ?></h2>
                    <span style="font-size: 11px; color: #00c6ff; display: block;"><?php echo $currentUser['role'] === 'admin' ? 'مدير النظام' : 'موظف مبيعات'; ?></span>
                </div>
            </li>
            <li><a href="../index.php"><i class="fas fa-home"></i><p>لوحة التحكم</p></a></li>
            <li><a href="../clients/clients.php"><i class="fas fa-user-group"></i><p>العملاء</p></a></li>
            <li><a href="../Products/products.php"><i class="fas fa-table"></i><p>المنتجات</p></a></li>
            <li><a href="charts.php"><i class="fas fa-chart-pie"></i><p>الإحصائيات</p></a></li>
            <li><a href="../posts/posts.php"><i class="fas fa-pen"></i><p>المنشورات</p></a></li>
            <li><a href="../favorite/favorite.php"><i class="fas fa-star"></i><p>المفضلة</p></a></li>
            <li><a href="../settings/settings.php"><i class="fas fa-cog"></i><p>الإعدادات</p></a></li>
            <li class="log-out"><a href="../logout.php"><i class="fas fa-sign-out-alt"></i><p>تسجيل الخروج</p></a></li>
        </ul>
    </div>
    <div class="content">
        <div class="title-info">
            <p>الإحصائيات والرسوم البيانية</p>
            <i class="fas fa-chart-pie"></i>
        </div>
        <div class="charts-grid">
            <div class="chart-card">
                <h3>العملاء الجدد (آخر 30 يوم)</h3>
                <div class="chart-container">
                    <canvas id="newClientsChart"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <h3>المنتجات الأكثر مبيعاً</h3>
                <div class="chart-container">
                    <canvas id="topProductsChart"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <h3>تصنيف العملاء</h3>
                <div class="chart-container">
                    <canvas id="clientSegmentsChart"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <h3>الإيرادات الشهرية (بالآلاف)</h3>
                <div class="chart-container">
                    <canvas id="monthlyRevenueChart"></canvas>
                </div>
            </div>
        </div>
    </div>
<script src="../js/chart.min.js"></script>
<script src="js/charts.js"></script>
</body>
</html>
