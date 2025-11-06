<?php
// VoltSpace basic configuration
// Update DB credentials to match your XAMPP MySQL setup

date_default_timezone_set('UTC');

define('SITE_NAME', 'VoltSpace');

// Database credentials
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', ''); // XAMPP default is empty
define('DB_NAME', 'voltspace_db');

// AI Service (FastAPI) URL
define('AI_SERVICE_URL', 'http://127.0.0.1:8000');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Compute web base URL for the php-app directory so links work under
// http://localhost/.../php-app/ regardless of parent folder name.
// Example result: "/VoltSpace/php-app" or "/voltspace/php-app"
$__docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim(str_replace('\\','/', $_SERVER['DOCUMENT_ROOT']), '/') : '';
$__appDir = rtrim(str_replace('\\','/', realpath(__DIR__)), '/');
$__base = '/php-app';
if ($__docRoot && strpos($__appDir, $__docRoot) === 0) {
    $__rel = substr($__appDir, strlen($__docRoot));
    $__base = $__rel ?: '/php-app';
}
if ($__base === '' || $__base[0] !== '/') { $__base = '/' . $__base; }
define('BASE_URL', rtrim($__base, '/'));
unset($__docRoot, $__appDir, $__base, $__rel);
?>

<!-- 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 -->