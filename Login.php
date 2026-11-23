<?php
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/auth.php';

// إذا كان المستخدم مسجلاً بالفعل، يتم توجيهه مباشرة إلى لوحة التحكم
if (is_logged_in()) {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>
        (function() {
            var theme = localStorage.getItem('store_theme') || 'dark';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <link rel="stylesheet" href="fonts/cairo.css?v=13">
    <link rel="stylesheet" href="fontawesome/css/all.min.css?v=13">
    <link rel="stylesheet" href="Css/ui.css?v=13">
    <link rel="stylesheet" href="Css/login.css?v=13">
    <link rel="stylesheet" href="Css/theme.css?v=13">
    <link rel="stylesheet" href="Css/responsive.css?v=13">
    <title>تسجيل الدخول | لوحة متابعة المتجر والمبيعات عن بُعد</title>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-glow"></div>
        
        <div class="login-card">
            <div class="login-header">
                <div class="brand-logo">
                    <img src="assets/images/store-logo.jpg?v=14" alt="شعار المتجر">
                </div>
                <h1 class="login-title">لوحة متابعة المتجر والمبيعات عن بُعد</h1>
                <p class="login-subtitle">سجّل الدخول لمتابعة المبيعات والمخزون والعملاء</p>
            </div>

            <form action="login_process.php" method="post" id="login-form" class="login-form" autocomplete="on">
                <?php echo csrf_field(); ?>

                <div class="input-group">
                    <label for="email">البريد الإلكتروني</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope input-icon"></i>
                        <input type="email" id="email" name="email" placeholder="admin@store.com" required autocomplete="email" autofocus>
                    </div>
                </div>

                <div class="input-group">
                    <label for="password">كلمة المرور</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" id="password" name="password" placeholder="••••••••" required autocomplete="current-password">
                        <button type="button" id="togglePassword" class="password-toggle" title="إظهار / إخفاء كلمة المرور" aria-label="إظهار / إخفاء كلمة المرور">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" id="submitBtn" class="btn-login">
                        <span class="btn-text">تسجيل الدخول</span>
                        <i class="fas fa-arrow-left btn-icon"></i>
                    </button>
                </div>
            </form>

            <div class="login-footer">
                <p><i class="fas fa-shield-alt"></i> نظام محمي بتشفير الجلسات وحماية CSRF و PDO</p>
            </div>
        </div>
    </div>

    <!-- حاوية التنبيهات المنبثقة Toast Container -->
    <div id="toastContainer" class="toast-container" aria-live="polite"></div>

    <script src="js/jquery-3.7.1.js"></script>
    <script src="js/ui.js?v=13"></script>
    <script src="js/login.js?v=13"></script>
</body>
</html>
