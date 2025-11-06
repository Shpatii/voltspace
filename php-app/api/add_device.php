<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/utils.php';
require_login();
$db = get_db();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo 'Method not allowed'; exit;
}

$room_id = (int)($_POST['room_id'] ?? 0);
$type = $_POST['type'] ?? '';
$category = $_POST['category'] ?? 'essential';
$name = trim($_POST['name'] ?? '');
$method = $_POST['method'] ?? 'serial';
$code = strtoupper(trim($_POST['code'] ?? ''));

// Validate room ownership
$stmt = $db->prepare('SELECT r.id FROM rooms r INNER JOIN homes h ON r.home_id=h.id WHERE r.id=? AND h.user_id=? LIMIT 1');
$stmt->bind_param('ii', $room_id, $user['id']);
$stmt->execute();
if (!$stmt->get_result()->fetch_assoc()) { http_response_code(403); echo 'Invalid room'; exit; }

// Validate type
$valid_types = ['light','ac','plug','sensor','tv','pc','speaker','fridge','washer','camera'];
if (!in_array($type, $valid_types, true)) { http_response_code(400); echo 'Invalid type'; exit; }

// Validate category
$valid_cats = ['essential','non_essential','flexible','pc','tv','speaker','fridge','washer','camera'];
if (!in_array($category, $valid_cats, true)) { http_response_code(400); echo 'Invalid category'; exit; }

// Validate codes
if ($method === 'serial') {
    if (!preg_match('/^ENR-DEV-[0-9A-F]{6}$/', $code)) { http_response_code(400); echo 'Invalid serial format'; exit; }
    $serial = $code; $bt = null;
} else {
    if (!preg_match('/^BT-\d{4}-\d{4}$/', $code)) { http_response_code(400); echo 'Invalid BT code'; exit; }
    $serial = null; $bt = $code;
}

// Defaults per type
$state = ['on' => false, 'category' => $category];
switch ($type) {
    case 'light':
        $state = ['on'=>false, 'brightness'=>100, 'base_w'=>9, 'category'=>$category];
        break;
    case 'ac':
        $state = ['on'=>false, 'setpoint'=>24, 'category'=>$category];
        break;
    case 'plug':
        $state = ['on'=>false, 'flexible'=> ($category === 'flexible'), 'category'=>$category];
        break;
    case 'tv':
        $state = ['on'=>false, 'brightness'=>70, 'category'=>$category];
        break;
    case 'pc':
        $state = ['on'=>false, 'load'=>20, 'category'=>$category]; // CPU load %
        break;
    case 'speaker':
        $state = ['on'=>false, 'volume'=>30, 'category'=>$category];
        break;
    case 'fridge':
        $state = ['on'=>true, 'eco'=>true, 'door_open'=>false, 'category'=>$category];
        break;
    case 'washer':
        $state = ['on'=>false, 'phase'=>'idle', 'category'=>$category]; // idle|wash|spin
        break;
    case 'camera':
        $state = ['on'=>true, 'res'=>'1080p', 'category'=>$category];
        break;
}

$power_w = 0;
switch ($type) {
    case 'light': $power_w = 9; break;
    case 'ac': $power_w = 900; break;
    case 'plug': $power_w = random_int(10, 1500); break;
    case 'tv': $power_w = 120; break;
    case 'pc': $power_w = 200; break;
    case 'speaker': $power_w = 20; break;
    case 'fridge': $power_w = 150; break;
    case 'washer': $power_w = 500; break;
    case 'camera': $power_w = 5; break;
}

$state_json = json_encode($state);

$stmt = $db->prepare('INSERT INTO devices(room_id, type, name, serial_number, bt_code, state_json, power_w, last_active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
$stmt->bind_param('isssssi', $room_id, $type, $name, $serial, $bt, $state_json, $power_w);
if ($stmt->execute()) {
    header('Location: ' . BASE_URL . '/pages/devices.php');
    exit;
}
http_response_code(500); echo 'Failed to add device';
?>
