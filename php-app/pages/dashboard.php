<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/utils.php';
require_login();
$db = get_db();
$user = current_user();

// Fetch devices
$stmt = $db->prepare('SELECT d.*, r.name AS room_name FROM devices d INNER JOIN rooms r ON d.room_id=r.id INNER JOIN homes h ON r.home_id=h.id WHERE h.user_id=?');
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$devices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Power model helpers
function estimate_light_w(array $state, int $base=9): int {
    $on = $state['on'] ?? false;
    $brightness = (int)($state['brightness'] ?? 100);
    return $on ? (int)round($base * max(0, min(100, $brightness)) / 100) : 0;
}
function estimate_plug_w(array $state, int $power_w): int {
    return ($state['on'] ?? false) ? max(0, (int)$power_w) : 0;
}
function estimate_ac_w(array $state): int {
    if (!($state['on'] ?? false)) return 0;
    $set = (int)($state['setpoint'] ?? 24);
    $hour = (int)date('G');
    $base = 700;
    if ($set < 22) $base += 250; // cooler → higher load
    if ($hour >= 12 && $hour <= 18) $base += 200; // daytime bump
    return min(1200, $base);
}

// Additional type estimators (simple heuristics)
function estimate_tv_w(array $state, int $base=100): int {
    if (!($state['on'] ?? false)) return 0;
    $b = (int)($state['brightness'] ?? 70);
    return max(20, (int)round($base * max(10, min(100, $b)) / 100));
}
function estimate_pc_w(array $state, int $idle=60, int $max=250): int {
    if (!($state['on'] ?? false)) return 5; // standby
    $load = (int)($state['load'] ?? 20); // 0..100
    $load = max(0, min(100, $load));
    return (int)round($idle + ($max - $idle) * ($load / 100.0));
}
function estimate_speaker_w(array $state): int {
    if (!($state['on'] ?? false)) return 0;
    $vol = (int)($state['volume'] ?? 30);
    return (int)round(3 + 0.25 * max(0, min(100, $vol))); // ~3..28W
}
function estimate_fridge_w(array $state): int {
    // Rough cycle: compressor ~30% of time, idle otherwise. Door open adds overhead.
    if (!($state['on'] ?? true)) return 0;
    $min = (int)date('i');
    $compressor = ($min % 10) < 3; // 3 minutes on per 10
    $w = $compressor ? 120 : 8;
    if (!empty($state['door_open'])) $w += 20;
    if (!empty($state['eco'])) $w = (int)round($w * 0.9);
    return $w;
}
function estimate_washer_w(array $state): int {
    if (!($state['on'] ?? false)) return 0;
    $phase = strtolower((string)($state['phase'] ?? 'wash'));
    if ($phase === 'spin') return 800;
    if ($phase === 'wash') return 500;
    if ($phase === 'heat') return 1200;
    return 10; // idle/pause
}
function estimate_camera_w(array $state): int {
    return ($state['on'] ?? true) ? 5 : 0;
}

$total_devices = count($devices);
$on_count = 0; $current_load_w = 0; $longest = [];

$midnight = strtotime(date('Y-m-d 00:00:00'));
$now = time();
$kwh_today = 0.0;
$series = array_fill(0, 24, 0);

foreach ($devices as $d) {
    $state = safe_json_decode($d['state_json']);
    $on = $state['on'] ?? false;
    $w = 0;
    switch ($d['type']) {
        case 'light':   $w = estimate_light_w($state, (int)($state['base_w'] ?? 9)); break;
        case 'plug':    $w = estimate_plug_w($state, (int)$d['power_w']); break;
        case 'ac':      $w = estimate_ac_w($state); break;
        case 'tv':      $w = estimate_tv_w($state, 100); break;
        case 'pc':      $w = estimate_pc_w($state); break;
        case 'speaker': $w = estimate_speaker_w($state); break;
        case 'fridge':  $w = estimate_fridge_w($state); break;
        case 'washer':  $w = estimate_washer_w($state); break;
        case 'camera':  $w = estimate_camera_w($state); break;
        default:        $w = estimate_plug_w($state, (int)$d['power_w']); break;
    }
    if ($on) $on_count++;
    $current_load_w += $w;
    // Longest running ON: duration since last_active
    if ($on) {
        $la = $d['last_active'] ? strtotime($d['last_active']) : $now; // if missing, treat as just turned on
        $dur = max(0, $now - $la);
        $longest[] = [
            'id' => (int)$d['id'],
            'name' => $d['name'],
            'type' => $d['type'],
            'room' => $d['room_name'],
            'room_id' => (int)$d['room_id'],
            'duration' => $dur,
        ];
    }
    // kWh today: approximate hourly slices since midnight while ON
    $la = $d['last_active'] ? strtotime($d['last_active']) : $now;
    if ($on) {
        $start = max($midnight, $la);
        $hours = max(0, ($now - $start) / 3600.0);
        $kwh_today += ($w * $hours) / 1000.0;
    }
}

// Deduplicate by normalized name + type + room to collapse accidental duplicates
$byKey = [];
foreach ($longest as $e) {
    $k = strtolower(trim($e['name'])) . '|' . $e['type'] . '|' . (int)$e['room_id'];
    if (!isset($byKey[$k]) || $e['duration'] > $byKey[$k]['duration']) {
        $byKey[$k] = $e;
    }
}
$longest = array_values($byKey);
usort($longest, function($a,$b){ return $b['duration'] <=> $a['duration']; });
$longest = array_slice($longest, 0, 5);
// Enrich with ISO last_active for client-side live timers
foreach ($devices as $d) {
    if (!($d['last_active'])) continue;
    foreach ($longest as &$e) {
        if ($e['id'] === (int)$d['id']) {
            $e['last_active_iso'] = date('c', strtotime($d['last_active']));
        }
    }
}

// Build simple hourly power series (00..23) using 1h slices from midnight
for ($h = 0; $h < 24; $h++) {
    $sliceStart = $midnight + $h * 3600;
    $sliceEnd = $sliceStart + 3600;
    if ($sliceStart > $now) { $series[$h] = 0; continue; }
    $sumW = 0;
    foreach ($devices as $d) {
        $state = safe_json_decode($d['state_json']);
        $on = $state['on'] ?? false;
        if (!$on) continue;
        $w = 0;
        switch ($d['type']) {
            case 'light':   $w = estimate_light_w($state, (int)($state['base_w'] ?? 9)); break;
            case 'plug':    $w = estimate_plug_w($state, (int)$d['power_w']); break;
            case 'ac':      $w = estimate_ac_w($state); break;
            case 'tv':      $w = estimate_tv_w($state, 100); break;
            case 'pc':      $w = estimate_pc_w($state); break;
            case 'speaker': $w = estimate_speaker_w($state); break;
            case 'fridge':  $w = estimate_fridge_w($state); break;
            case 'washer':  $w = estimate_washer_w($state); break;
            case 'camera':  $w = estimate_camera_w($state); break;
            default:        $w = estimate_plug_w($state, (int)$d['power_w']); break;
        }
        $la = $d['last_active'] ? strtotime($d['last_active']) : $now;
        if ($la <= $sliceEnd) { $sumW += $w; }
    }
    $series[$h] = $sumW;
}

include __DIR__ . '/../includes/header.php';
?>
<h1>Dashboard</h1>

<div class="cards">
    <div class="card stat"><div class="k">Total Devices</div><div class="v"><?php echo (int)$total_devices; ?></div></div>
    <div class="card stat"><div class="k">Devices ON</div><div class="v"><?php echo (int)$on_count; ?></div></div>
    <div class="card stat"><div class="k">Current Load</div><div class="v"><?php echo (int)$current_load_w; ?> W</div></div>
    <div class="card stat"><div class="k">kWh Today</div><div class="v"><?php echo number_format($kwh_today, 2); ?></div></div>
</div>

<div class="grid two">
    <section class="card">
        <h2>Longest Running ON</h2>
        <table>
            <tr><th>Device</th><th>Type</th><th>Room</th><th>Duration</th></tr>
            <?php foreach ($longest as $e): ?>
                <tr>
                    <td><?php echo h($e['name']); ?></td>
                    <td><?php echo h($e['type']); ?></td>
                    <td><?php echo h($e['room']); ?></td>
                    <td data-start="<?php echo h($e['last_active_iso'] ?? ''); ?>"><?php echo h(fmt_duration((int)$e['duration'])); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </section>
    <section class="card">
        <h2>AI Insights</h2>
        <form id="runInsightsForm" method="post" action="<?php echo h(BASE_URL); ?>/api/run_insights.php">
            <button type="submit">Run AI Insights</button>
        </form>
        <div id="insightsResult" class="muted" style="margin-top:6px"></div>
        <?php
           $maxW = max(1, (int)max($series));
           echo '<div class="mini-chart" id="powerChart" aria-label="Estimated hourly power (W)">';
           echo '<div class="chart-tooltip" id="chartTip"></div>';
           foreach ($series as $h => $w) {
             $pct = (int)round(($w / $maxW) * 100);
             $cls = $w>0 ? 'bar' : 'bar zero';
             $hourLabel = str_pad((string)$h, 2, '0', STR_PAD_LEFT) . ':00';
             echo '<div class="'.$cls.'" data-hour="'.$hourLabel.'" data-w="'.$w.'" style="height:'.$pct.'%"></div>';
           }
           echo '<div class="xlabels">';
           for ($i=0;$i<24;$i+=3) { $lbl = str_pad((string)$i,2,'0',STR_PAD_LEFT); echo '<span>'.$lbl.'</span>'; }
           echo '</div>';
           echo '</div>';
           echo '<div class="mini-chart-legend">Today hourly load (W). Peak: '.$maxW.' W</div>';
        ?>
    </section>
</div>

<script>
// Live update durations every second using last_active ISO timestamps
(function(){
  function fmt(sec){
    sec = Math.max(0, Math.floor(sec));
    const h = Math.floor(sec/3600), m = Math.floor((sec%3600)/60), s = sec%60;
    if (h>0) return String(h).padStart(2,'0')+":"+String(m).padStart(2,'0')+":"+String(s).padStart(2,'0');
    return String(m).padStart(2,'0')+":"+String(s).padStart(2,'0');
  }
  function tick(){
    const now = Date.now();
    document.querySelectorAll('td[data-start]').forEach(td => {
      const iso = td.getAttribute('data-start');
      if(!iso) return;
      const t = new Date(iso).getTime();
      if (isNaN(t)) return;
      const sec = (now - t) / 1000;
      td.textContent = fmt(sec);
    });
  }
  tick();
  setInterval(tick, 1000);
})();

// Intercept Run Insights to keep user on dashboard and show result
(function(){
  const f = document.getElementById('runInsightsForm');
  if(!f) return;
  const res = document.getElementById('insightsResult');
  f.addEventListener('submit', async (e)=>{
    e.preventDefault();
    res.textContent = 'Running insights...';
    try{
      const r = await fetch(f.action, { method:'POST', headers:{'Accept':'application/json'} });
      const j = await r.json();
      if(j.ok){
        res.innerHTML = `Saved ${j.count} insight(s). <a class="btn" href="<?php echo h(BASE_URL); ?>/pages/insights.php">View insights</a>`;
      } else {
        res.textContent = 'AI service error';
      }
    }catch(err){
      res.textContent = 'Failed to run insights';
    }
  });
})();

// Chart tooltips
(function(){
  const chart = document.getElementById('powerChart');
  if(!chart) return;
  const tip = document.getElementById('chartTip');
  function showTip(text, x, y){
    tip.textContent = text;
    tip.style.display = 'block';
    const rect = chart.getBoundingClientRect();
    const tx = x - rect.left + 10;
    const ty = y - rect.top - 10;
    tip.style.left = tx + 'px';
    tip.style.top = ty + 'px';
  }
  function hideTip(){ tip.style.display = 'none'; }
  chart.querySelectorAll('.bar').forEach(bar => {
    bar.addEventListener('mousemove', (ev)=>{
      const hour = bar.getAttribute('data-hour')||'';
      const w = bar.getAttribute('data-w')||'0';
      showTip(`${hour} — ${w} W`, ev.clientX, ev.clientY);
    });
    bar.addEventListener('mouseleave', hideTip);
  });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
