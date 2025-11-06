<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/utils.php';
require_login();
$db = get_db();
$user = current_user();

$id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
if ($id <= 0) { http_response_code(400); echo 'Invalid id'; exit; }

// Load device ensuring ownership
$stmt = $db->prepare('SELECT d.*, r.name AS room_name, r.id AS room_id
  FROM devices d INNER JOIN rooms r ON d.room_id=r.id INNER JOIN homes h ON r.home_id=h.id
  WHERE d.id=? AND h.user_id=? LIMIT 1');
$stmt->bind_param('ii', $id, $user['id']);
$stmt->execute();
$dev = $stmt->get_result()->fetch_assoc();
if (!$dev) { http_response_code(404); echo 'Device not found'; exit; }

$state = safe_json_decode($dev['state_json']);
$error = null; $ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? $dev['name']);
    $category = $_POST['category'] ?? ($state['category'] ?? 'essential');
    $power_w = (int)($_POST['power_w'] ?? $dev['power_w']);
    $on = isset($_POST['on']);
    $brightness = isset($_POST['brightness']) ? (int)$_POST['brightness'] : null;
    $setpoint = isset($_POST['setpoint']) ? (int)$_POST['setpoint'] : null;
    $last_active = trim($_POST['last_active'] ?? '');

    $st = $state;
    $st['on'] = $on;
    $st['category'] = $category;
    if ($dev['type'] === 'light' && $brightness !== null) { $st['brightness'] = max(1, min(100, $brightness)); }
    if ($dev['type'] === 'ac' && $setpoint !== null) { $st['setpoint'] = max(16, min(30, $setpoint)); }

    $state_json = json_encode($st);
    $la = $last_active ? date('Y-m-d H:i:s', strtotime($last_active)) : null;

    $stmt = $db->prepare('UPDATE devices SET name=?, state_json=?, power_w=?, last_active=? WHERE id=?');
    $stmt->bind_param('ssisi', $name, $state_json, $power_w, $la, $id);
    if ($stmt->execute()) { $ok = true; $dev['name']=$name; $dev['power_w']=$power_w; $dev['last_active']=$la; $state=$st; }
}

include __DIR__ . '/../includes/header.php';
?>
<h1>Edit Device</h1>
<?php if ($ok): ?><div class="alert ok">Saved.</div><?php endif; ?>
<form method="post" class="card">
  <input type="hidden" name="id" value="<?php echo (int)$dev['id']; ?>" />
  <div class="grid two">
    <label>Name
      <input type="text" name="name" value="<?php echo h($dev['name']); ?>" required />
    </label>
    <label>Category
      <?php $cats=['essential','non_essential','flexible','pc','tv','speaker','fridge','washer','camera']; ?>
      <select name="category">
        <?php foreach($cats as $c): ?>
          <option value="<?php echo h($c); ?>" <?php echo (($state['category']??'')===$c)?'selected':''; ?>><?php echo h(ucwords(str_replace('_',' ',$c))); ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </div>
  <div class="grid two">
    <label>Power (W)
      <input type="number" name="power_w" value="<?php echo (int)$dev['power_w']; ?>" min="0" />
    </label>
    <label>On
      <input type="checkbox" name="on" <?php echo !empty($state['on'])?'checked':''; ?> />
    </label>
  </div>
  <?php if ($dev['type']==='light'): ?>
  <label>Brightness (1–100)
    <input type="number" name="brightness" min="1" max="100" value="<?php echo (int)($state['brightness']??100); ?>" />
  </label>
  <?php elseif ($dev['type']==='ac'): ?>
  <label>Setpoint (16–30 °C)
    <input type="number" name="setpoint" min="16" max="30" value="<?php echo (int)($state['setpoint']??24); ?>" />
  </label>
  <?php endif; ?>
  <label>Last Active
    <?php $laVal = $dev['last_active'] ? date('Y-m-d\TH:i', strtotime($dev['last_active'])) : ''; ?>
    <input type="datetime-local" name="last_active" value="<?php echo h($laVal); ?>" />
  </label>
  <div>
    <button type="submit">Save</button>
    <a class="btn" href="<?php echo h(BASE_URL); ?>/pages/devices.php">Cancel</a>
  </div>
</form>

<?php include __DIR__ . '/../includes/footer.php'; ?>

