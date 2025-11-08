<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/utils.php';
require_login();
$db = get_db();
$user = current_user();

// Ensure columns exist (first-time upgrade)
vs_ensure_home_energy_columns($db);

// Load homes
$stmt = $db->prepare('SELECT id, name, country, energy_price_cents_per_kwh AS cents, currency FROM homes WHERE user_id=? ORDER BY name');
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$homes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$midnight = strtotime(date('Y-m-d 00:00:00'));
$now = time();

function device_watts(array $d): int {
    $state = safe_json_decode($d['state_json']);
    switch ($d['type']) {
        case 'light':
            $base = (int)($state['base_w'] ?? 9);
            return ($state['on'] ?? false) ? (int)round($base * ((int)($state['brightness'] ?? 100)) / 100) : 0;
        case 'ac':
            if (!($state['on'] ?? false)) return 0;
            $set = (int)($state['setpoint'] ?? 24); $h=(int)date('G'); $b=700; if($set<22) $b+=250; if($h>=12&&$h<=18) $b+=200; return min(1200,$b);
        case 'tv':
            if (!($state['on'] ?? false)) return 0; $b=(int)($state['brightness'] ?? 70); return max(20,(int)round(100*max(10,min(100,$b))/100));
        case 'pc':
            if (!($state['on'] ?? false)) return 5; $l=(int)($state['load'] ?? 20); return (int)round(60 + (250-60)*max(0,min(100,$l))/100);
        case 'speaker':
            if (!($state['on'] ?? false)) return 0; $v=(int)($state['volume'] ?? 30); return (int)round(3+0.25*max(0,min(100,$v)));
        case 'fridge':
            $min=(int)date('i'); $comp=($min%10)<3; $w=$comp?120:8; if(!empty($state['door_open'])) $w+=20; if(!empty($state['eco'])) $w=(int)round($w*0.9); return $w;
        case 'washer':
            if (!($state['on'] ?? false)) return 0; $p=strtolower((string)($state['phase']??'wash')); if($p==='spin')return 800; if($p==='wash')return 500; if($p==='heat')return 1200; return 10;
        case 'camera':
            return ($state['on'] ?? true) ? 5 : 0;
        default:
            return (($state['on'] ?? false) ? (int)$d['power_w'] : 0);
    }
}

// Build report per home
$report = [];
foreach ($homes as $h) {
    $hid = (int)$h['id'];
    // Devices in this home
    $stmtD = $db->prepare('SELECT d.* FROM devices d INNER JOIN rooms r ON d.room_id=r.id WHERE r.home_id=?');
    $stmtD->bind_param('i', $hid);
    $stmtD->execute();
    $devs = $stmtD->get_result()->fetch_all(MYSQLI_ASSOC);

    $kwh_today = 0.0;
    foreach ($devs as $d) {
        $w = device_watts($d);
        // Integrate ON time since midnight using logs
        $stmtPrev = $db->prepare('SELECT payload, UNIX_TIMESTAMP(created_at) ts FROM device_logs WHERE device_id=? AND created_at<=FROM_UNIXTIME(?) ORDER BY created_at DESC LIMIT 1');
        $tsMid = $midnight; $did = (int)$d['id'];
        $stmtPrev->bind_param('ii', $did, $tsMid);
        $stmtPrev->execute();
        $prev = $stmtPrev->get_result()->fetch_assoc();
        $state_at_mid = false;
        if ($prev && $prev['payload']) { $p=json_decode($prev['payload'],true); if(is_array($p)&&array_key_exists('to',$p)) $state_at_mid=(bool)$p['to']; }
        $stmtLogs = $db->prepare('SELECT payload, UNIX_TIMESTAMP(created_at) ts FROM device_logs WHERE device_id=? AND created_at>FROM_UNIXTIME(?) ORDER BY created_at ASC');
        $stmtLogs->bind_param('ii', $did, $tsMid);
        $stmtLogs->execute();
        $rows = $stmtLogs->get_result()->fetch_all(MYSQLI_ASSOC);
        $cursor = $midnight; $st = $state_at_mid; $on_sec = 0;
        foreach ($rows as $lg) { $ts=(int)$lg['ts']; if ($st) $on_sec += max(0,$ts-$cursor); $pl=json_decode($lg['payload']??'',true); if(is_array($pl)&&array_key_exists('to',$pl)) $st=(bool)$pl['to']; $cursor=$ts; }
        if ($st) $on_sec += max(0, $now - $cursor);
        $kwh_today += ($w * $on_sec) / 3600000.0;
    }
    $price = (int)($h['cents'] ?? 0) / 100.0; $cur = $h['currency'] ?: 'EUR';
    $cost_today = $price * $kwh_today;
    $mtd = $cost_today * (int)date('j');
    $ytd = $cost_today * ((int)date('z') + 1);
    $report[] = [
        'home' => $h,
        'kwh_today' => $kwh_today,
        'cost_today' => $cost_today,
        'mtd' => $mtd,
        'ytd' => $ytd,
    ];
}

include __DIR__ . '/../includes/header.php';
?>
<h1>Energy Report</h1>

<section class="card">
  <table>
    <tr><th>Home</th><th>Location</th><th>kWh Today</th><th>Cost Today</th><th>MTD (est.)</th><th>YTD (est.)</th></tr>
    <?php foreach ($report as $r): $h=$r['home']; $c=strtoupper($h['country']??''); $rates=vs_country_rates(); $flag=$rates[$c]['flag']??'🏳️'; $cname=$rates[$c]['name']??$c; $cur=$h['currency']?:'EUR'; $pc=(int)($h['cents']??0)/100.0; ?>
      <tr>
        <td><?php echo h($h['name']); ?></td>
        <td><span class="icon"><?php echo h($flag); ?></span> <?php echo h($cname); ?></td>
        <td><?php echo number_format($r['kwh_today'], 2); ?></td>
        <td><?php echo h($cur).' '.number_format($r['cost_today'], 2); ?></td>
        <td><?php echo h($cur).' '.number_format($r['mtd'], 2); ?></td>
        <td><?php echo h($cur).' '.number_format($r['ytd'], 2); ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
  <p class="muted">MTD/YTD are rough estimates extrapolated from today's usage.</p>
  <p class="muted">Tip: open Devices and toggle items to reflect today's activity; logs are used to accumulate on-time.</p>
</section>

<script>
// Turn price cells into live calculators and update cost cells on the fly
(function(){
  const tbl = document.querySelector('section.card table');
  if(!tbl) return;
  const rows = Array.from(tbl.querySelectorAll('tr')).slice(1); // skip header
  rows.forEach(tr => {
    const tds = tr.querySelectorAll('td');
    if (tds.length < 7) return;
    const priceTd = tds[2];
    const kwhTd = tds[3];
    const costTd = tds[4];
    const mtdTd = tds[5];
    const ytdTd = tds[6];

    // Parse current values
    const priceTxt = priceTd.textContent.trim();
    const m = priceTxt.match(/^(\w{3})\s+([0-9]+(?:\.[0-9]+)?)\s*\/kWh/i);
    const cur = m ? m[1] : 'EUR';
    const price = m ? parseFloat(m[2]) : 0;
    const kwh = parseFloat((kwhTd.textContent||'0').replace(/[^0-9.]/g,'')) || 0;

    // Build widgets
    const sel = document.createElement('select');
    ['EUR','USD','ALL'].forEach(c=>{ const o=document.createElement('option'); o.value=c; o.textContent=c; if(c===cur) o.selected=true; sel.appendChild(o); });
    sel.className='currency'; sel.setAttribute('aria-label','Currency');
    const inp = document.createElement('input'); inp.type='number'; inp.className='price'; inp.step='0.01'; inp.min='0'; inp.value = isFinite(price)? price.toFixed(2) : '0.00';
    const slash = document.createElement('span'); slash.className='muted'; slash.textContent=' /kWh';
    priceTd.textContent=''; priceTd.appendChild(sel); priceTd.appendChild(inp); priceTd.appendChild(slash);

    function recalc(){
      const p = parseFloat(inp.value||'0')||0; const c= sel.value||'EUR';
      const cost = kwh * p; const now = new Date();
      const mtd = cost * now.getDate();
      const ytd = cost * (Math.floor((Date.now() - new Date(now.getFullYear(),0,1).getTime())/86400000) + 1);
      costTd.textContent = c + ' ' + cost.toFixed(2);
      mtdTd.textContent = c + ' ' + mtd.toFixed(2);
      ytdTd.textContent = c + ' ' + ytd.toFixed(2);
    }
    sel.addEventListener('change', recalc);
    inp.addEventListener('input', recalc);
  });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

