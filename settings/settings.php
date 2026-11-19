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
    <link rel="stylesheet" href="../fonts/cairo.css">
    <link rel="stylesheet" href="../fontawesome/css/all.min.css">
    <title>الإعدادات</title>
    <link rel="stylesheet" href="css/settings.css">
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
            <li><a href="../charts/charts.php"><i class="fas fa-chart-pie"></i><p>الإحصائيات</p></a></li>
            <li><a href="../posts/posts.php"><i class="fas fa-pen"></i><p>المنشورات</p></a></li>
            <li><a href="../favorite/favorite.php"><i class="fas fa-star"></i><p>المفضلة</p></a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i><p>الإعدادات</p></a></li>
            <li class="log-out"><a href="../logout.php"><i class="fas fa-sign-out"></i><p>تسجيل الخروج</p></a></li>
        </ul>
    </div>

    <div class="content">
        <!-- عنوان الصفحة -->
        <div class="title-info fade-in">
            <p>الإعدادات</p>
            <i class="fas fa-cog"></i>
        </div>

        <!-- شبكة الإعدادات -->
        <div class="settings-grid">
            
            <!-- بطاقة الملف الشخصي -->
            <div class="settings-card profile-card">
                <div class="card-header">
                    <div class="icon-box blue"><i class="fas fa-user"></i></div>
                    <h3>الحساب الشخصي</h3>
                </div>
                <form>
                    <div class="input-group">
                        <label>الاسم الكامل</label>
                        <input type="text" value="hisham" placeholder="الاسم">
                    </div>
                    <div class="input-group">
                        <label>البريد الإلكتروني</label>
                        <input type="email" value="<?php echo $_SESSION['email']; ?>" readonly>
                    </div>
                    <button type="submit" class="save-btn">حفظ التغييرات</button>
                </form>
            </div>

            <!-- بطاقة المظهر -->
            <div class="settings-card appearance-card">
                <div class="card-header">
                    <div class="icon-box purple"><i class="fas fa-paint-brush"></i></div>
                    <h3>المظهر والنسق</h3>
                </div>
                <div class="theme-switcher">
                    <button id="lightModeBtn" class="theme-btn"><i class="fas fa-sun"></i> فاتح</button>
                    <button id="darkModeBtn" class="theme-btn active"><i class="fas fa-moon"></i> داكن</button>
                </div>
            </div>

            <!-- بطاقة الإشعارات واللغة -->
            <div class="settings-card preferences-card">
                <div class="card-header">
                    <div class="icon-box orange"><i class="fas fa-sliders-h"></i></div>
                    <h3>تفضيلات النظام</h3>
                </div>
                
                <div class="setting-item">
                    <div class="setting-info">
                        <h4>الإشعارات</h4>
                        <p>تلقي تنبيهات عن التحديثات</p>
                    </div>
                    <label class="switch">
                        <input type="checkbox" checked>
                        <span class="slider round"></span>
                    </label>
                </div>

                <div class="setting-item">
                     <div class="setting-info">
                        <h4>اللغة</h4>
                        <p>لغة واجهة النظام</p>
                    </div>
                    <select class="lang-select">
                        <option selected>العربية</option>
                        <option>English</option>
                    </select>
                </div>
            </div>

            <!-- بطاقة الأمان (منطقة الخطر) -->
            <div class="settings-card danger-card">
                 <div class="card-header">
                    <div class="icon-box red"><i class="fas fa-shield-alt"></i></div>
                    <h3>الأمان</h3>
                </div>
                <div class="danger-actions">
                    <button class="danger-btn outline">تغيير كلمة المرور</button>
                    <button class="danger-btn full">تسجيل الخروج من كل الأجهزة</button>
                </div>
            </div>

        </div>
    </div> 

    <script>
        // تبديل الوضع الداكن والفاتح
        const darkBtn = document.getElementById("darkModeBtn");
        const lightBtn = document.getElementById("lightModeBtn");
        const body = document.body;

        darkBtn.addEventListener("click", () => {
            body.classList.add("dark-mode");
            body.classList.remove("light-mode");
        });

        lightBtn.addEventListener("click", () => {
            body.classList.add("light-mode");
            body.classList.remove("dark-mode");
        });
    </script>
</body>
</html>
