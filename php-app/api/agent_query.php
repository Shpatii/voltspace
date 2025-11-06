<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/utils.php';
require_once __DIR__ . '/../config.php';
require_login();

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$question = trim($input['question'] ?? '');
if ($question === '') { http_response_code(400); echo json_encode(['error'=>'Missing question']); exit; }

$ch = curl_init(AI_SERVICE_URL . '/agent');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['question'=>$question]));
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

