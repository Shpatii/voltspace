<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/utils.php';
require_once __DIR__ . '/../config.php';
require_login();

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$question = trim($input['question'] ?? '');
if ($question === '') { http_response_code(400); echo json_encode(['error'=>'Missing question']); exit; }

$db = get_db();
$u = current_user();

// Build compact RAG context for this user
$ctx = [];
// Devices summary
$stmt = $db->prepare('SELECT d.name, d.type, d.power_w, d.state_json, d.last_active, r.name AS room
  FROM devices d INNER JOIN rooms r ON d.room_id=r.id INNER JOIN homes h ON r.home_id=h.id
  WHERE h.user_id=? ORDER BY d.id DESC LIMIT 50');
$stmt->bind_param('i', $u['id']);
$stmt->execute();
$devs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
foreach ($devs as $d) {
    $state = json_decode($d['state_json'] ?? '[]', true) ?: [];
    $ctx['devices'][] = [
        'name' => $d['name'], 'type' => $d['type'], 'room' => $d['room'],
        'on' => (bool)($state['on'] ?? false),
        'power_w' => (int)$d['power_w'],
        'attrs' => array_diff_key($state, ['on'=>true]),
        'last_active' => $d['last_active'],
    ];
}
// Recent insights
$stmt = $db->prepare('SELECT title, severity, created_at FROM insights WHERE user_id=? ORDER BY id DESC LIMIT 20');
$stmt->bind_param('i', $u['id']);
$stmt->execute();
$ctx['insights'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
// Recent device logs
$stmt = $db->prepare('SELECT d.name AS device, l.event, l.created_at
  FROM device_logs l INNER JOIN devices d ON l.device_id=d.id
  INNER JOIN rooms r ON d.room_id=r.id INNER JOIN homes h ON r.home_id=h.id
  WHERE h.user_id=? ORDER BY l.id DESC LIMIT 30');
$stmt->bind_param('i', $u['id']);
$stmt->execute();
$ctx['logs'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$ch = curl_init(AI_SERVICE_URL . '/agent');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['question'=>$question, 'context'=>$ctx, 'user_id'=>(int)$u['id'] ]));
$resp = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

header('Content-Type: application/json');
if ($http !== 200) {
    echo json_encode(['answer' => 'Assistant unavailable (HTTP ' . $http . ')']);
    exit;
}
echo $resp;
?>
