<?php
// Utility helpers

function h(?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function fmt_datetime(?string $dt): string {
    if (!$dt) return '-';
    return date('Y-m-d H:i', strtotime($dt));
}

function fmt_duration(int $seconds): string {
    // Real-time friendly HH:MM:SS; clamp negatives to zero
    if ($seconds < 0) $seconds = 0;
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $secs = $seconds % 60;
    if ($hours > 0) return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
    return sprintf('%02d:%02d', $minutes, $secs);
}

function safe_json_decode(?string $json): array {
    if (!$json) return [];
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function now_iso(): string {
    return date('c');
}
?>
