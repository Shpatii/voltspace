<?php
require_once __DIR__ . '/includes/auth.php';
$user = current_user();
if ($user) {
    header('Location: ' . BASE_URL . '/pages/dashboard.php');
} else {
    header('Location: ' . BASE_URL . '/pages/login.php');
}
exit;
?>
