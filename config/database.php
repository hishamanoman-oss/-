<?php
/**
 * إدارة الاتصال بقاعدة البيانات عبر PDO
 * Secure PDO Database Connection Manager
 */

require_once __DIR__ . '/config.php';

function get_db_connection(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            DB_HOST,
            DB_PORT,
            DB_NAME
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // تسجيل الخطأ داخلياً بأمان وعدم تسريب بيانات الاتصال الحساسة للمستخدم
            error_log('PDO Connection Error: ' . $e->getMessage());
            die('فشل الاتصال بقاعدة البيانات. يرجى مراجعة سجلات الخادم أو المحاولة لاحقاً.');
        }
    }

    return $pdo;
}
