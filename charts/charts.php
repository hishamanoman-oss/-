<?php
session_start();
if(!isset($_SESSION['email'])){
    header("Location: ../Login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/charts.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>الإحصائيات</title>
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
            <li><a href="../index.php"><i class="fas fa-home"></i><p>لوحة التحكم</p></a></li>
            <li><a href="../clients/clients.php"><i class="fas fa-user-group"></i><p>العملاء</p></a></li>
            <li><a href="../Products/products.php"><i class="fas fa-table"></i><p>المنتجات</p></a></li>
            <li><a href="charts.php"><i class="fas fa-chart-pie"></i><p>الإحصائيات</p></a></li>
            <li><a href="../posts/posts.php"><i class="fas fa-pen"></i><p>المنشورات</p></a></li>
            <li><a href="../favorite/favorite.php"><i class="fas fa-star"></i><p>المفضلة</p></a></li>
            <li><a href="../settings/settings.php"><i class="fas fa-cog"></i><p>الإعدادات</p></a></li>
            <li class="log-out"><a href="../logout.php"><i class="fas fa-sign-out"></i><p>تسجيل الخروج</p></a></li>
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
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="js/charts.js"></script>
</body>
</html>
