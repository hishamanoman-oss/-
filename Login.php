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
    <link rel="stylesheet" href="fonts/cairo.css?v=9">
    <link rel="stylesheet" href="fontawesome/css/all.min.css?v=9">
    <link rel="stylesheet" href="Css/ui.css?v=9">
    <link rel="stylesheet" href="Css/login.css?v=9">
    <link rel="stylesheet" href="Css/responsive.css?v=11">
    <title>تسجيل الدخول</title>
</head>
<body>
    <div class="login">
        <div class="box">
            <h1>تسجيل الدخول</h1>
            <form action="login_process.php" method="post" id="login-form">
                <?php echo csrf_field(); ?>
                <div>
                    <label for="email">البريد الإلكتروني</label><br>
                    <input type="email" id="email" name="email" placeholder="أدخل البريد الإلكتروني" required>
                </div><br>
                <div>
                    <label for="password">كلمة المرور</label><br>
                    <input type="password" id="password" name="password" placeholder="أدخل كلمة المرور" required>
                </div>
                <br>
                <div>
                    <button type="submit">تسجيل الدخول</button>
                </div>
            </form>
        </div>
    </div>
<script src="js/jquery-3.7.1.js"></script>
<script src="js/ui.js?v=9"></script>
<script src="js/login.js?v=9"></script>
</body>
</html>
