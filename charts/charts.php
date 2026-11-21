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
    <link rel="stylesheet" href="css/charts.css?v=4">
    <title>الإحصائيات</title>
</head>
<body>
    <?php
    $activePage = 'charts';
    $basePath   = '../';
    require __DIR__ . '/../includes/sidebar.php';
    ?>

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
