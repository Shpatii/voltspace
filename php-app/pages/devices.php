<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/utils.php';
require_login();
$db = get_db();
$user = current_user();

// Icon maps for device types and categories (simple emoji for minimal setup)
$TYPE_ICONS = [
    'light' => '💡',
    'ac'    => '❄️',
    'plug'  => '🔌',
    'sensor'=> '📟',
    'pc'    => '💻',
    'tv'            => '📺',
    'speaker'       => '🔊',
    'fridge'        => '🧊',
    'washer'        => '🧺',
    'camera'        => '🎥',
];
$CATEGORY_ICONS = [
    'essential'     => '⭐',
    'non_essential' => '⏱️',
    'flexible'      => '🔄',
];
$stmt = $db->prepare('SELECT id, name, country FROM homes WHERE user_id=? ORDER BY name');
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$homes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Build quick lookup map by id and load country flag data
$home_map = [];
foreach ($homes as $hh) { $home_map[(int)$hh['id']] = $hh; }
$rates = vs_country_rates();

// allow optional initial selection from querystring
$selected_home = isset($_GET['home_id']) ? (int)$_GET['home_id'] : 0;

$rooms = [];
if ($homes) {
    $stmt = $db->prepare('SELECT r.id, r.name, r.home_id FROM rooms r INNER JOIN homes h ON r.home_id=h.id WHERE h.user_id=? ORDER BY r.name');
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $rooms = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// List devices (include room.home_id so we can filter client-side)
$stmt = $db->prepare('SELECT d.*, r.name AS room_name, r.home_id FROM devices d INNER JOIN rooms r ON d.room_id=r.id INNER JOIN homes h ON r.home_id=h.id WHERE h.user_id=? ORDER BY d.created_at DESC');
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$devices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

function room_name(array $rooms, int $rid): string {
    foreach ($rooms as $r) if ((int)$r['id'] === $rid) return $r['name'];
    return '#'.$rid;
}

include __DIR__ . '/../includes/header.php';
?>
<section class="page-header">
    <div class="title-wrap">
        <h1>Devices</h1>
        <p class="page-subtitle">Monitor and manage every device across your homes.</p>
    </div>
</section>

<div class="devices-page">

  <!-- Home filter (client-side) -->
  <div class="filters" style="margin-bottom:18px">
    <label style="display:inline-block;font-weight:600;margin-right:12px">Show devices for</label>
    <select id="homeFilter" style="min-width:220px;padding:8px;border-radius:8px">
      <option value="">All homes</option>
      <?php foreach ($homes as $h): $hid=(int)$h['id']; $flag = $rates[strtoupper($h['country'] ?? '')]['flag'] ?? '🏳️'; ?>
        <option value="<?php echo $hid; ?>" <?php echo ($selected_home === $hid) ? 'selected' : ''; ?>>
          <?php echo h($flag . ' ' . $h['name']); ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="grid device-split">
    <section class="card device-list-card">
        <h2>Device List</h2>
        <p class="muted">All devices sorted by most recently added. Use the actions to toggle, edit, or remove.</p>
        <table class="data-table">
            <thead>
                <tr><th>Name</th><th>Type</th><th>Room</th><th>Category</th><th>State</th><th>Power W</th><th>Last Active</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php if (!$devices): ?>
                    <tr><td colspan="8" class="empty">No devices yet. Add your first one using the wizard.</td></tr>
                <?php else: ?>
                    <?php foreach ($devices as $d): $state = safe_json_decode($d['state_json']); $on = $state['on'] ?? false; ?>
                        <tr data-device-id="<?php echo (int)$d['id']; ?>" data-home-id="<?php echo (int)$d['home_id']; ?>">
                            <td><?php $ti = $TYPE_ICONS[$d['type']] ?? '🧩'; echo '<span class="icon">'.h($ti).'</span> '.h($d['name']); ?></td>
                            <td><?php echo h($d['type']); ?></td>
                            <td><?php echo h($d['room_name']); ?></td>
                            <td>
                                <?php $cat = $state['category'] ?? null; if ($cat): $ci = $CATEGORY_ICONS[$cat] ?? '🏷️'; ?>
                                    <span class="badge category-<?php echo h($cat); ?>"><span class="icon"><?php echo h($ci); ?></span><?php echo h(ucwords(str_replace('_',' ', $cat))); ?></span>
                                <?php else: ?>
                                    <span class="muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="cell-state">
                                <span class="badge state-badge <?php echo $on ? 'ok' : '';?>"><?php echo $on ? 'ON' : 'OFF'; ?></span>
                                <?php if ($d['type']==='light'): ?>
                                    <small>Brightness: <?php echo (int)($state['brightness'] ?? 100); ?>%</small>
                                <?php elseif ($d['type']==='ac'): ?>
                                    <small>Setpoint: <?php echo (int)($state['setpoint'] ?? 24); ?>°C</small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo (int)$d['power_w']; ?></td>
                            <td class="cell-last-active"><?php echo h(fmt_datetime($d['last_active'])); ?></td>
                            <td class="table-actions">
                                <div class="table-actions-inner">
                                    <form method="post" action="<?php echo h(BASE_URL); ?>/api/toggle_device.php" class="js-toggle-form">
                                        <input type="hidden" name="device_id" value="<?php echo (int)$d['id']; ?>">
                                        <button type="submit" class="secondary" data-label-on="Turn OFF" data-label-off="Turn ON"><?php echo $on ? 'Turn OFF' : 'Turn ON'; ?></button>
                                    </form>
                                    <a class="btn btn-secondary" href="<?php echo h(BASE_URL); ?>/pages/device_edit.php?id=<?php echo (int)$d['id']; ?>">Edit</a>
                                    <form method="post" action="<?php echo h(BASE_URL); ?>/api/delete_device.php" onsubmit="return confirm('Delete this device?')">
                                        <input type="hidden" name="device_id" value="<?php echo (int)$d['id']; ?>">
                                        <button type="submit" class="danger">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </section>
    <section class="card">
        <h2>Add Device (Wizard)</h2>
        <form method="post" action="<?php echo h(BASE_URL); ?>/api/add_device.php" class="stack-form">
            <label>Home & Room
                <select name="room_id" required>
                    <option value="">Select room</option>
                    <?php foreach ($rooms as $r): ?>
                        <?php $hid = (int)$r['home_id']; $home = $home_map[$hid] ?? null; $flag = $rates[strtoupper($home['country'] ?? '')]['flag'] ?? '🏳️'; $hname = $home ? $home['name'] : room_name($homes, $hid); ?>
                        <option value="<?php echo (int)$r['id']; ?>"><?php echo h($flag) . ' ' . h($hname) . ' / ' . h($r['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Device Type
                <select name="type" required>
                    <option value="light">Light 💡</option>
                    <option value="ac">AC ❄️</option>
                    <option value="plug">Plug 🔌</option>
                    <option value="sensor">Sensor 📟</option>
                    <option value="pc">PC 💻</option>
                    <option value="tv">TV 📺</option>
                    <option value="speaker">Speaker 🔊</option>
                    <option value="fridge">Fridge 🧊</option>
                    <option value="washer">Washer 🧺</option>
                    <option value="camera">Camera 🎥</option>
                </select>
            </label>
            <label>Usage Category
                <select name="category" required>
                    <option value="essential">Essential ⭐</option>
                    <option value="non_essential">Non-essential ⏱️</option>
                    <option value="flexible">Flexible (movable/shiftable) 🔄</option>
                </select>
            </label>
            <p class="muted">Flexible = can move usage to off-peak; Non-essential = can be turned off without impact.</p>
            <label>Device Name
                <input type="text" name="name" placeholder="Living Light" required>
            </label>
            <label>Onboarding Method</label>
            <label class="inline"><input type="radio" name="method" value="serial" checked> Serial</label>
            <label class="inline"><input type="radio" name="method" value="bt"> Bluetooth</label>
            <label>Code
                <input type="text" name="code" placeholder="ENR-DEV-1A2B3C or BT-1234-5678" required>
            </label>
            <button type="submit">Add Device</button>
            <p class="muted">Serial pattern: ENR-DEV-XXXXXX (hex). BT pattern: BT-XXXX-XXXX (digits).</p>
        </form>
    </section>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const forms = document.querySelectorAll('.js-toggle-form');
    forms.forEach(form => {
        form.addEventListener('submit', async (ev) => {
            ev.preventDefault();
            const btn = form.querySelector('button');
            const row = form.closest('tr');
            if (!btn || !row) { form.submit(); return; }
            btn.disabled = true;
            btn.classList.add('loading');
            const originalText = btn.textContent;
            btn.textContent = 'Updating…';
            const formData = new FormData(form);
            try {
                const res = await fetch(form.action, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' },
                    body: formData
                });
                if (!res.ok) throw new Error('Request failed');
                const data = await res.json();
                if (!data || !data.ok) throw new Error('Toggle failed');

                const badge = row.querySelector('.state-badge');
                if (badge) {
                    badge.textContent = data.on ? 'ON' : 'OFF';
                    badge.classList.toggle('ok', !!data.on);
                }
                const toggleBtn = form.querySelector('button');
                if (toggleBtn) {
                    const onLabel = toggleBtn.getAttribute('data-label-on') || 'Turn OFF';
                    const offLabel = toggleBtn.getAttribute('data-label-off') || 'Turn ON';
                    toggleBtn.textContent = data.on ? onLabel : offLabel;
                }
                const lastCell = row.querySelector('.cell-last-active');
                if (lastCell && data.last_active_fmt) {
                    lastCell.textContent = data.last_active_fmt;
                }
            } catch (err) {
                console.error(err);
                form.submit();
            } finally {
                btn.disabled = false;
                btn.classList.remove('loading');
                if (btn.textContent === 'Updating…') {
                    btn.textContent = originalText;
                }
            }
        });
    });

    // new: filter devices by selected home
    const homeFilter = document.getElementById('homeFilter');
    if (homeFilter) {
        const rows = Array.from(document.querySelectorAll('tr[data-device-id]'));
        function applyFilter() {
            const val = homeFilter.value;
            rows.forEach(r => {
                const hid = r.getAttribute('data-home-id') || '';
                r.style.display = (!val || hid === val) ? '' : 'none';
            });
        }
        homeFilter.addEventListener('change', applyFilter);
        // apply initial filter if selected via querystring
        applyFilter();
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
