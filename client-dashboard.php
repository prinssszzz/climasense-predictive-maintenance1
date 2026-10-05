<?php
$requireOrgId = null;
require_once __DIR__ . '/includes/auth.php';
try { $requireOrgId = cs_current_user()['organization_id'] ?? null; } catch (Throwable $_) { $requireOrgId = null; }
cs_require_permission('analytics.view', $requireOrgId);

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/helpers.php';

$fleet = cs_fleet_summary();
$units = cs_units();
$needsAttention = array_values(array_filter($units, fn($unit) => $unit['status'] !== 'healthy'));
usort($needsAttention, fn($a, $b) => $a['health'] <=> $b['health']);
$dueSoon = count(array_filter($units, fn($unit) => $unit['rul_days'] <= 30));
$displayUnits = array_slice($needsAttention ?: $units, 0, 6);
$serviceBuckets = [
  ['label' => 'Due in 30 days', 'min' => 0, 'max' => 30, 'class' => 'crit'],
  ['label' => '31–60 days', 'min' => 31, 'max' => 60, 'class' => 'warn'],
  ['label' => '61–90 days', 'min' => 61, 'max' => 90, 'class' => 'amber'],
  ['label' => '90+ days', 'min' => 91, 'max' => PHP_INT_MAX, 'class' => 'ok'],
];
$serviceBucketCounts = [];
foreach ($serviceBuckets as $bucket) {
  $serviceBucketCounts[] = count(array_filter($units, fn($unit) => $unit['rul_days'] >= $bucket['min'] && $unit['rul_days'] <= $bucket['max']));
}
// Demo-only trend: historical fleet snapshots are not stored by this data source.
$trendOffsets = [-2.4, -1.8, -1.2, -1.5, -0.6, -0.2, 0];
$healthTrend = array_map(fn($offset) => max(0, min(100, $fleet['avg_health'] + $offset)), $trendOffsets);
$alerts = cs_predictive_alerts(2026, 3);
$upcoming = array_slice(array_filter(cs_maintenance_log(), fn($item) => $item['status'] !== 'completed'), 0, 2);
$clientUser = cs_current_user();
$clientOrgId = (int)($clientUser['organization_id'] ?? 0);
$maintenanceRecommendations = [];
$notificationFlash = $_SESSION['cs_client_notification_flash'] ?? null;
unset($_SESSION['cs_client_notification_flash']);
if (empty($_SESSION['cs_client_notification_csrf'])) $_SESSION['cs_client_notification_csrf'] = bin2hex(random_bytes(32));
if ($clientOrgId > 0) {
  cs_ensure_consumer_unit_schema();
  cs_ensure_user_phone_column();
  $recommendationQuery = cs_db()->prepare("SELECT a.unit_code,a.name,a.consumer_user_id,u.name AS customer_name,u.email AS customer_email,u.phone AS customer_phone FROM ac_units a JOIN users u ON u.id=a.consumer_user_id WHERE a.organization_id=? AND a.verification_status='active' ORDER BY u.name,a.unit_code");
  $recommendationQuery->execute([$clientOrgId]);
  $customerUnits = $recommendationQuery->fetchAll();
  $customerUnitCodes = array_fill_keys(array_column($customerUnits, 'unit_code'), true);
  $customerServiceLog = array_values(array_filter(cs_maintenance_log(), fn($entry) => isset($customerUnitCodes[$entry['unit']])));
  foreach ($customerUnits as $customerUnit) {
    $records = array_values(array_filter($customerServiceLog, fn($entry) => $entry['unit'] === $customerUnit['unit_code']));
    $urgent = array_values(array_filter($records, fn($entry) => ($entry['status'] ?? '') === 'urgent'));
    usort($urgent, fn($a, $b) => strcmp($b['date'], $a['date']));
    $pattern = cs_maintenance_interval($records);
    $recommendedDate = $urgent[0]['date'] ?? $pattern['next_date'];
    $maintenanceStatus = cs_maintenance_status($recommendedDate);
    if ($urgent || in_array($maintenanceStatus['key'], ['recommended', 'due'], true)) {
      $maintenanceRecommendations[] = $customerUnit + ['recommended_date' => $recommendedDate, 'status' => $urgent ? 'Maintenance Recommended' : $maintenanceStatus['label']];
    }
  }
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notify_customer'])) {
    $postedToken = (string)($_POST['csrf_token'] ?? '');
    $unitCode = trim((string)($_POST['unit_code'] ?? ''));
    $recommendation = null;
    foreach ($maintenanceRecommendations as $item) if ($item['unit_code'] === $unitCode) { $recommendation = $item; break; }
    if (!hash_equals($_SESSION['cs_client_notification_csrf'], $postedToken) || !$recommendation) {
      $_SESSION['cs_client_notification_flash'] = ['type' => 'error', 'text' => 'This recommendation could not be confirmed. Refresh and try again.'];
    } else {
      $firstName = explode(' ', trim($recommendation['customer_name']))[0] ?: 'there';
      $dateText = date('F j, Y', strtotime($recommendation['recommended_date']));
      $emailMessage = "Hello {$firstName}, based on previous maintenance records, maintenance for {$unitCode} is recommended. Estimated date: {$dateText}. We will contact you to arrange a suitable visit.";
      $emailSent = cs_send_client_maintenance_email($recommendation['customer_email'], $recommendation['customer_name'], $unitCode, $recommendation['recommended_date']);
      cs_record_client_notification($clientOrgId, (int)$recommendation['consumer_user_id'], $unitCode, 'email', $emailMessage, $emailSent ? 'sent' : 'failed');
      $smsMessage = "ClimaSense: maintenance for {$unitCode} is recommended around {$dateText}. We will contact you to arrange a suitable visit.";
      cs_record_client_notification($clientOrgId, (int)$recommendation['consumer_user_id'], $unitCode, 'sms', $smsMessage, 'not_configured');
      $_SESSION['cs_client_notification_flash'] = ['type' => 'success', 'text' => $emailSent ? 'Email sent. SMS could not be sent because text messaging is not connected.' : 'Notification attempts were saved. Email could not be sent; check the email setup. SMS is not connected.'];
    }
    header('Location: client-dashboard.php');
    exit;
  }
  cs_ensure_client_notification_schema();
  $historyQuery = cs_db()->prepare('SELECT n.unit_code,n.channel,n.message,n.sent_at,n.status,u.name AS customer_name FROM client_customer_notifications n JOIN users u ON u.id=n.customer_id WHERE n.organization_id=? ORDER BY n.sent_at DESC,n.id DESC LIMIT 20');
  $historyQuery->execute([$clientOrgId]);
  $clientNotificationHistory = $historyQuery->fetchAll();
} else {
  $clientNotificationHistory = [];
}
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <div>
    <span class="eyebrow">Overview</span>
    <h1>Air-conditioning dashboard</h1>
    <p>See what needs attention and open a unit for its full details.</p>
  </div>
  <button class="btn btn-primary" data-modal-open="#modalMaint">Log maintenance</button>
</div>

<?php if ($clientOrgId > 0): ?>
  <?php if ($notificationFlash): ?><div class="panel"><div class="panel-body" role="status"><?= htmlspecialchars($notificationFlash['text']) ?></div></div><?php endif; ?>
  <div class="panel">
    <div class="panel-head"><div><h3>Maintenance recommendations</h3><div class="sub">Let customers know when their AC is approaching its recommended maintenance date.</div></div></div>
    <div class="panel-body">
      <?php if (!$maintenanceRecommendations): ?><p class="u-text-slate">There are no customer maintenance recommendations right now.</p>
      <?php else: foreach ($maintenanceRecommendations as $recommendation): ?>
        <div class="upcoming-row">
          <div><div class="u-fw-600 u-text-ink"><?= htmlspecialchars($recommendation['unit_code']) ?> · <?= htmlspecialchars($recommendation['customer_name']) ?></div><div class="u-text-slate u-mt-1"><?= htmlspecialchars($recommendation['status']) ?> · Estimated date: <?= htmlspecialchars(date('F j, Y', strtotime($recommendation['recommended_date']))) ?></div></div>
          <div class="u-row u-gap-2"><a class="btn btn-ghost btn-sm" href="maintenance.php?unit=<?= urlencode($recommendation['unit_code']) ?>&amp;status=scheduled&amp;date=<?= urlencode(max($recommendation['recommended_date'], date('Y-m-d'))) ?>">Schedule Visit</a><form method="post" action="client-dashboard.php"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['cs_client_notification_csrf']) ?>"><input type="hidden" name="unit_code" value="<?= htmlspecialchars($recommendation['unit_code']) ?>"><button class="btn btn-primary btn-sm" type="submit" name="notify_customer" value="1">Notify Customer</button></form></div>
        </div>
      <?php endforeach; endif; ?>
      <?php if ($maintenanceRecommendations): ?><p class="u-text-slate u-mt-4">Contact the customer to agree on a visit time, then record the appointment here. After the visit, mark it completed and save the service details; the next estimate will update from the new history.</p><?php endif; ?>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head"><div><h3>Notification history</h3><div class="sub">Recent email and text message attempts for your customers.</div></div></div>
    <div class="panel-body">
      <?php if (!$clientNotificationHistory): ?><p class="u-text-slate">No customer notifications have been sent yet.</p><?php else: ?><div class="table-wrap"><table><thead><tr><th>AC Unit</th><th>Customer</th><th>Type</th><th>Date</th><th>Status</th></tr></thead><tbody><?php foreach ($clientNotificationHistory as $notice): ?><tr><td><?= htmlspecialchars($notice['unit_code']) ?></td><td><?= htmlspecialchars($notice['customer_name']) ?></td><td><?= $notice['channel'] === 'email' ? 'Email' : 'SMS' ?></td><td><?= htmlspecialchars(date('M j, Y g:i A', strtotime($notice['sent_at']))) ?></td><td><?= $notice['status'] === 'sent' ? 'Sent' : ($notice['status'] === 'failed' ? 'Could not send' : 'Not connected') ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<div class="kpi-row">
  <div class="kpi-card">
    <span class="kpi-label">Total units</span>
    <div class="kpi-value"><?= $fleet['total'] ?> <small>units</small></div>
    <div class="kpi-delta up"><?= $fleet['healthy'] ?> operating normally</div>
  </div>
  <div class="kpi-card">
    <span class="kpi-label">Needs attention</span>
    <div class="kpi-value"><?= $fleet['warning'] + $fleet['critical'] ?> <small>units</small></div>
    <div class="kpi-delta <?= $fleet['critical'] ? 'down' : 'up' ?>"><?= $fleet['critical'] ?> critical, <?= $fleet['warning'] ?> at risk</div>
  </div>
  <div class="kpi-card">
    <span class="kpi-label">Fleet health</span>
    <div class="kpi-value"><?= $fleet['avg_health'] ?><small>%</small></div>
    <div class="kpi-delta up">Based on current unit readings</div>
  </div>
  <div class="kpi-card">
    <span class="kpi-label">Service due soon</span>
    <div class="kpi-value"><?= $dueSoon ?> <small>units</small></div>
    <div class="kpi-delta <?= $dueSoon ? 'down' : 'up' ?>">Within the next 30 days</div>
  </div>
</div>

<div class="grid-2">
  <div>
    <div class="panel-head panel-head-flush">
      <div><h3>Units to check</h3><div class="sub">Showing the units that need the most attention first.</div></div>
      <a href="units.php" class="btn btn-ghost btn-sm">View all units</a>
    </div>
    <div class="unit-grid">
      <?php foreach ($displayUnits as $unit): $meta = cs_status_meta($unit['status']); ?>
        <a class="unit-card" href="unit-detail.php?id=<?= urlencode($unit['id']) ?>">
          <div class="unit-card-top">
            <div>
              <div class="unit-card-id"><?= htmlspecialchars($unit['id']) ?></div>
              <div class="unit-card-name"><?= htmlspecialchars($unit['name']) ?></div>
              <div class="unit-card-loc"><?= htmlspecialchars($unit['location']) ?></div>
            </div>
            <span class="status-chip <?= $meta['class'] ?>"><?= $meta['label'] ?></span>
          </div>
          <div class="unit-card-mid">
            <?= cs_gauge($unit['health'], $unit['status'], 72) ?>
            <div class="unit-card-metrics">
              <div class="metric">Temperature <b><?= $unit['temp'] ?>°C</b></div>
              <div class="metric">Daily use <b><?= number_format($unit['operating_hours_per_day'], 1) ?> h</b></div>
              <div class="metric">Energy use <b><?= number_format($unit['energy_consumption_kwh'], 2) ?> kWh</b></div>
              <div class="metric">RUL <b><?= $unit['rul_days'] ?> days</b></div>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <div>
    <div class="panel">
      <div class="panel-head"><div><h3>Predicted risks</h3><div class="sub">Highest-risk units in the current forecast.</div></div><a href="alerts.php?forecast_year=2026" class="btn btn-ghost btn-sm">All alerts</a></div>
      <div class="panel-body">
        <?php if (!$alerts): ?><p class="u-text-slate">No active alerts.</p><?php endif; ?>
        <?php foreach ($alerts as $alert): ?>
          <div class="alert-item">
            <span class="alert-dot <?= htmlspecialchars($alert['severity']) ?>"></span>
            <div class="alert-body">
              <div class="alert-top"><strong><?= htmlspecialchars($alert['name']) ?></strong><span class="alert-time"><?= htmlspecialchars($alert['time']) ?></span></div>
              <p class="alert-msg"><?= htmlspecialchars($alert['message']) ?></p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="panel">
      <div class="panel-head"><div><h3>Fleet health trend</h3><div class="sub">Illustrative 7-day view based on current demo readings.</div></div><span class="demo-tag">DEMO TREND</span></div>
      <div class="panel-body">
        <div class="trend-summary"><strong><?= $fleet['avg_health'] ?>%</strong><span>current average fleet health</span><span class="trend-flat">Latest</span></div>
        <div class="chart-box sm"><canvas id="dashboardHealthTrend"></canvas></div>
        <p class="chart-note">Historical snapshots are not stored yet; this trend is illustrative, not measured history.</p>
      </div>
    </div>

    <div class="panel">
      <div class="panel-head"><div><h3>Service due</h3><div class="sub">Select a window to see the units in it.</div></div><a href="analytics.php" class="btn btn-ghost btn-sm">Details</a></div>
      <div class="panel-body">
        <div class="service-window-grid" role="group" aria-label="Filter units by estimated service window">
          <?php foreach ($serviceBuckets as $i => $bucket): ?>
            <button type="button" class="service-window <?= $bucket['class'] ?><?= $i === 0 ? ' selected' : '' ?>" data-service-window="<?= $i ?>" aria-pressed="<?= $i === 0 ? 'true' : 'false' ?>">
              <span><?= htmlspecialchars($bucket['label']) ?></span><strong><?= $serviceBucketCounts[$i] ?></strong><small>units</small>
            </button>
          <?php endforeach; ?>
        </div>
        <div class="service-unit-list" id="serviceUnitList" aria-live="polite"></div>
      </div>
    </div>

    <div class="panel">
      <div class="panel-head"><div><h3>Next maintenance</h3><div class="sub">Scheduled work coming up.</div></div><a href="maintenance.php" class="btn btn-ghost btn-sm">Schedule</a></div>
      <div class="panel-body u-col u-gap-4">
        <?php foreach ($upcoming as $item): ?>
          <div class="upcoming-row">
            <div><div class="u-fw-600 u-text-ink"><?= htmlspecialchars($item['task']) ?></div><div class="u-text-slate u-mt-1"><?= htmlspecialchars($item['unit']) ?> · <?= date('M j', strtotime($item['date'])) ?></div></div>
            <span class="mstatus <?= htmlspecialchars($item['status']) ?>"><?= ucfirst(htmlspecialchars($item['status'])) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<div class="modal-backdrop" id="modalMaint">
  <div class="modal">
    <div class="modal-head"><h3>Log maintenance</h3><button class="modal-close" data-modal-close aria-label="Close">×</button></div>
    <form id="maintForm">
      <div class="field"><label for="fUnit">AC unit</label><select id="fUnit" class="input" required><?php foreach ($units as $unit): ?><option value="<?= htmlspecialchars($unit['id']) ?>"><?= htmlspecialchars($unit['id'].' — '.$unit['name']) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="fTask">Task</label><input id="fTask" class="input" type="text" placeholder="e.g. Clean air filter" required></div>
      <div class="field"><label for="fDate">Date</label><input id="fDate" class="input" type="date" required></div>
      <div class="modal-actions"><button type="button" class="btn btn-ghost" data-modal-close>Cancel</button><button type="submit" class="btn btn-primary">Save task</button></div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
<script>
  csLineChart('dashboardHealthTrend', ['7 days ago', '6 days', '5 days', '4 days', '3 days', 'Yesterday', 'Today'], <?= json_encode($healthTrend) ?>, { label: 'Illustrative fleet health', min: 0, max: 100, maxTicks: 5 });
  const serviceUnits = <?= json_encode(array_map(fn($unit) => ['id' => $unit['id'], 'name' => $unit['name'], 'rul' => $unit['rul_days']], $units), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
  const serviceRanges = <?= json_encode(array_map(fn($bucket) => [$bucket['min'], $bucket['max']], $serviceBuckets)) ?>;
  const serviceList = document.getElementById('serviceUnitList');
  function showServiceWindow(index) {
    const [min, max] = serviceRanges[index];
    const matches = serviceUnits.filter(unit => unit.rul >= min && unit.rul <= max).sort((a, b) => a.rul - b.rul);
    serviceList.replaceChildren();
    if (!matches.length) {
      const empty = document.createElement('p'); empty.className = 'service-empty'; empty.textContent = 'No units in this window.'; serviceList.append(empty); return;
    }
    matches.forEach(unit => {
      const link = document.createElement('a'); link.className = 'service-unit-row'; link.href = `unit-detail.php?id=${encodeURIComponent(unit.id)}`;
      const label = document.createElement('span');
      const id = document.createElement('strong'); id.textContent = unit.id;
      label.append(id, document.createTextNode(` ${unit.name}`));
      const rul = document.createElement('b'); rul.textContent = `${unit.rul} days`;
      link.append(label, rul); serviceList.append(link);
    });
  }
  document.querySelectorAll('[data-service-window]').forEach(button => button.addEventListener('click', () => {
    document.querySelectorAll('[data-service-window]').forEach(item => { item.classList.remove('selected'); item.setAttribute('aria-pressed', 'false'); });
    button.classList.add('selected'); button.setAttribute('aria-pressed', 'true'); showServiceWindow(Number(button.dataset.serviceWindow));
  }));
  showServiceWindow(0);
</script>
<?php require __DIR__ . '/includes/footer-end.php'; ?>
