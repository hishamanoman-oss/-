<?php
/**
 * نظام المصادقة والتحقق من الأدوار والصلاحيات
 * Multi-Role Authentication & Access Control Layer
 */

require_once __DIR__ . '/security.php';

/**
 * جلب بيانات المستخدم المسجل حالياً
 * @return array{id: int|null, full_name: string, email: string, role: string}|null
 */
function current_user(): ?array {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'        => isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,
        'full_name' => (string)($_SESSION['full_name'] ?? ''),
        'email'     => (string)($_SESSION['email'] ?? ''),
        'role'      => (string)($_SESSION['role'] ?? 'staff'),
    ];
}

/**
 * التحقق مما إذا كان المستخدم مسجلاً للدخول
 */
function is_logged_in(): bool {
    return !empty($_SESSION['user_id']) && !empty($_SESSION['email']);
}

/**
 * التحقق من صلاحية أو دور المستخدم الحالي
 * @param string|array<string> $roles
 */
function has_role(string|array $roles): bool {
    if (!is_logged_in()) {
        return false;
    }
    $currentRole = $_SESSION['role'] ?? 'staff';
    if (is_array($roles)) {
        return in_array($currentRole, $roles, true);
    }
    return $currentRole === $roles;
}

/**
 * فرض تسجيل الدخول لحماية الصفحة أو نقطة النهاية (Endpoint)
 */
function require_auth(bool $isJson = false): void {
    if (!is_logged_in()) {
        if ($isJson) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error'   => 'غير مصرح لك بالوصول. يرجى تسجيل الدخول للمتابعة.'
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }

        // تحديد مسار التوجيه النسبي الصحيح لصفحة تسجيل الدخول
        $redirectUrl = 'Login.php';
        if (file_exists(__DIR__ . '/../Login.php') && !file_exists('Login.php')) {
            $redirectUrl = '../Login.php';
        }

        header('Location: ' . $redirectUrl);
        exit();
    }
}

/**
 * فرض صلاحيات دور محدد للوصول إلى الصفحة أو الإجراء
 * @param array<string> $allowedRoles
 */
function require_role(array $allowedRoles, bool $isJson = false): void {
    require_auth($isJson);

    if (!has_role($allowedRoles)) {
        http_response_code(403);
        if ($isJson) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error'   => 'خطأ 403: ليس لديك الصلاحية المطلوبة لتنفيذ هذا الإجراء.'
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }

        die('
            <div dir="rtl" style="font-family: Cairo, Tahoma, sans-serif; text-align: center; padding: 50px;">
                <h1 style="color: #dc3545;">خطأ 403: تم رفض الوصول</h1>
                <p style="font-size: 18px; color: #555;">عذراً، هذا القسم مخصص لمديري النظام فقط ولا تملك الصلاحية الكافية للوصول إليه.</p>
                <a href="javascript:history.back()" style="display: inline-block; margin-top: 20px; padding: 10px 25px; background: #007bff; color: white; text-decoration: none; border-radius: 6px;">العودة للخلف</a>
            </div>
        ');
    }
}
