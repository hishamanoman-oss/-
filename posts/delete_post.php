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

$postId = filter_input(INPUT_POST, 'post_id', FILTER_VALIDATE_INT);

if (!$postId) {
    $_SESSION['flash_error'] = 'معرف التعميم غير صالح.';
    header('Location: posts.php');
    exit;
}

try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("DELETE FROM posts WHERE id = :id");
    $stmt->execute(['id' => $postId]);

    $_SESSION['flash_success'] = 'تم حذف التعميم الإداري بنجاح.';
} catch (Throwable $e) {
    error_log('Error deleting post: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'حدث خطأ أثناء محاولة حذف التعميم.';
}

header('Location: posts.php');
exit;
