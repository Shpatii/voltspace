<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/utils.php';
require_once __DIR__ . '/../config.php';
require_login();
$db = get_db();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/pages/insights.php');
    exit;
}

$stmt = $db->prepare('DELETE FROM insights WHERE user_id=?');
$stmt->bind_param('i', $user['id']);
$stmt->execute();

header('Location: ' . BASE_URL . '/pages/insights.php');
exit;
?>

