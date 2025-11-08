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

// Energy pricing and country helpers
function vs_country_rates(): array {
    // Approximate residential rates; editable in DB per home after creation
    return [
        'XK' => ['name' => 'Kosovo', 'flag' => '🇽🇰', 'currency' => 'EUR', 'price_eur_per_kwh' => 0.09],
        'AL' => ['name' => 'Albania', 'flag' => '🇦🇱', 'currency' => 'ALL', 'price_all_per_kwh' => 12.0],
        'LU' => ['name' => 'Luxembourg', 'flag' => '🇱🇺', 'currency' => 'EUR', 'price_eur_per_kwh' => 0.28],
    ];
}

function vs_country_default_price(string $code): array {
    $map = vs_country_rates();
    $code = strtoupper($code);
    $r = $map[$code] ?? ['currency'=>'EUR', 'price_eur_per_kwh'=>0.20, 'flag'=>'🏳️', 'name'=>'Unknown'];
    // Normalize to price in cents and currency
    if (($r['currency'] ?? 'EUR') === 'EUR') {
        $cents = (int)round(100 * ($r['price_eur_per_kwh'] ?? 0.20));
        return ['currency'=>'EUR', 'cents_per_kwh'=>$cents, 'flag'=>$r['flag'], 'name'=>$r['name']];
    }
    if (($r['currency'] ?? '') === 'ALL') {
        $cents = (int)round(100 * ($r['price_all_per_kwh'] ?? 12)); // store in centimes of ALL
        return ['currency'=>'ALL', 'cents_per_kwh'=>$cents, 'flag'=>$r['flag'], 'name'=>$r['name']];
    }
    return ['currency'=>'EUR', 'cents_per_kwh'=>20, 'flag'=>'🏳️', 'name'=>'Unknown'];
}

function vs_ensure_home_energy_columns(mysqli $db): void {
    // Add columns to homes: country, energy_price_cents_per_kwh, currency if not present
    @$db->query("ALTER TABLE homes ADD COLUMN IF NOT EXISTS country CHAR(2) NULL");
    @$db->query("ALTER TABLE homes ADD COLUMN IF NOT EXISTS energy_price_cents_per_kwh INT NULL");
    @$db->query("ALTER TABLE homes ADD COLUMN IF NOT EXISTS currency VARCHAR(3) NULL");
}

?>
