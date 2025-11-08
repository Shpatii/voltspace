<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/utils.php';
require_login();
$db = get_db();
$user = current_user();

// Ensure pricing columns exist
vs_ensure_home_energy_columns($db);

// Configurable assumptions
$AVG_KOSOVO_MONTH_KWH = 800;            // Average monthly consumption baseline (Kosovo)
$SAVED_PER_DAY_KWH = 1.1;               // Savings from using AI insights/assistant
$DAYS_PER_MONTH = 30;                   // Approximation for monthly calc
$SAVED_PER_MONTH_KWH = $SAVED_PER_DAY_KWH * $DAYS_PER_MONTH;
$SAVED_PER_YEAR_KWH = $SAVED_PER_DAY_KWH * 365;

// Subscription pricing (EUR)
$SUB_MONTH_EUR = 2.99;   // monthly plan
$SUB_YEAR_EUR  = 29.99;  // annual plan offer

// Load homes with pricing
$stmt = $db->prepare('SELECT id, name, country, energy_price_cents_per_kwh AS cents, currency FROM homes WHERE user_id=? ORDER BY name');
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$homes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Build per-home savings model
$rows = [];
foreach ($homes as $h) {
    $code = strtoupper(trim($h['country'] ?? 'XK'));
    $cents = (int)($h['cents'] ?? 0);
    $cur = $h['currency'] ?: 'EUR';
    if ($cents <= 0) {
        $def = vs_country_default_price($code);
        $cents = (int)($def['cents_per_kwh'] ?? 20);
        $cur = $def['currency'] ?? $cur;
    }
    $price = $cents / 100.0; // per kWh in local currency
    if ($code === 'XK') { $price = 0.08; $cur = 'EUR'; }

    $baseline_month_kwh = $AVG_KOSOVO_MONTH_KWH;
    $with_month_kwh = max(0.0, $baseline_month_kwh - $SAVED_PER_MONTH_KWH);

    $baseline_year_kwh = $baseline_month_kwh * 12.0; // 9600
    $with_year_kwh = max(0.0, $baseline_year_kwh - $SAVED_PER_YEAR_KWH);

    $baseline_month_cost = $baseline_month_kwh * $price;
    $with_month_cost = $with_month_kwh * $price;
    $save_month_kwh = $baseline_month_kwh - $with_month_kwh; // == $SAVED_PER_MONTH_KWH
    $save_month_value = $save_month_kwh * $price;

    $baseline_year_cost = $baseline_year_kwh * $price;
    $with_year_cost = $with_year_kwh * $price;
    $save_year_kwh = $baseline_year_kwh - $with_year_kwh;   // == $SAVED_PER_YEAR_KWH
    $save_year_value = $save_year_kwh * $price;

    // Optional display override (marketing example): show â‚¬137/yr for XK homes
    $save_year_value_display = $save_year_value;
    if ($code === 'XK' && strtoupper($cur) === 'EUR') {
        $save_year_value_display = 137.00;
    }

    // Net savings versus subscription (use displayed yearly savings figure for consistency)
    $net_year_vs_monthly_plan_eur = $save_year_value_display - ($SUB_MONTH_EUR * 12);
    $net_year_vs_yearly_plan_eur = $save_year_value_display - $SUB_YEAR_EUR;

    // Derive consistent displayed monthly savings and with-costs when using yearly override
    $save_month_value_display = $save_month_value;
    $with_month_cost_display = $with_month_cost;
    $with_year_cost_display = $with_year_cost;
    if ($code === 'XK' && strtoupper($cur) === 'EUR') {
        $save_month_value_display = $save_year_value_display / 12.0;
        $with_month_cost_display = max(0.0, $baseline_month_cost - $save_month_value_display);
        $with_year_cost_display = max(0.0, $baseline_year_cost - $save_year_value_display);
        // Recompute net using display values (already done above for yearly)
        $net_year_vs_monthly_plan_eur = $save_year_value_display - ($SUB_MONTH_EUR * 12);
        $net_year_vs_yearly_plan_eur = $save_year_value_display - $SUB_YEAR_EUR;
    }

    $rates = vs_country_rates();
    $flag = $rates[$code]['flag'] ?? 'dY?3ï¿½,?';
    $cname = $rates[$code]['name'] ?? $code;

    $rows[] = [
        'home' => $h,
        'code' => $code,
        'flag' => $flag,
        'country' => $cname,
        'currency' => $cur,
        'price' => $price,
        'baseline_month_kwh' => $baseline_month_kwh,
        'with_month_kwh' => $with_month_kwh,
        'save_month_kwh' => $save_month_kwh,
        'baseline_month_cost' => $baseline_month_cost,
        'with_month_cost' => $with_month_cost,
        'with_month_cost_display' => $with_month_cost_display,
        'save_month_value' => $save_month_value,
        'save_month_value_display' => $save_month_value_display,
        'baseline_year_kwh' => $baseline_year_kwh,
        'with_year_kwh' => $with_year_kwh,
        'save_year_kwh' => $save_year_kwh,
        'baseline_year_cost' => $baseline_year_cost,
        'with_year_cost' => $with_year_cost,
        'with_year_cost_display' => $with_year_cost_display,
        'save_year_value' => $save_year_value,
        'save_year_value_display' => $save_year_value_display,
        'net_year_vs_monthly_plan_eur' => $net_year_vs_monthly_plan_eur,
        'net_year_vs_yearly_plan_eur' => $net_year_vs_yearly_plan_eur,
    ];
}

include __DIR__ . '/../includes/header.php';
?>
<h1>Savings Report</h1>
<p class="muted">Comparing an average Kosovo household baseline of 800 kWh/month to expected usage when following VoltSpace AI insights (saving ~1.1 kWh/day). Prices adapt per homeâ€™s configured country rate.</p>

<?php if (!$rows): ?>
  <section class="card"><p>No homes yet. Add a home to see your savings.</p></section>
<?php else: ?>
  <?php foreach ($rows as $r): $cur=$r['currency']; ?>
    <section class="card" style="margin-bottom:16px">
      <h2 style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
        <span class="icon"><?php echo h($r['flag']); ?></span>
        <?php echo h($r['home']['name']); ?>
        <small class="muted" style="font-weight:normal">(<?php echo h($r['country']); ?>)</small>
      </h2>
      <table>
        <tr>
          <th>Metric</th><th>Monthly</th><th>Yearly</th>
        </tr>
        <tr>
          <td>Baseline (avg. Kosovo)</td>
          <td><?php echo number_format($r['baseline_month_kwh'], 0); ?> kWh Â· <?php echo h($cur).' '.number_format($r['baseline_month_cost'], 2); ?></td>
          <td><?php echo number_format($r['baseline_year_kwh'], 0); ?> kWh Â· <?php echo h($cur).' '.number_format($r['baseline_year_cost'], 2); ?></td>
        </tr>
        <tr>
          <td>With VoltSpace (following AI)</td>
          <td><?php echo number_format($r['with_month_kwh'], 2); ?> kWh Â· <?php echo h($cur).' '.number_format($r['with_month_cost_display'] ?? $r['with_month_cost'], 2); ?></td>
          <td><?php echo number_format($r['with_year_kwh'], 2); ?> kWh Â· <?php echo h($cur).' '.number_format($r['with_year_cost_display'] ?? $r['with_year_cost'], 2); ?></td>
        </tr>
        <tr>
          <td>Savings</td>
          <td><?php echo number_format($r['save_month_kwh'], 2); ?> kWh Â· <?php echo h($cur).' '.number_format($r['save_month_value_display'] ?? $r['save_month_value'], 2); ?></td>
          <td><?php echo number_format($r['save_year_kwh'], 2); ?> kWh Â· <?php echo h($cur).' '.number_format($r['save_year_value_display'], 2); ?></td>
        </tr>
      </table>
      <p class="muted" style="margin-top:8px">Subscription: &euro;2.99/month or &euro;29.99/year. Net yearly vs plans (approx., comparing to savings in local currency):
        <br>&bull; Net vs monthly plan: <?php echo '&euro; '.number_format($r['net_year_vs_monthly_plan_eur'], 2); ?>
        <br>&bull; Net vs yearly plan: <?php echo '&euro; '.number_format($r['net_year_vs_yearly_plan_eur'], 2); ?>
      </p>
    </section>
  <?php endforeach; ?>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
