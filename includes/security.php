<?php
/**
 * طبقة الحماية العامة (الجلسات الآمنة، فلترة XSS، وحماية CSRF)
 * Security, Safe Sessions, XSS Prevention & CSRF Layer
 */

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

/**
 * فلترة النصوص لمنع هجمات XSS
 */
function e(?string $value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * توليد أو جلب توكن CSRF الحالي في الجلسة
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * توليد حقل مخفي لـ CSRF جاهز للاستخدام داخل النماذج
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" id="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * التحقق من صحة توكن CSRF بأمان ضد هجمات توقيت المقارنة
 */
function verify_csrf_token(?string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}
