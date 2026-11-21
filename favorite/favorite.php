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
    <link rel="stylesheet" href="css/favorite.css?v=4">
    <title>المفضلة</title>
</head>
<body>
    <?php
    $activePage = 'favorite';
    $basePath   = '../';
    require __DIR__ . '/../includes/sidebar.php';
    ?>

    <div class="content">
        <div class="title-info">
            <p>المفضلة</p>
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
