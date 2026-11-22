<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

require_auth();
require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: posts.php');
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    $_SESSION['flash_error'] = 'خطأ أمني: رمز الحماية (CSRF Token) غير صالح أو انتهت صلاحية الجلسة.';
    header('Location: posts.php');
    exit;
}

$title   = trim($_POST['title'] ?? '');
$content = trim($_POST['content'] ?? '');

if ($title === '' || $content === '') {
    $_SESSION['flash_error'] = 'يرجى كتابة عنوان وتفاصيل التعميم بالكامل قبل النشر.';
    header('Location: posts.php');
    exit;
}

if (mb_strlen($title) > 200) {
    $_SESSION['flash_error'] = 'عنوان التعميم يجب ألا يتجاوز 200 حرف.';
    header('Location: posts.php');
    exit;
}

try {
    $pdo = get_db_connection();
    $currentUser = current_user();

    $stmt = $pdo->prepare("
        INSERT INTO posts (user_id, title, content, status, created_at, updated_at)
        VALUES (:user_id, :title, :content, 'published', NOW(), NOW())
    ");

    $stmt->execute([
        'user_id' => $currentUser['id'],
        'title'   => $title,
        'content' => $content
    ]);

    $_SESSION['flash_success'] = 'تم نشر التعميم / التوجيه الإداري بنجاح.';
} catch (Throwable $e) {
    error_log('Error creating post: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'حدث خطأ غير متوقع أثناء حفظ التعميم.';
}

header('Location: posts.php');
exit;
