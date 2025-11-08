<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/utils.php';
require_once __DIR__ . '/../config.php';
require_login();
$db = get_db();
$user = current_user();

// Gather devices
$stmt = $db->prepare('SELECT d.* FROM devices d INNER JOIN rooms r ON d.room_id=r.id INNER JOIN homes h ON r.home_id=h.id WHERE h.user_id=?');
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$devs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$payload = [];
foreach ($devs as $d) {
    $payload[] = [
        'name' => $d['name'],
        'type' => $d['type'],
        'power_w' => (int)$d['power_w'],
        'state' => safe_json_decode($d['state_json']),
        'last_active' => $d['last_active'] ? date('c', strtotime($d['last_active'])) : null,
    ];
}

// Prefer AI-generated insights if available, then fall back
$ch = curl_init(AI_SERVICE_URL . '/insights_ai');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
$resp = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http !== 200) {
    // Fallback to rule-based endpoint
    $ch = curl_init(AI_SERVICE_URL . '/insights');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
}

if ($http !== 200) {
    header('Content-Type: application/json');
    echo json_encode(['ok'=>false, 'error'=>'AI service error', 'status'=>$http]);
    exit;
}

$data = json_decode($resp, true);
$insights = $data['insights'] ?? [];
$count = 0;
foreach ($insights as $ins) {
    $title = substr($ins['title'] ?? 'Insight', 0, 128);
    $detail = $ins['detail'] ?? '';
    $severity = $ins['severity'] ?? 'info';
    $ack = 0;
    $stmt = $db->prepare('INSERT INTO insights(user_id, title, detail, severity, acknowledged, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
    $stmt->bind_param('isssi', $user['id'], $title, $detail, $severity, $ack);
    if ($stmt->execute()) $count++;
}

// If browser expects HTML (e.g., direct form submit), redirect back to Insights page.
$accept = $_SERVER['HTTP_ACCEPT'] ?? '';
if (strpos($accept, 'text/html') !== false) {
    header('Location: ' . BASE_URL . '/pages/insights.php?ran=1&count=' . urlencode((string)$count));
    exit;
}

header('Content-Type: application/json');
echo json_encode(['ok'=>true, 'count'=>$count]);
?>
