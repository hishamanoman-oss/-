<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

require_role(['admin']);
$currentUser = current_user();

$pdo = get_db_connection();

// جلب إعدادات المتجر من قاعدة البيانات
$settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settingsMap  = $settingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);

$storeName = $settingsMap['store_name'] ?? 'متجر المبيعات الذكي';
$currency  = $settingsMap['currency'] ?? 'ر.س';
$theme     = $settingsMap['theme'] ?? 'dark';

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../fonts/cairo.css?v=5">
    <link rel="stylesheet" href="../fontawesome/css/all.min.css?v=5">
    <link rel="stylesheet" href="css/settings.css?v=5">
    <title>إعدادات النظام والمتجر</title>
</head>
<body>
    <?php
    $activePage = 'settings';
    $basePath   = '../';
    require __DIR__ . '/../includes/sidebar.php';
    ?>

    <div class="content">
        <!-- عنوان الصفحة -->
        <div class="title-info">
            <p><i class="fas fa-sliders-h" style="margin-left:8px;"></i> إعدادات المتجر والأمان</p>
            <i class="fas fa-cog"></i>
        </div>

        <!-- رسائل التنبيه -->
        <?php if ($flashSuccess): ?>
            <div class="alert-flash alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?= e($flashSuccess) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($flashError): ?>
            <div class="alert-flash alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <span><?= e($flashError) ?></span>
            </div>
        <?php endif; ?>

        <!-- شبكة الإعدادات -->
        <div class="settings-grid">
            
            <!-- 1. بطاقة إعدادات المتجر العامة -->
            <div class="settings-card profile-card">
                <div class="card-header">
                    <div class="icon-box blue"><i class="fas fa-store"></i></div>
                    <h3>إعدادات المتجر والفواتير</h3>
                </div>
                <form action="settings_process.php" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="store_settings">

                    <div class="input-group">
                        <label for="store_name">اسم المتجر / المنشأة</label>
                        <input type="text" id="store_name" name="store_name" value="<?= e($storeName) ?>" required maxlength="100">
                    </div>

                    <div class="input-group">
                        <label for="currency">العملة الرسمية المعتمدة</label>
                        <input type="text" id="currency" name="currency" value="<?= e($currency) ?>" placeholder="مثال: ر.س أو $" required maxlength="20">
                    </div>

                    <div class="input-group">
                        <label for="themeSelect">المظهر الافتراضي للنظام</label>
                        <select id="themeSelect" name="theme" class="lang-select" style="width:100%; padding:12px;">
                            <option value="dark" <?= $theme === 'dark' ? 'selected' : '' ?>>الوضع الداكن (Dark Mode)</option>
                            <option value="light" <?= $theme === 'light' ? 'selected' : '' ?>>الوضع الفاتح (Light Mode)</option>
                        </select>
                    </div>

                    <button type="submit" class="save-btn" style="display:flex; align-items:center; justify-content:center; gap:8px;">
                        <i class="fas fa-save"></i> حفظ وتطبيق إعدادات المتجر
                    </button>
                </form>
            </div>

            <!-- 2. بطاقة الأمان وتغيير كلمة المرور -->
            <div class="settings-card danger-card">
                <div class="card-header">
                    <div class="icon-box red"><i class="fas fa-user-shield"></i></div>
                    <h3>أمان الحساب وكلمة المرور</h3>
                </div>
                <form action="settings_process.php" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="change_password">

                    <div class="input-group">
                        <label>البريد الإلكتروني الحالي</label>
                        <input type="email" value="<?= e($currentUser['email']) ?>" readonly style="background:rgba(255,255,255,0.05); color:#aaa; cursor:not-allowed;">
                    </div>

                    <div class="input-group">
                        <label for="current_password">كلمة المرور الحالية</label>
                        <input type="password" id="current_password" name="current_password" placeholder="أدخل كلمة المرور الحالية للتأكيد" required>
                    </div>

                    <div class="input-group">
                        <label for="new_password">كلمة المرور الجديدة</label>
                        <input type="password" id="new_password" name="new_password" placeholder="8 خانات على الأقل" minlength="8" required>
                    </div>

                    <div class="input-group">
                        <label for="confirm_password">تأكيد كلمة المرور الجديدة</label>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="أعد إدخال كلمة المرور الجديدة" minlength="8" required>
                    </div>

                    <button type="submit" class="danger-btn full" style="margin-top:10px; display:flex; align-items:center; justify-content:center; gap:8px;">
                        <i class="fas fa-key"></i> تحديث كلمة المرور الآن
                    </button>
                </form>
            </div>

            <!-- 3. بطاقة بيانات المشرف والجلسة النشطة -->
            <div class="settings-card preferences-card">
                <div class="card-header">
                    <div class="icon-box orange"><i class="fas fa-info-circle"></i></div>
                    <h3>معلومات الجلسة والنظام</h3>
                </div>

                <div class="setting-item">
                    <div class="setting-info">
                        <h4>المستخدم الحالي</h4>
                        <p><?= e($currentUser['full_name']) ?></p>
                    </div>
                    <span style="background:rgba(4,129,255,0.2); color:#00c6ff; border:1px solid #0481ff; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:bold;">
                        مدير عام (Admin)
                    </span>
                </div>

                <div class="setting-item">
                    <div class="setting-info">
                        <h4>حالة النظام</h4>
                        <p>قاعدة البيانات متصلة عبر PDO</p>
                    </div>
                    <span style="color:#00ff88; font-size:13px; font-weight:bold;">
                        <i class="fas fa-check-circle"></i> متصل
                    </span>
                </div>

                <div class="setting-item">
                    <div class="setting-info">
                        <h4>إصدار PHP الخادم</h4>
                        <p><?= PHP_VERSION ?></p>
                    </div>
                    <span style="color:#aaa; font-size:12px;">PHP 8.2 Ready</span>
                </div>

                <div class="setting-item" style="border-top:1px solid rgba(255,255,255,0.06); padding-top:15px; margin-top:10px;">
                    <div class="setting-info">
                        <h4>تسجيل الخروج</h4>
                        <p>إنهاء الجلسة بأمان</p>
                    </div>
                    <a href="../logout.php" class="danger-btn outline" style="text-decoration:none; font-size:12px; padding:6px 14px;">
                        <i class="fas fa-sign-out-alt"></i> خروج
                    </a>
                </div>
            </div>

        </div>
    </div>
</body>
</html>
