<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/utils.php';
require_login();
$db = get_db();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo 'Method not allowed'; exit;
}

$device_id = (int)($_POST['device_id'] ?? 0);
if ($device_id <= 0) { http_response_code(400); echo 'Invalid device'; exit; }

// Ensure ownership
$stmt = $db->prepare('SELECT d.id, d.state_json FROM devices d INNER JOIN rooms r ON d.room_id=r.id INNER JOIN homes h ON r.home_id=h.id WHERE d.id=? AND h.user_id=? LIMIT 1');
$stmt->bind_param('ii', $device_id, $user['id']);
$stmt->execute();
$dev = $stmt->get_result()->fetch_assoc();
if (!$dev) { http_response_code(404); echo 'Not found'; exit; }

$state = safe_json_decode($dev['state_json']);
$on = $state['on'] ?? false;
$state['on'] = !$on;
$new_json = json_encode($state);

$stmt = $db->prepare('UPDATE devices SET state_json=?, last_active=NOW() WHERE id=?');
$stmt->bind_param('si', $new_json, $device_id);
$stmt->execute();

// Log
$payload = json_encode(['from'=>$on, 'to'=>!$on]);
$stmt = $db->prepare('INSERT INTO device_logs(device_id, event, payload, created_at) VALUES (?, ?, ?, NOW())');
$e = 'toggle';
$stmt->bind_param('iss', $device_id, $e, $payload);
$stmt->execute();

header('Location: ' . BASE_URL . '/pages/devices.php');
exit;
?>
