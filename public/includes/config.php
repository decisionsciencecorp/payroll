<?php
// Payroll - Copyright (C) 2026 Decision Science Corp. - Licensed under GNU AGPL v3.0. See LICENSE.
// Payroll app configuration — LEMP-compatible, no .htaccess
if (getenv('PAYROLL_TEST') && getenv('DB_PATH')) {
    define('DB_PATH', getenv('DB_PATH'));
    define('STORAGE_PATH', getenv('STORAGE_PATH') ?: (sys_get_temp_dir() . '/payroll_storage'));
} else {
    define('DB_PATH', __DIR__ . '/../../db/payroll.db');
    // Host requirement: user uploads must live in public/uploads/ to persist across deployments.
    define('STORAGE_PATH', __DIR__ . '/../uploads');
}
define('DB_TIMEOUT', 30);
define('SESSION_NAME', 'payroll_admin');
define('PASSWORD_COST', 12);
define('SITE_NAME', 'Payroll');
if (!defined('LOG_PATH')) {
    define('LOG_PATH', __DIR__ . '/../../logs/app.log');
}

require_once __DIR__ . '/database.php';
initializeDatabase();
if (!defined('SITE_URL')) {
    $db = getDbConnection();
    $row = $db->querySingle("SELECT site_url FROM company_settings WHERE id = 1", true);
    $siteUrl = (!empty($row['site_url']) && is_string($row['site_url'])) ? trim($row['site_url']) : '';
    if ($siteUrl !== '') {
        define('SITE_URL', rtrim($siteUrl, '/'));
    } else {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host !== '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            define('SITE_URL', $scheme . '://' . $host);
        } else {
            define('SITE_URL', 'http://localhost');
        }
    }
}

if (session_status() === PHP_SESSION_NONE) {
    $secure = defined('SITE_URL') && strpos(SITE_URL, 'https://') === 0;
    session_set_cookie_params([
        'httponly' => true,
        'secure' => $secure,
        'samesite' => 'Lax',
    ]);
    session_name(SESSION_NAME);
    session_start();
}

$isDevelopment = (
    ($_SERVER['HTTP_HOST'] ?? '') === 'localhost' ||
    strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false ||
    (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'development')
);

if ($isDevelopment) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../../logs/php-errors.log');
}

date_default_timezone_set('UTC');

require_once __DIR__ . '/security-headers.php';
