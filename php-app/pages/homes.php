<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/utils.php';
require_login();
$db = get_db();
$user = current_user();

// Ensure schema has pricing columns (idempotent)
vs_ensure_home_energy_columns($db);

// Add
$msg = null; $err = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $country = strtoupper(trim($_POST['country'] ?? 'XK'));
    $price_cents = (int)($_POST['price_cents'] ?? 0);
    $currency = trim($_POST['currency'] ?? 'EUR');
    if ($name === '') { $err = 'Name is required'; }
    if (!$err) {
        $stmt = $db->prepare('INSERT INTO homes(user_id, name, address, country, energy_price_cents_per_kwh, currency, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
        $stmt->bind_param('isssis', $user['id'], $name, $address, $country, $price_cents, $currency);
        if ($stmt->execute()) { $msg = 'Home added.'; } else { $err = 'Failed to add home'; }
    }
}

// List
$stmt = $db->prepare('SELECT id, name, address, country, energy_price_cents_per_kwh, currency, created_at FROM homes WHERE user_id=? ORDER BY created_at DESC');
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$homes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../includes/header.php';
?>
<h1>Homes</h1>
<?php if ($msg): ?><div class="alert ok"><?php echo h($msg); ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert warn"><?php echo h($err); ?></div><?php endif; ?>

<div class="grid two">
    <section class="card">
        <h2>Your Homes</h2>
        <table>
            <tr><th>Name</th><th>Address</th><th>Location</th><th>Energy Price</th><th>Created</th></tr>
            <?php foreach ($homes as $h): ?>
                <tr>
                    <td><?php echo h($h['name']); ?></td>
                    <td><?php echo h($h['address']); ?></td>
                    <td><?php $c = strtoupper($h['country'] ?? ''); $rates = vs_country_rates(); $flag = $rates[$c]['flag'] ?? '🏳️'; $cname = $rates[$c]['name'] ?? $c; echo '<span class="icon">'.h($flag).'</span> '.h($cname); ?></td>
                    <td><?php $cur = $h['currency'] ?? 'EUR'; $pc = (int)($h['energy_price_cents_per_kwh'] ?? 0); echo $cur.' '.number_format($pc/100, 2).'/kWh'; ?></td>
                    <td><?php echo h(fmt_datetime($h['created_at'])); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </section>
    <section class="card">
        <h2>Add Home</h2>
        <form method="post" id="homeForm">
            <label>Name
                <input type="text" name="name" required>
            </label>
            <label>Address
                <input type="text" name="address">
            </label>
            <label>Country / Location
                <select name="country" id="country" required>
                    <?php $cr = vs_country_rates(); ?>
                    <option value="XK">🇽🇰 Kosovo</option>
                    <option value="AL">🇦🇱 Albania</option>
                    <option value="LU">🇱🇺 Luxembourg</option>
                </select>
            </label>
            <label>Energy Price (per kWh)
                <div style="display:flex;gap:8px">
                    <select name="currency" id="currency" style="max-width:110px">
                        <option value="EUR">EUR</option>
                        <option value="ALL">ALL</option>
                    </select>
                    <input type="number" step="0.01" name="price" id="price" placeholder="0.20">
                </div>
                <input type="hidden" name="price_cents" id="price_cents">
                <small class="muted">Auto-filled from country; you can adjust.</small>
            </label>
            <button type="submit">Add Home</button>
        </form>
    </section>
</div>

<script>
// Autofill price by country and keep cents hidden field updated
(function(){
  const rates = {
    XK: {currency:'EUR', cents: Math.round(0.09*100)},
    AL: {currency:'ALL', cents: Math.round(12*100)},
    LU: {currency:'EUR', cents: Math.round(0.28*100)}
  };
  const $country = document.getElementById('country');
  const $currency = document.getElementById('currency');
  const $price = document.getElementById('price');
  const $cents = document.getElementById('price_cents');
  function apply(){
    const r = rates[$country.value] || {currency:'EUR', cents:20};
    $currency.value = r.currency;
    $price.value = (r.cents/100).toFixed(2);
    $cents.value = String(r.cents);
  }
  function sync(){
    const val = parseFloat($price.value||'0');
    $cents.value = String(Math.round(val*100));
  }
  $country.addEventListener('change', apply);
  $price.addEventListener('input', sync);
  apply();
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
