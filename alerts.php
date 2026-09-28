<?php
$pageTitle = 'Alerts';
$activeNav = 'alerts';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/helpers.php';
$requireOrgId = cs_current_user()['organization_id'] ?? null;
cs_require_permission('alerts.view', $requireOrgId);

$forecastYear = filter_input(INPUT_GET, 'forecast_year', FILTER_VALIDATE_INT, ['options' => ['default' => 2026, 'min_range' => 2022, 'max_range' => 2036]]) ?: 2026;
$projectedUnits = cs_units_for_forecast_year($forecastYear);
$alerts = cs_predictive_alerts($forecastYear, 12);
$counts = [
  'critical' => count(array_filter($projectedUnits, fn($unit) => $unit['status'] === 'critical')),
  'warning' => count(array_filter($projectedUnits, fn($unit) => $unit['status'] === 'warning')),
  'healthy' => count(array_filter($projectedUnits, fn($unit) => $unit['status'] === 'healthy')),
];
$averageRul = (int)round(array_sum(array_column($projectedUnits, 'rul_days')) / max(count($projectedUnits), 1));
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div>
    <span class="eyebrow">Notification Center</span>
    <h1>Alerts</h1>
    <p>Top predictive health risks from the fleet dataset for <?= $forecastYear ?>, using the same unit projections as Predictive Analytics.</p>
  </div>
  <div class="page-actions">
    <label class="u-text-sm u-text-slate" for="alertsForecastYear">Forecast year</label>
    <select class="input input-sm" id="alertsForecastYear" aria-label="Select forecast year">
      <?php foreach (range(2022, 2036) as $year): ?><option value="<?= $year ?>" <?= $year === $forecastYear ? 'selected' : '' ?>><?= $year ?></option><?php endforeach; ?>
    </select>
    <a class="btn btn-ghost btn-sm" href="analytics.php?forecast_year=<?= $forecastYear ?>">Predictive Analytics</a>
  </div>
</div>

<div class="kpi-row">
  <div class="kpi-card">
    <span class="kpi-icon" style="color:var(--rust);background:var(--rust-bg)"><svg width="17" height="17" viewBox="0 0 24 24"><path d="M12 8v5M12 16h.01" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2" fill="none"/></svg></span>
    <span class="kpi-label">Critical</span>
    <div class="kpi-value"><?= number_format($counts['critical']) ?> <small>units</small></div>
    <div class="kpi-delta <?= $counts['critical'] ? 'down' : 'up' ?>"><?= $counts['critical'] ? 'Critical risk projected for '.$forecastYear : 'No critical units projected' ?></div>
  </div>
  <div class="kpi-card">
    <span class="kpi-icon" style="color:var(--amber);background:var(--amber-bg)"><svg width="17" height="17" viewBox="0 0 24 24"><path d="M12 3 2 20h20L12 3Z" stroke="currentColor" stroke-width="2" fill="none" stroke-linejoin="round"/><path d="M12 10v4M12 17h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span>
    <span class="kpi-label">Warning</span>
    <div class="kpi-value"><?= number_format($counts['warning']) ?> <small>units</small></div>
    <div class="kpi-delta <?= $counts['warning'] ? 'down' : 'up' ?>"><?= $counts['warning'] ? 'At-risk units projected for '.$forecastYear : 'No at-risk units projected' ?></div>
  </div>
  <div class="kpi-card">
    <span class="kpi-icon"><svg width="17" height="17" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2" fill="none"/><path d="M12 8v.01M12 11v5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span>
    <span class="kpi-label">Healthy units</span>
    <div class="kpi-value"><?= number_format($counts['healthy']) ?> <small>units</small></div>
    <div class="kpi-delta up">Projected healthy for <?= $forecastYear ?></div>
  </div>
  <div class="kpi-card">
    <span class="kpi-label">Avg. Remaining Life</span>
    <div class="kpi-value"><?= $averageRul ?> <small>days</small></div>
    <div class="kpi-delta up">Across <?= number_format(count($projectedUnits)) ?> projected units</div>
  </div>
</div>

<div class="filter-bar">
  <select data-filter-select="severity">
    <option value="">All severities</option>
    <option value="critical">Critical</option>
    <option value="warning">Warning</option>
  </select>
</div>

<div class="panel">
  <div class="panel-body">
    <?php if (!$alerts): ?><p class="u-text-slate">No units are forecast at risk for <?= $forecastYear ?>.</p><?php endif; ?>
    <?php foreach ($alerts as $a): ?>
      <div class="alert-item" data-searchable="<?= htmlspecialchars($a['unit'].' '.$a['name'].' '.$a['message']) ?>" data-severity="<?= $a['severity'] ?>">
        <span class="alert-dot <?= $a['severity'] ?>"></span>
        <div class="alert-body">
          <div class="alert-top">
            <strong><?= htmlspecialchars($a['name']) ?> <span class="mono u-text-slate u-fw-500">· <?= $a['unit'] ?></span></strong>
            <span class="alert-time"><?= $a['time'] ?></span>
          </div>
          <p class="alert-msg"><?= htmlspecialchars($a['message']) ?></p>
          <div class="alert-tags u-row u-gap-2">
            <span class="tag"><?= htmlspecialchars($a['type']) ?></span>
            <a href="unit-detail.php?id=<?= urlencode($a['unit']) ?>" class="tag tag-ok">View Unit</a>
            <?php if ($a['severity'] !== 'info'): ?>
              <button class="btn btn-ghost btn-sm u-ml-auto" data-ack-alert>Acknowledge</button>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
<script>
  document.getElementById('alertsForecastYear')?.addEventListener('change', function () {
    const url = new URL(window.location.href);
    url.searchParams.set('forecast_year', this.value);
    window.location.assign(url.toString());
  });
</script>
<?php require __DIR__ . '/includes/footer-end.php'; ?>
