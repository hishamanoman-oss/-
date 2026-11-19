<?php
/**
 * ملف الاتصال المتوافق مع الصفحات القديمة
 * Legacy MySQLi Connection Adapter
 */

require_once __DIR__ . '/config/config.php';

$host = DB_HOST;
$user = DB_USER;
$pass = DB_PASS;
$db   = DB_NAME;
$port = (int)DB_PORT;

$conn = @mysqli_connect($host, $user, $pass, $db, $port);

if (!$conn) {
    error_log('MySQLi Connection Failed: ' . mysqli_connect_error());
    die('فشل الاتصال بقاعدة البيانات. يرجى المحاولة لاحقاً.');
}

mysqli_set_charset($conn, 'utf8mb4');
