<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

require_auth();
require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: settings.php');
    exit;
}

verify_csrf_token();

$action = trim($_POST['action'] ?? '');

try {
    $pdo = get_db_connection();
    $currentUser = current_user();

    if ($action === 'store_settings') {
        $storeName = trim($_POST['store_name'] ?? '');
        $currency  = trim($_POST['currency'] ?? '');
        $theme     = trim($_POST['theme'] ?? 'dark');

        if ($storeName === '') {
            $_SESSION['flash_error'] = 'يرجى إدخال اسم المتجر بشكل صحيح.';
            header('Location: settings.php');
            exit;
        }

        if ($currency === '') {
            $_SESSION['flash_error'] = 'يرجى إدخال رمز أو اسم العملة.';
            header('Location: settings.php');
            exit;
        }

        if (!in_array($theme, ['dark', 'light'], true)) {
            $theme = 'dark';
        }

        // حفظ أو تحديث الإعدادات في جدول settings
        $upsertStmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value, updated_at)
            VALUES (:key, :val, NOW())
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
        ");

        $upsertStmt->execute(['key' => 'store_name', 'val' => $storeName]);
        $upsertStmt->execute(['key' => 'currency', 'val' => $currency]);
        $upsertStmt->execute(['key' => 'theme', 'val' => $theme]);

        $_SESSION['flash_success'] = 'تم حفظ وتحديث إعدادات المتجر بنجاح وتطبيقها فوراً في الفواتير ولوحة التحكم.';
        header('Location: settings.php');
        exit;

    } elseif ($action === 'change_password') {
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $newPassword     = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $_SESSION['flash_error'] = 'يرجى تعبئة كافة حقول كلمة المرور.';
            header('Location: settings.php');
            exit;
        }

        if (mb_strlen($newPassword) < 8) {
            $_SESSION['flash_error'] = 'كلمة المرور الجديدة يجب ألا تقل عن 8 خانات/أحرف.';
            header('Location: settings.php');
            exit;
        }

        if ($newPassword !== $confirmPassword) {
            $_SESSION['flash_error'] = 'كلمة المرور الجديدة وتأكيدها غير متطابقين.';
            header('Location: settings.php');
            exit;
        }

        // التحقق من صحة كلمة المرور الحالية
        $userStmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = :id");
        $userStmt->execute(['id' => $currentUser['id']]);
        $userData = $userStmt->fetch(PDO::FETCH_ASSOC);

        if (!$userData || !password_verify($currentPassword, $userData['password_hash'])) {
            $_SESSION['flash_error'] = 'كلمة المرور الحالية التي أدخلتها غير صحيحة.';
            header('Location: settings.php');
            exit;
        }

        // تشفير وتحديث كلمة المرور الجديدة
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateStmt = $pdo->prepare("UPDATE users SET password_hash = :hash, updated_at = NOW() WHERE id = :id");
        $updateStmt->execute(['hash' => $newHash, 'id' => $currentUser['id']]);

        $_SESSION['flash_success'] = 'تم تغيير وتحديث كلمة المرور بنجاح وأمان.';
        header('Location: settings.php');
        exit;

    } else {
        header('Location: settings.php');
        exit;
    }

} catch (Throwable $e) {
    error_log('Settings Process Error: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'حدث خطأ غير متوقع أثناء معالجة الإعدادات.';
    header('Location: settings.php');
    exit;
}
