
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
    <link rel="stylesheet" href="css/favorite.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>Favorite</title>
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
            <li><a href="../posts/posts.php"><i class="fas fa-pen"></i><p>posts</p></a></li>
            <li><a href="favorite.php"><i class="fas fa-star"></i><p>favorite</p></a></li>
            <li><a href="../settings/settings.php"><i class="fas fa-cog"></i><p>settings</p></a></li>
            <li class="log-out"><a href="../logout.php"><i class="fas fa-sign-out"></i><p>log out</p></a></li>
        </ul>
    </div>
    <div class="content">
        <div class="title-info">
            <p>Favorite</p>
            <i class="fas fa-star"></i>
        </div>
        
        <div class="favorites-container">
            
            <!-- قسم العملاء المميزين -->
            <h3 class="section-title"><i class="fas fa-crown"></i> عملاء VIP</h3>
            <div class="cards-grid">
                <?php
                $vip_clients = [
                    ['name' => 'مؤسسة النخبة', 'tier' => 'شريك استراتيجي', 'phone' => '05000000', 'email' => 'info@elite.com'],
                    ['name' => 'خالد عبد الرحمن', 'tier' => 'عميل ذهبي', 'phone' => '05600000', 'email' => 'khaled@mail.com'],
                    ['name' => 'شركة الأفق للتجارة', 'tier' => 'عميل ماسي', 'phone' => '05900000', 'email' => 'contact@horizon.com'],
                ];

                foreach($vip_clients as $client): ?>
                <div class="card client-card">
                    <div class="card-icon client-icon">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div class="card-details">
                        <h4><?php echo $client['name']; ?></h4>
                        <span class="badge golden"><?php echo $client['tier']; ?></span>
                    </div>
                    <div class="card-actions">
                        <a href="tel:<?php echo $client['phone']; ?>" class="btn-icon call" title="اتصال"><i class="fas fa-phone"></i></a>
                        <a href="mailto:<?php echo $client['email']; ?>" class="btn-icon email" title="بريد إلكتروني"><i class="fas fa-envelope"></i></a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- قسم المنتجات المراقبة -->
            <h3 class="section-title"><i class="fas fa-laptop-code"></i> منتجات تحت المراقبة</h3>
            <div class="cards-grid">
                <?php
                $products = [
                    ['name' => 'MacBook Pro M3', 'specs' => 'Apple M3 Max, 36GB RAM', 'price' => '9,500$'],
                    ['name' => 'Dell XPS 15', 'specs' => 'i9-13900H, RTX 4070', 'price' => '2,800$'],
                    ['name' => 'Sony WH-1000XM5', 'specs' => 'Noise Cancelling', 'price' => '350$'],
                    ['name' => 'Samsung S24 Ultra', 'specs' => 'AI Features, 1TB', 'price' => '1,200$'],
                ];

                foreach($products as $prod): ?>
                <div class="card product-card">
                    <div class="card-icon product-icon">
                        <i class="fas fa-microchip"></i>
                    </div>
                    <div class="card-details">
                        <h4><?php echo $prod['name']; ?></h4>
                        <p class="specs"><?php echo $prod['specs']; ?></p>
                        <span class="price"><?php echo $prod['price']; ?></span>
                    </div>
                    <div class="card-actions">
                         <button class="btn-text">تفاصيل <i class="fas fa-arrow-left"></i></button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

        </div>
     </div>
</body>
</html>
