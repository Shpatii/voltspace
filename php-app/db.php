<?php
require_once __DIR__ . '/config.php';

function get_db(): mysqli {
    static $conn = null;
    if ($conn instanceof mysqli) {
        return $conn;
    }
    $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_errno) {
        http_response_code(500);
        die('Database connection failed: ' . $conn->connect_error);
    }
    $conn->set_charset('utf8mb4');
    // Ensure MySQL session uses UTC to match PHP's timezone in config.php
    // Prevents negative durations due to timezone skew between PHP and MySQL
    @$conn->query("SET time_zone = '+00:00'");
    return $conn;
}
?>
