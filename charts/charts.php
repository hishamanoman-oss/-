<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin', 'staff']);
$currentUser = current_user();
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
    <link rel="stylesheet" href="css/charts.css?v=8">
    <title>الإحصائيات والرسوم البيانية</title>
</head>
<body>
    <?php
    $activePage = 'charts';
    $basePath   = '../';
    require __DIR__ . '/../includes/sidebar.php';
    ?>

    <div class="content">
        <div class="title-info">
            <p><i class="fas fa-chart-pie" style="margin-left:8px;"></i> الإحصائيات والرسوم البيانية الحية</p>
            <i class="fas fa-chart-line"></i>
        </div>

        <div class="charts-grid">
            <!-- 1. مسار نمو المبيعات -->
            <div class="chart-card" style="grid-column: 1 / -1;">
                <h3><i class="fas fa-chart-line" style="color:#00c6ff; margin-left:6px;"></i> مسار نمو المبيعات اليومية</h3>
                <div class="chart-container" id="salesTrendContainer">
                    <canvas id="salesTrendChart"></canvas>
                </div>
            </div>

            <!-- 2. المنتجات الأكثر مبيعاً -->
            <div class="chart-card">
                <h3><i class="fas fa-trophy" style="color:#ffd700; margin-left:6px;"></i> أعلى المنتجات مبيعاً</h3>
                <div class="chart-container" id="topProductsContainer">
                    <canvas id="topProductsChart"></canvas>
                </div>
            </div>

            <!-- 3. توزيع المخزون حسب الأقسام -->
            <div class="chart-card">
                <h3><i class="fas fa-layer-group" style="color:#00ff88; margin-left:6px;"></i> توزيع المخزون حسب الأقسام</h3>
                <div class="chart-container" id="stockByCategoryContainer">
                    <canvas id="stockByCategoryChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script src="../js/chart.min.js"></script>
    <script src="../js/ui.js?v=8"></script>
    <script src="js/charts.js?v=8"></script>
</body>
</html>
