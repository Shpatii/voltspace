<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/utils.php';
require_login();
$db = get_db();
$user = current_user();

$stmt = $db->prepare('SELECT id, name, country FROM homes WHERE user_id=? ORDER BY name');
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$homes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$rates = vs_country_rates();

$home_id = isset($_GET['home_id']) ? (int)$_GET['home_id'] : 0;
if ($home_id === 0 && $homes) $home_id = (int)$homes[0]['id'];

// Add room
$msg = null; $err = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rname = trim($_POST['name'] ?? '');
    $floor = (int)($_POST['floor'] ?? 0);
    $hid = (int)($_POST['home_id'] ?? 0);
    if ($rname === '') { $err = 'Room name required'; }
    if ($hid <= 0) { $err = 'Select a home'; }
    if (!$err) {
        $stmt = $db->prepare('INSERT INTO rooms(home_id, name, floor, created_at) VALUES (?, ?, ?, NOW())');
        $stmt->bind_param('isi', $hid, $rname, $floor);
        if ($stmt->execute()) { $msg = 'Room added.'; } else { $err = 'Failed to add room'; }
    }
}

// Rooms list
if ($home_id > 0) {
    $stmt = $db->prepare('SELECT r.id, r.name, r.floor, r.created_at FROM rooms r INNER JOIN homes h ON r.home_id=h.id WHERE h.user_id=? AND h.id=? ORDER BY r.created_at DESC');
    $stmt->bind_param('ii', $user['id'], $home_id);
} else {
    $stmt = $db->prepare('SELECT r.id, r.name, r.floor, r.created_at FROM rooms r INNER JOIN homes h ON r.home_id=h.id WHERE h.user_id=? ORDER BY r.created_at DESC');
    $stmt->bind_param('i', $user['id']);
}
$stmt->execute();
$rooms = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../includes/header.php';
?>
<!-- wrap page so styles only apply here -->
<div class="rooms-page">
  <h1>Rooms</h1>
  <?php if ($msg): ?><div class="alert ok"><?php echo h($msg); ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert warn"><?php echo h($err); ?></div><?php endif; ?>

  <div class="filters">
      <form method="get">
          <label>Home
              <select name="home_id" onchange="this.form.submit()">
                  <?php foreach ($homes as $h): ?>
                      <?php foreach ($homes as $h): $flag = $rates[strtoupper($h['country'] ?? '')]['flag'] ?? '🏳️'; ?>
                          <option value="<?php echo (int)$h['id']; ?>" <?php echo ((int)$h['id'] === $home_id) ? 'selected' : ''; ?>><?php echo h($flag).' '.h($h['name']); ?></option>
                      <?php endforeach; ?>
                  <?php endforeach; ?>
              </select>
          </label>
      </form>
      <a class="btn" href="<?php echo h(BASE_URL); ?>/pages/homes.php">Manage Homes</a>
      </div>

  <div class="grid two">
      <section class="card">
          <h2>Rooms</h2>
          <table>
              <tr><th>Name</th><th>Floor</th><th>Created</th></tr>
              <?php foreach ($rooms as $r): ?>
                  <tr>
                      <td><?php echo h($r['name']); ?></td>
                      <td><?php echo (int)$r['floor']; ?></td>
                      <td><?php echo h(fmt_datetime($r['created_at'])); ?></td>
                  </tr>
              <?php endforeach; ?>
          </table>
      </section>
      <section class="card">
          <h2>Add Room</h2>
          <form method="post">
              <label>Home
                  <select name="home_id" required>
                      <option value="">Select a home</option>
                      <?php foreach ($homes as $h): ?>
                          <?php foreach ($homes as $h): $flag = $rates[strtoupper($h['country'] ?? '')]['flag'] ?? '🏳️'; ?>
                              <option value="<?php echo (int)$h['id']; ?>" <?php echo ((int)$h['id'] === $home_id) ? 'selected' : ''; ?>><?php echo h($flag).' '.h($h['name']); ?></option>
                          <?php endforeach; ?>
                      <?php endforeach; ?>
                  </select>
              </label>
              <label>Name
                  <input type="text" name="name" required>
              </label>
              <label>Floor
                  <input type="number" name="floor" value="0">
              </label>
              <button type="submit">Add Room</button>
          </form>
      </section>
  </div>
</div>
<?php
include __DIR__ . '/../includes/footer.php';
?>
