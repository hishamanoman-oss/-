<?php
/**
 * ملف الإعدادات العامة وقراءة متغيرات البيئة
 * Global Configuration & Environment Parser
 */

if (!defined('APP_CONFIG_LOADED')) {
    define('APP_CONFIG_LOADED', true);

    $envFile = realpath(__DIR__ . '/../.env');
    $envVars = [];

    if ($envFile && file_exists($envFile) && is_readable($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (str_contains($line, '=')) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                // إزالة علامات الاقتباس إن وجدت
                if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                    (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                    $value = substr($value, 1, -1);
                }
                $envVars[$key] = $value;
            }
        }
    }

    // تعريف الثوابت مع وجود قيم افتراضية احتياطية لبيئة XAMPP المحلية
    if (!defined('APP_NAME')) {
        define('APP_NAME', $envVars['APP_NAME'] ?? 'نظام إدارة المتجر والمبيعات');
    }
    if (!defined('APP_ENV')) {
        define('APP_ENV', $envVars['APP_ENV'] ?? 'local');
    }
    if (!defined('DB_HOST')) {
        define('DB_HOST', $envVars['DB_HOST'] ?? 'localhost');
    }
    if (!defined('DB_PORT')) {
        define('DB_PORT', $envVars['DB_PORT'] ?? '3306');
    }
    if (!defined('DB_NAME')) {
        define('DB_NAME', $envVars['DB_NAME'] ?? 'myproject_db');
    }
    if (!defined('DB_USER')) {
        define('DB_USER', $envVars['DB_USER'] ?? 'root');
    }
    if (!defined('DB_PASS')) {
        define('DB_PASS', $envVars['DB_PASS'] ?? '');
    }
    if (!defined('BASE_PATH')) {
        define('BASE_PATH', realpath(__DIR__ . '/..'));
    }
}
