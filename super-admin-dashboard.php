<?php
require_once __DIR__ . '/includes/auth.php';
cs_require_role('super_admin');
$pageTitle = 'Super Admin Dashboard';
$activeNav = 'dashboard';
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/helpers.php';
$u = cs_current_user();
$acUnits = cs_units();
$dashboardYear = filter_input(INPUT_GET, 'forecast_year', FILTER_VALIDATE_INT, ['options' => ['default' => 2026, 'min_range' => 2022, 'max_range' => 2036]]) ?: 2026;
$dashboardYearOffset = $dashboardYear - 2026;
foreach ($acUnits as &$unit) {
    $annualWear = 0.9
        + max(0, $unit['operating_hours_per_day'] - 8) * 0.22
        + abs($unit['temp'] - 25) * 0.06
        + $unit['energy_consumption_kwh'] * 0.10
        + (100 - $unit['compression_condition_score']) * 0.018
        + (100 - $unit['filter_condition_score']) * 0.014;
    $unit['health'] = (int) round(max(25, min(99, $unit['health'] - ($dashboardYearOffset * $annualWear))));
    $unit['compression_condition_score'] = round(max(25, min(99, $unit['compression_condition_score'] - ($dashboardYearOffset * $annualWear * 0.55))));
    $unit['filter_condition_score'] = round(max(20, min(99, $unit['filter_condition_score'] - ($dashboardYearOffset * $annualWear * 0.75))));
    $unit['operating_hours_per_day'] = round(max(1, $unit['operating_hours_per_day'] * (1 + ($dashboardYearOffset * 0.004))), 1);
    $unit['energy_consumption_kwh'] = round(max(0.1, $unit['energy_consumption_kwh'] * (1 + ($dashboardYearOffset * 0.012))), 2);
    $unit['rul_days'] = max(7, min(365, (int) round(($unit['health'] / 100) * 180)));
    $unit['status'] = $unit['health'] >= 80 ? 'healthy' : ($unit['health'] >= 60 ? 'warning' : 'critical');
}
unset($unit);
$fleet = [
    'total' => count($acUnits),
    'avg_health' => (int) round(array_sum(array_column($acUnits, 'health')) / max(count($acUnits), 1)),
    'critical' => count(array_filter($acUnits, fn($unit) => $unit['status'] === 'critical')),
    'warning' => count(array_filter($acUnits, fn($unit) => $unit['status'] === 'warning')),
];
$fleet['healthy'] = $fleet['total'] - $fleet['critical'] - $fleet['warning'];
$anomalyUnits = array_values(array_filter($acUnits, fn($unit) =>
    $unit['vibration'] > 0.65 || $unit['current'] > 9 || $unit['temp'] < 18 || $unit['temp'] > 30 || $unit['pressure'] < 90 || $unit['pressure'] > 175
));
$anomalyCount = count($anomalyUnits);
$attentionUnits = array_values(array_filter($acUnits, fn($unit) => $unit['status'] !== 'healthy'));
usort($attentionUnits, fn($a, $b) => $a['health'] <=> $b['health']);
$attentionUnits = array_slice($attentionUnits, 0, 4);
$averageCompression = array_sum(array_column($acUnits, 'compression_condition_score')) / max(count($acUnits), 1);
$averageFilter = array_sum(array_column($acUnits, 'filter_condition_score')) / max(count($acUnits), 1);
$averageDailyHours = array_sum(array_column($acUnits, 'operating_hours_per_day')) / max(count($acUnits), 1);
$baseMaintenanceRisk = (100 - $fleet['avg_health']) + ((100 - $averageCompression) * 0.25) + ((100 - $averageFilter) * 0.25) + (max(0, $averageDailyHours - 8) * 1.5);
$riskYears = range(2022, 2036);
$riskForecast = [];
foreach ($riskYears as $year) {
    // Annual projection: current condition plus accumulated wear from daily runtime.
    $riskForecast[] = max(5, min(95, round($baseMaintenanceRisk + (($year - 2026) * (2.5 + max(0, $averageDailyHours - 8) * 0.2)), 1)));
}
$energyBands = [
    ['label' => 'Up to 6 h', 'min' => 0, 'max' => 6],
    ['label' => '6–8 h', 'min' => 6, 'max' => 8],
    ['label' => '8–10 h', 'min' => 8, 'max' => 10],
    ['label' => '10–12 h', 'min' => 10, 'max' => 12],
    ['label' => 'Over 12 h', 'min' => 12, 'max' => INF],
];
$energyLabels = [];
$energyValues = [];
foreach ($energyBands as $band) {
    $bandUnits = array_filter($acUnits, fn($unit) => $unit['operating_hours_per_day'] > $band['min'] && $unit['operating_hours_per_day'] <= $band['max']);
    $energyLabels[] = $band['label'];
    $energyValues[] = $bandUnits ? round(array_sum(array_column($bandUnits, 'energy_consumption_kwh')) / count($bandUnits), 2) : 0;
}
$rulUnits = $acUnits;
usort($rulUnits, fn($a, $b) => $a['rul_days'] <=> $b['rul_days']);
$rulLabels = array_map(fn($unit) => $unit['id'], $rulUnits);
$rulValues = array_column($rulUnits, 'rul_days');
$soonestRul = $rulValues[0] ?? 0;
$maintenanceRiskLabels = [];
$maintenanceRiskValues = [];
for ($year = $dashboardYear; $year <= min($dashboardYear + 4, 2036); $year++) {
    $maintenanceRiskLabels[] = (string)$year;
    $maintenanceRiskValues[] = $riskForecast[$year - 2022];
}
$currentMaintenanceRisk = $riskForecast[$dashboardYear - 2022];
$serviceBuckets = [
    ['label' => 'Due in 30 days', 'min' => 0, 'max' => 30, 'class' => 'crit'],
    ['label' => '31–60 days', 'min' => 31, 'max' => 60, 'class' => 'warn'],
    ['label' => '61–90 days', 'min' => 61, 'max' => 90, 'class' => 'amber'],
    ['label' => '90+ days', 'min' => 91, 'max' => PHP_INT_MAX, 'class' => 'ok'],
];
$serviceBucketCounts = [];
foreach ($serviceBuckets as $bucket) {
    $serviceBucketCounts[] = count(array_filter($acUnits, fn($unit) => $unit['rul_days'] >= $bucket['min'] && $unit['rul_days'] <= $bucket['max']));
}
$stats = [
    'total_users' => 0,
    'admins' => 0,
    'consumers' => 0,
    'active_units' => $fleet['total'],
    'alerts' => $fleet['warning'] + $fleet['critical'],
    'fleet_health' => $fleet['avg_health'],
];
$db = cs_db();
$stats['total_users'] = (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
$stats['admins'] = (int)$db->query("SELECT COUNT(*) FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE r.code IN ('super_admin','admin_level_1','admin_level_2','client_admin')")->fetchColumn();
$stats['consumers'] = (int)$db->query("SELECT COUNT(*) FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE r.code = 'consumer'")->fetchColumn();
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div>
    <span class="eyebrow">System Control Center</span>
    <h1>Welcome back, <?= htmlspecialchars($u['name']) ?>.</h1>
    <p>Viewing the projected AC fleet condition for <?= $dashboardYear ?>.</p>
  </div>
  <div class="page-actions">
    <label class="u-text-sm u-text-slate" for="dashboardYear">Dashboard year</label>
    <select class="input input-sm" id="dashboardYear" aria-label="Select dashboard year">
      <?php foreach (range(2022, 2036) as $year): ?><option value="<?= $year ?>" <?= $year === $dashboardYear ? 'selected' : '' ?>><?= $year ?></option><?php endforeach; ?>
    </select>
    <button class="btn btn-ghost" type="button" onclick="window.location.href='settings.php'">System Settings</button>
    <button class="btn btn-primary" type="button" onclick="csToast && csToast('System-wide sync started.', 'ok');">Run Sync</button>
  </div>
</div>

<div class="kpi-row">
  <div class="kpi-card">
    <span class="kpi-icon"><svg width="17" height="17" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" stroke="currentColor" stroke-width="2" fill="none"/><circle cx="10" cy="7" r="4" stroke="currentColor" stroke-width="2" fill="none"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" stroke="currentColor" stroke-width="2" fill="none" /></svg></span>
    <span class="kpi-label">Total Users</span>
    <div class="kpi-value"><?= $stats['total_users'] ?> <small>accounts</small></div>
    <div class="kpi-delta up">Cross-role coverage</div>
  </div>
  <div class="kpi-card">
    <span class="kpi-icon"><svg width="17" height="17" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="10" rx="2" stroke="currentColor" stroke-width="2" fill="none"/><path d="M7 19h10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span>
    <span class="kpi-label">Monitored Units</span>
    <div class="kpi-value"><?= $stats['active_units'] ?> <small>units</small></div>
    <div class="kpi-delta up"><?= $fleet['healthy'] ?> operating normally in <?= $dashboardYear ?></div>
  </div>
  <div class="kpi-card">
    <span class="kpi-icon" style="color:var(--amber);background:var(--amber-bg)"><svg width="17" height="17" viewBox="0 0 24 24"><path d="M12 3 2 20h20L12 3Z" stroke="currentColor" stroke-width="2" fill="none" stroke-linejoin="round"/><path d="M12 10v4M12 17h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span>
    <span class="kpi-label">Units Needing Attention</span>
    <div class="kpi-value"><?= $stats['alerts'] ?> <small>items</small></div>
    <div class="kpi-delta down"><?= $fleet['critical'] ?> critical, <?= $fleet['warning'] ?> at risk in <?= $dashboardYear ?></div>
  </div>
  <div class="kpi-card">
    <span class="kpi-icon" style="color:var(--forest);background:var(--mint-50)"><svg width="17" height="17" viewBox="0 0 24 24"><path d="M12 2v20M2 12h20" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="12" cy="12" r="8" stroke="currentColor" stroke-width="2" fill="none"/></svg></span>
    <span class="kpi-label">Fleet Health</span>
    <div class="kpi-value"><?= $stats['fleet_health'] ?>% <small>average</small></div>
    <div class="kpi-delta up">Projected for <?= $dashboardYear ?></div>
  </div>
</div>

<div class="grid-2">
  <div>
  <div class="panel">
    <div class="panel-head"><div><h3>Maintenance/Failure Risk</h3><div class="sub">Estimated probability of fleet maintenance need over the next five years.</div></div><span class="demo-tag">MODEL FORECAST</span></div>
    <div class="panel-body"><div class="trend-summary"><strong><?= $currentMaintenanceRisk ?>%</strong><span>estimated risk in <?= $dashboardYear ?></span><span class="trend-flat">Current</span></div><div class="chart-box sm"><canvas id="superAdminMaintenanceRisk"></canvas></div><p class="chart-note">Estimate is based on fleet health, component condition, and operating hours.</p></div>
  </div>

  <div class="panel">
    <div class="panel-head">
      <div>
        <h3>Anomaly Detection</h3>
        <div class="sub">Detects unusual behavior compared with normal AC operation.</div>
      </div>
    </div>
    <div class="panel-body">
      <div class="trend-summary"><strong style="color:<?= $anomalyCount > 0 ? 'var(--amber)' : 'var(--forest)' ?>"><?= $anomalyCount > 0 ? '⚠ Abnormal' : 'Normal' ?></strong><span><?= $anomalyCount ?> of <?= $fleet['total'] ?> units flagged from current sensor readings</span><span class="status-chip <?= $anomalyCount > 0 ? 'warn' : 'ok' ?>"><?= $anomalyCount > 0 ? 'Review' : 'Clear' ?></span></div>
      <p class="chart-note">Flags vibration above 0.65, current above 9 A, temperature outside 18–30 °C, or pressure outside 90–175.</p>
      <?php if ($anomalyCount > 0): ?><div class="service-unit-list"><?php foreach (array_slice($anomalyUnits, 0, 3) as $unit): ?><a class="service-unit-row" href="unit-detail.php?id=<?= urlencode($unit['id']) ?>"><span><strong><?= htmlspecialchars($unit['id']) ?></strong> <?= htmlspecialchars($unit['name']) ?></span><b>Review readings</b></a><?php endforeach; ?></div><?php endif; ?>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head"><div><h3>Energy Consumption Trend</h3><div class="sub">Average kWh as daily operating hours increase in <?= $dashboardYear ?></div></div><a href="analytics.php?forecast_year=<?= $dashboardYear ?>" class="btn btn-ghost btn-sm">Details</a></div>
    <div class="panel-body"><div class="chart-box sm"><canvas id="superAdminEnergyChart"></canvas></div></div>
  </div>

  </div>

  <div>
  <div class="panel">
    <div class="panel-head">
      <div>
        <h3>Priority AC Units</h3>
        <div class="sub">Lowest projected health scores for <?= $dashboardYear ?></div>
      </div>
    </div>
    <div class="panel-body u-col u-gap-3">
      <?php foreach ($attentionUnits as $unit): ?>
        <a class="upcoming-row" href="unit-detail.php?id=<?= urlencode($unit['id']) ?>">
          <div><div class="u-fw-600 u-text-ink"><?= htmlspecialchars($unit['id']) ?> · <?= htmlspecialchars($unit['name']) ?></div><div class="u-text-slate u-mt-1">Health <?= $unit['health'] ?>% · <?= $unit['rul_days'] ?> days remaining</div></div>
          <span class="status-chip <?= cs_status_meta($unit['status'])['class'] ?>"><?= cs_status_meta($unit['status'])['label'] ?></span>
        </a>
      <?php endforeach; ?>
      <a class="btn btn-ghost btn-block" href="units.php">View all AC units</a>
    </div>
  </div>
  <div class="panel">
    <div class="panel-head"><div><h3>Remaining Useful Life (RUL)</h3><div class="sub">Estimated time before each AC reaches its maintenance threshold in <?= $dashboardYear ?>.</div></div><a href="units.php" class="btn btn-ghost btn-sm">Details</a></div>
    <div class="panel-body"><div class="trend-summary"><strong><?= $soonestRul ?> days</strong><span>until the soonest estimated maintenance threshold</span><span class="trend-flat">Soonest</span></div><div class="chart-box usage-chart-box"><canvas id="superAdminRulChart"></canvas></div><p class="chart-note">Days remaining, estimated from projected unit health.</p></div>
  </div>
  <div class="panel">
    <div class="panel-head"><div><h3>Service Due</h3><div class="sub">Select a window to see matching units for <?= $dashboardYear ?>.</div></div></div>
    <div class="panel-body">
      <div class="service-window-grid" role="group" aria-label="Filter units by estimated service window">
        <?php foreach ($serviceBuckets as $i => $bucket): ?>
          <button type="button" class="service-window <?= $bucket['class'] ?><?= $i === 0 ? ' selected' : '' ?>" data-admin-service-window="<?= $i ?>" aria-pressed="<?= $i === 0 ? 'true' : 'false' ?>">
            <span><?= htmlspecialchars($bucket['label']) ?></span><strong><?= $serviceBucketCounts[$i] ?></strong><small>units</small>
          </button>
        <?php endforeach; ?>
      </div>
      <div class="service-unit-list" id="adminServiceUnitList" aria-live="polite"></div>
    </div>
  </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
<script>
  (function () {
    const canvas = document.getElementById('superAdminRulChart');
    if (!canvas || !window.Chart) return;
    const rulLabels = <?= json_encode($rulLabels) ?>;
    const rulValues = <?= json_encode($rulValues) ?>;
    new Chart(canvas, {
      type: 'bar',
      data: {
        labels: rulLabels,
        datasets: [{ label: 'Days remaining', data: rulValues, backgroundColor: '#2ea06b', borderRadius: 5, maxBarThickness: 20 }]
      },
      options: {
        indexAxis: 'y', responsive: true, maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: ctx => `${ctx.parsed.x} days remaining` } }
        },
        scales: {
          x: { beginAtZero: true, max: 365, title: { display: true, text: 'Days remaining' }, ticks: { maxTicksLimit: 5 } },
          y: { title: { display: true, text: 'AC unit' } }
        }
      }
    });
  })();
  document.getElementById('dashboardYear')?.addEventListener('change', function () {
    const url = new URL(window.location.href);
    url.searchParams.set('forecast_year', this.value);
    window.location.assign(url.toString());
  });
  csLineChart('superAdminEnergyChart', <?= json_encode($energyLabels) ?>, <?= json_encode($energyValues) ?>, { label: 'Average energy use (kWh)', min: 0 });
  csLineChart('superAdminMaintenanceRisk', <?= json_encode($maintenanceRiskLabels) ?>, <?= json_encode($maintenanceRiskValues) ?>, { label: 'Maintenance/failure risk', min: 0, max: 100, maxTicks: 5 });
  const adminServiceUnits = <?= json_encode(array_map(fn($unit) => ['id' => $unit['id'], 'name' => $unit['name'], 'rul' => $unit['rul_days']], $acUnits), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
  const adminServiceRanges = <?= json_encode(array_map(fn($bucket) => [$bucket['min'], $bucket['max']], $serviceBuckets)) ?>;
  const adminServiceList = document.getElementById('adminServiceUnitList');
  function showAdminServiceWindow(index) {
    const [min, max] = adminServiceRanges[index];
    const matches = adminServiceUnits.filter(unit => unit.rul >= min && unit.rul <= max).sort((a, b) => a.rul - b.rul);
    adminServiceList.replaceChildren();
    if (!matches.length) {
      const empty = document.createElement('p'); empty.className = 'service-empty'; empty.textContent = 'No units in this window.'; adminServiceList.append(empty); return;
    }
    matches.slice(0, 8).forEach(unit => {
      const link = document.createElement('a'); link.className = 'service-unit-row'; link.href = `unit-detail.php?id=${encodeURIComponent(unit.id)}`;
      const label = document.createElement('span');
      const id = document.createElement('strong'); id.textContent = unit.id;
      label.append(id, document.createTextNode(` ${unit.name}`));
      const rul = document.createElement('b'); rul.textContent = `${unit.rul} days`;
      link.append(label, rul); adminServiceList.append(link);
    });
    if (matches.length > 8) { const more = document.createElement('p'); more.className = 'chart-note'; more.textContent = `Showing 8 of ${matches.length} units.`; adminServiceList.append(more); }
  }
  document.querySelectorAll('[data-admin-service-window]').forEach(button => button.addEventListener('click', () => {
    document.querySelectorAll('[data-admin-service-window]').forEach(item => { item.classList.remove('selected'); item.setAttribute('aria-pressed', 'false'); });
    button.classList.add('selected'); button.setAttribute('aria-pressed', 'true'); showAdminServiceWindow(Number(button.dataset.adminServiceWindow));
  }));
  showAdminServiceWindow(0);
</script>
<?php require __DIR__ . '/includes/footer-end.php'; ?>
