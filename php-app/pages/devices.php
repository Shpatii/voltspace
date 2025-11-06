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
// Load homes and rooms for selection
$stmt = $db->prepare('SELECT id, name FROM homes WHERE user_id=? ORDER BY name');
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$homes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$rooms = [];
if ($homes) {
    $stmt = $db->prepare('SELECT r.id, r.name, r.home_id FROM rooms r INNER JOIN homes h ON r.home_id=h.id WHERE h.user_id=? ORDER BY r.name');
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $rooms = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// List devices
$stmt = $db->prepare('SELECT d.*, r.name AS room_name FROM devices d INNER JOIN rooms r ON d.room_id=r.id INNER JOIN homes h ON r.home_id=h.id WHERE h.user_id=? ORDER BY d.created_at DESC');
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$devices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

function room_name(array $rooms, int $rid): string {
    foreach ($rooms as $r) if ((int)$r['id'] === $rid) return $r['name'];
    return '#'.$rid;
}

include __DIR__ . '/../includes/header.php';
?>
<h1>Devices</h1>

<div class="grid device-split">
    <section class="card">
        <h2>Device List</h2>
        <table>
            <tr><th>Name</th><th>Type</th><th>Room</th><th>Category</th><th>State</th><th>Power W</th><th>Last Active</th><th>Actions</th></tr>
            <?php foreach ($devices as $d): $state = safe_json_decode($d['state_json']); $on = $state['on'] ?? false; ?>
                <tr>
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
                    <td>
                        <span class="badge <?php echo $on ? 'ok' : '';?>"><?php echo $on ? 'ON' : 'OFF'; ?></span>
                        <?php if ($d['type']==='light'): ?>
                            <small>Brightness: <?php echo (int)($state['brightness'] ?? 100); ?>%</small>
                        <?php elseif ($d['type']==='ac'): ?>
                            <small>Setpoint: <?php echo (int)($state['setpoint'] ?? 24); ?>°C</small>
                        <?php endif; ?>
                    </td>
                    <td><?php echo (int)$d['power_w']; ?></td>
                    <td><?php echo h(fmt_datetime($d['last_active'])); ?></td>
                    <td>
                        <form method="post" action="<?php echo h(BASE_URL); ?>/api/toggle_device.php" style="display:inline">
                            <input type="hidden" name="device_id" value="<?php echo (int)$d['id']; ?>">
                            <button type="submit"><?php echo $on ? 'Turn OFF' : 'Turn ON'; ?></button>
                        </form>
                        <a class="btn" href="<?php echo h(BASE_URL); ?>/pages/device_edit.php?id=<?php echo (int)$d['id']; ?>">Edit</a>
                        <form method="post" action="<?php echo h(BASE_URL); ?>/api/delete_device.php" style="display:inline" onsubmit="return confirm('Delete this device?')">
                            <input type="hidden" name="device_id" value="<?php echo (int)$d['id']; ?>">
                            <button type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </section>
    <section class="card">
        <h2>Add Device (Wizard)</h2>
        <form method="post" action="<?php echo h(BASE_URL); ?>/api/add_device.php">
            <label>Home & Room
                <select name="room_id" required>
                    <option value="">Select room</option>
                    <?php foreach ($rooms as $r): ?>
                        <option value="<?php echo (int)$r['id']; ?>"><?php echo h(room_name($homes, (int)$r['home_id']).' / '.$r['name']); ?></option>
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

<?php include __DIR__ . '/../includes/footer.php'; ?>
