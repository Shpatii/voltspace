<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/utils.php';
require_login();
$db = get_db();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo 'Method not allowed'; exit; }

$device_id = (int)($_POST['device_id'] ?? 0);
if ($device_id <= 0) { http_response_code(400); echo 'Invalid id'; exit; }

// Ensure device belongs to user
$stmt = $db->prepare('DELETE d FROM devices d INNER JOIN rooms r ON d.room_id=r.id INNER JOIN homes h ON r.home_id=h.id WHERE d.id=? AND h.user_id=?');
$stmt->bind_param('ii', $device_id, $user['id']);
$stmt->execute();

header('Location: ' . BASE_URL . '/pages/devices.php');
exit;
?>

