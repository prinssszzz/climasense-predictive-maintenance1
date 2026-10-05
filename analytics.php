<?php
$pageTitle = 'Predictive Analytics';
$activeNav = 'analytics';
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
$requireOrgId = null;
$requireOrgId = cs_current_user()['organization_id'] ?? null;
cs_require_permission('analytics.view', $requireOrgId);
$inspectionMode = ($_GET['inspection'] ?? '') === '1';
$prefillUnit = trim((string)($_GET['unit'] ?? ''));
$prefillUnitData = $inspectionMode ? cs_unit($prefillUnit) : null;
$inspectionMode = $inspectionMode && $prefillUnitData !== null;
$prefillTask = trim((string)($_GET['task'] ?? 'Inspection report'));
$riskValue = filter_input(INPUT_GET, 'risk', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100]]);
if ($riskValue === false || $riskValue === null) $riskValue = $prefillUnitData ? max(5, min(95, 100 - (int)$prefillUnitData['health'])) : 0;
$prefillPriority = (string)($_GET['priority'] ?? (($riskValue >= 70) ? 'high' : 'normal'));
if (!in_array($prefillPriority, ['urgent', 'high', 'normal', 'low'], true)) $prefillPriority = 'normal';
if ($inspectionMode) {
  cs_require_permission('maintenance.manage', $requireOrgId);
  if (empty($_SESSION['csrf_maintenance'])) $_SESSION['csrf_maintenance'] = bin2hex(random_bytes(32));
}
$inspectionFlash = $_SESSION['maintenance_flash'] ?? null;
unset($_SESSION['maintenance_flash']);

$forecastYear = filter_input(INPUT_GET, 'forecast_year', FILTER_VALIDATE_INT, ['options' => ['default' => 2026, 'min_range' => 2022, 'max_range' => 2036]]) ?: 2026;
$units = cs_units_for_forecast_year($forecastYear);
$maintenanceLog = cs_maintenance_log();
usort($units, fn($a, $b) => $a['rul_days'] <=> $b['rul_days']);
$fleet = [
  'total' => count($units),
  'avg_health' => (int) round(array_sum(array_column($units, 'health')) / max(count($units), 1)),
  'critical' => count(array_filter($units, fn($unit) => $unit['status'] === 'critical')),
  'warning' => count(array_filter($units, fn($unit) => $unit['status'] === 'warning')),
];
$fleet['healthy'] = $fleet['total'] - $fleet['critical'] - $fleet['warning'];
$today = date('Y-m-d');
$previousYearUnits = cs_units_for_forecast_year($forecastYear - 1);
$previousYearHealth = (int) round(array_sum(array_column($previousYearUnits, 'health')) / max(count($previousYearUnits), 1));
$annualHealthChange = $fleet['avg_health'] - $previousYearHealth;
$scheduledUnits = count(array_unique(array_column(array_filter($maintenanceLog, fn($item) =>
  $item['status'] === 'scheduled' && !empty($item['tech']) && $item['tech'] !== 'Unassigned' && $item['date'] >= $today
), 'unit')));
$workloadWindows = [
  'week' => count(array_filter($units, fn($unit) => $unit['status'] === 'critical' || $unit['rul_days'] <= 7)),
  'month' => count(array_filter($units, fn($unit) => $unit['status'] !== 'critical' && $unit['rul_days'] > 7 && $unit['rul_days'] <= 30)),
  'quarter' => count(array_filter($units, fn($unit) => $unit['status'] !== 'critical' && $unit['rul_days'] > 30 && $unit['rul_days'] <= 90)),
  'later' => count(array_filter($units, fn($unit) => $unit['status'] !== 'critical' && $unit['rul_days'] > 90)),
];
$estimatedServiceHours = ($workloadWindows['week'] + $workloadWindows['month']) * 2;
$priorityUnits = array_slice($units, 0, 8);
$rulLabels = array_column($priorityUnits, 'id');
$rulValues = array_column($priorityUnits, 'rul_days');
$rulColors = array_map(fn($u) => $u['status'] === 'critical' ? '#b4432d' : ($u['status'] === 'warning' ? '#c67c2e' : '#1b6b4c'), $priorityUnits);

function cs_analytics_average(array $units, string $field, int $precision = 1): float {
  $values = array_column($units, $field);
  return round(array_sum($values) / max(count($values), 1), $precision);
}
$averageRul = cs_analytics_average($units, 'rul_days', 0);
$averageCompression = cs_analytics_average($units, 'compression_condition_score');
$averageFilter = cs_analytics_average($units, 'filter_condition_score');
$averageDailyHours = cs_analytics_average($units, 'operating_hours_per_day');
$averageEnergy = cs_analytics_average($units, 'energy_consumption_kwh');
$averageVibration = cs_analytics_average($units, 'vibration');
$averageCooling = round(($averageCompression + $averageFilter) / 2, 1);
$riskForUnit = function (array $unit) use ($averageCompression, $averageFilter, $averageDailyHours, $averageEnergy, $averageVibration): array {
  $energy = (float) ($unit['energy_consumption_kwh'] ?? $averageEnergy);
  $compression = (float) ($unit['compression_condition_score'] ?? $unit['health']);
  $filter = (float) ($unit['filter_condition_score'] ?? $unit['health']);
  $hours = (float) ($unit['operating_hours_per_day'] ?? $averageDailyHours);
  $vibration = (float) ($unit['vibration'] ?? $averageVibration);
  $energyExcess = max(0, (($energy - $averageEnergy) / max($averageEnergy, 0.1)) * 100);
  $compressionGap = max(0, $averageCompression - $compression);
  $filterGap = max(0, $averageFilter - $filter);
  $coolingScore = ($compression + $filter) / 2;
  $fleetCoolingScore = ($averageCompression + $averageFilter) / 2;
  $coolingGap = max(0, $fleetCoolingScore - $coolingScore);
  $vibrationExcess = max(0, (($vibration - $averageVibration) / max($averageVibration, 0.1)) * 100);
  $risk = (int) round(min(99, max(1, (100 - $unit['health']) + $energyExcess * 0.08 + $compressionGap * 0.25 + $filterGap * 0.18 + $coolingGap * 0.2 + max(0, $hours - $averageDailyHours) * 1.5 + $vibrationExcess * 0.06)));
  $reasons = [];
  if ($energyExcess >= 5) $reasons[] = 'Energy ' . (int) round($energyExcess) . '% above fleet average';
  if ($compressionGap >= 3) $reasons[] = 'Compression condition ' . (int) round($compressionGap) . ' pts below average';
  if ($filterGap >= 3) $reasons[] = 'Filter condition ' . (int) round($filterGap) . ' pts below average';
  if ($coolingGap >= 3) $reasons[] = 'Cooling performance ' . (int) round($coolingGap) . ' pts below fleet average';
  if ($vibrationExcess >= 10) $reasons[] = 'Vibration ' . (int) round($vibrationExcess) . '% above average';
  if ($hours > $averageDailyHours + 1) $reasons[] = 'Runtime ' . number_format($hours - $averageDailyHours, 1) . ' h/day above average';
  if (!$reasons) $reasons[] = 'Health score is the main risk signal; other readings are near fleet averages';
  return ['score' => $risk, 'reasons' => array_slice($reasons, 0, 3)];
};
$baseMaintenanceRisk = (100 - $fleet['avg_health']) + ((100 - $averageCooling) * 0.5) + (max(0, $averageDailyHours - 8) * 1.5);
$forecastYears = range(2022, 2036);
$forecastRisk = [];
foreach ($forecastYears as $year) {
  $forecastRisk[] = max(5, min(95, round($baseMaintenanceRisk + (($year - 2026) * (2.5 + max(0, $averageDailyHours - 8) * 0.2)), 1)));
}
$previousRisk = max(5, min(95, round($baseMaintenanceRisk + (($forecastYear - 1 - 2026) * (2.5 + max(0, $averageDailyHours - 8) * 0.2)), 1)));
$currentRisk = max(5, min(95, round($baseMaintenanceRisk + (($forecastYear - 2026) * (2.5 + max(0, $averageDailyHours - 8) * 0.2)), 1)));
$riskChange = round($currentRisk - $previousRisk, 1);
$rulLabels = ['Due within 30 days', 'Due in 31-60 days', 'Due in 61-90 days', 'More than 90 days'];
$rulValues = [
  count(array_filter($units, fn($unit) => $unit['rul_days'] <= 30)),
  count(array_filter($units, fn($unit) => $unit['rul_days'] >= 31 && $unit['rul_days'] <= 60)),
  count(array_filter($units, fn($unit) => $unit['rul_days'] >= 61 && $unit['rul_days'] <= 90)),
  count(array_filter($units, fn($unit) => $unit['rul_days'] > 90)),
];
$rulColors = ['#b4432d', '#c67c2e', '#d8a14a', '#1b6b4c'];

$energyBuckets = [
  ['label' => 'Up to 8 h/day', 'min' => 0, 'max' => 8],
  ['label' => '8-12 h/day', 'min' => 8, 'max' => 12],
  ['label' => 'Over 12 h/day', 'min' => 12, 'max' => INF],
];
$energyLabels = [];
$energyValues = [];
foreach ($energyBuckets as $bucket) {
  $bucketUnits = array_filter($units, fn($unit) => $unit['operating_hours_per_day'] > $bucket['min'] && $unit['operating_hours_per_day'] <= $bucket['max']);
  $energyLabels[] = $bucket['label'];
  $energyValues[] = $bucketUnits ? round(array_sum(array_column($bucketUnits, 'energy_consumption_kwh')) / count($bucketUnits), 2) : 0;
}

$runtimeBands = [
  ['label' => 'Up to 6 h', 'min' => 0, 'max' => 6],
  ['label' => '6 to 8 h', 'min' => 6, 'max' => 8],
  ['label' => '8 to 10 h', 'min' => 8, 'max' => 10],
  ['label' => '10 to 12 h', 'min' => 10, 'max' => 12],
  ['label' => '12 to 14 h', 'min' => 12, 'max' => 14],
  ['label' => 'Over 14 h', 'min' => 14, 'max' => INF],
];
$runtimeLabels = [];
$runtimeHealth = [];
foreach ($runtimeBands as $band) {
  $bandUnits = array_filter($units, fn($unit) => $unit['operating_hours_per_day'] > $band['min'] && $unit['operating_hours_per_day'] <= $band['max']);
  $runtimeLabels[] = $band['label'];
  $runtimeHealth[] = $bandUnits ? cs_analytics_average($bandUnits, 'health', 0) : 0;
}

$modelGroups = [];
foreach ($units as $unit) $modelGroups[$unit['model']][] = $unit;
ksort($modelGroups, SORT_NATURAL | SORT_FLAG_CASE);
$modelHealthRows = [];
foreach ($modelGroups as $model => $modelUnits) {
  $modelHealthRows[] = [
    'model' => $model,
    'compression' => cs_analytics_average($modelUnits, 'compression_condition_score', 0),
    'filter' => cs_analytics_average($modelUnits, 'filter_condition_score', 0),
    'health' => cs_analytics_average($modelUnits, 'health', 0),
    'rul' => cs_analytics_average($modelUnits, 'rul_days', 0),
  ];
}
$modelRulYears = range(2022, 2036);
$modelRulPalette = ['#1b6b4c', '#c67c2e', '#386cb0', '#b4432d', '#8056a8', '#178b91', '#b05d87', '#6b7c32'];
$modelRulSeries = [];
$modelRulVariation = [];
foreach (array_keys($modelGroups) as $model) $modelRulSeries[$model] = [];
foreach ($modelGroups as $model => $modelUnits) {
  $avgHours = cs_analytics_average($modelUnits, 'operating_hours_per_day', 8);
  $avgEnergy = cs_analytics_average($modelUnits, 'energy_consumption_kwh', 1);
  $modelRulVariation[$model] = [
    'amplitude' => min(0.8, 0.32 + max(0, $avgHours - 8) * 0.025 + $avgEnergy * 0.04),
    'phase' => count($modelRulVariation) * 0.85,
  ];
}
foreach ($modelRulYears as $year) {
  $yearGroups = [];
  foreach (cs_units_for_forecast_year($year) as $yearUnit) $yearGroups[$yearUnit['model']][] = $yearUnit['rul_days'];
  foreach ($modelRulSeries as $model => &$series) {
    $days = $yearGroups[$model] ?? [];
    // Illustrative modeled variation makes operating-load differences visible; it is not measured history.
    $baseMonths = (array_sum($days) / max(count($days), 1)) / 30;
    $variation = $modelRulVariation[$model];
    $loadSwing = sin((($year - 2022) * 1.12) + $variation['phase']) * $variation['amplitude'];
    $series[] = round(max(0.2, min(12, $baseMonths + $loadSwing)), 1);
  }
  unset($series);
}
$modelRulDatasets = [];
$modelRulIndex = 0;
foreach ($modelRulSeries as $model => $series) {
  $color = $modelRulPalette[$modelRulIndex % count($modelRulPalette)];
  $modelRulDatasets[] = ['label' => $model, 'data' => $series, 'borderColor' => $color, 'backgroundColor' => $color, 'pointBackgroundColor' => $color];
  $modelRulIndex++;
}

$features = ['Current Draw', 'Subcooling delta', 'Vibration RMS', 'Coil temperature difference', 'Runtime Hrs', 'Cycle Frequency'];
$importance = [28, 24, 19, 14, 9, 6];
$features = ['Temperature (C)', 'Daily hours', 'Energy (kWh)', 'Compression (%)', 'Filter (%)'];
$importance = [
  cs_analytics_average($units, 'temp'),
  cs_analytics_average($units, 'operating_hours_per_day'),
  cs_analytics_average($units, 'energy_consumption_kwh', 2),
  cs_analytics_average($units, 'compression_condition_score'),
  cs_analytics_average($units, 'filter_condition_score'),
];
$features = ['Compression condition', 'Filter condition', 'Fleet health'];
$importance = [
  cs_analytics_average($units, 'compression_condition_score'),
  cs_analytics_average($units, 'filter_condition_score'),
  $fleet['avg_health'],
];
$isShopAdmin = cs_has_role('client_admin') || cs_has_role('admin_level_1') || cs_has_role('admin_level_2');
$flaggedUnits = array_values(array_filter($units, fn($unit) => $unit['status'] !== 'healthy' || $unit['rul_days'] <= 90));
$riskUnits = $flaggedUnits;
$unitsPerPage = 5;
$totalRiskPages = max(1, (int) ceil(count($riskUnits) / $unitsPerPage));
$riskPage = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]) ?: 1;
$riskPage = min($riskPage, $totalRiskPages);
$pagedRiskUnits = array_slice($riskUnits, ($riskPage - 1) * $unitsPerPage, $unitsPerPage);
$actionForUnit = function (array $unit): array {
  if ($unit['status'] === 'critical' || $unit['rul_days'] <= 14) {
    return ['when' => 'Within 48 hours', 'action' => 'Arrange an urgent inspection and check compressor, refrigerant charge, and electrical connections.', 'class' => 'crit'];
  }
  if ($unit['rul_days'] <= 30) {
    return ['when' => 'Within 7 days', 'action' => 'Book preventive service; inspect the filter, coil condition, and refrigerant charge.', 'class' => 'warn'];
  }
  if ($unit['status'] === 'warning' || $unit['rul_days'] <= 90) {
    return ['when' => 'Within 30 days', 'action' => 'Plan a maintenance visit and recheck the unit condition before the next service cycle.', 'class' => 'warn'];
  }
  return ['when' => 'Routine check', 'action' => 'Keep the current schedule and review the unit again in 30 days.', 'class' => 'ok'];
};
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/inspection-popup.php';
if ($inspectionFlash): ?><div class="panel"><div class="panel-body" style="color:<?= $inspectionFlash['type'] === 'success' ? 'var(--forest-600)' : 'var(--rust)' ?>;"><?= htmlspecialchars($inspectionFlash['message']) ?></div></div><?php endif; ?>
<?php if ($isShopAdmin): ?>
<div class="page-head">
  <div>
    <span class="eyebrow">Shop Maintenance Planner</span>
    <h1>Predictive Analytics</h1>
    <p>See which air conditioners need attention, when to service them, and what to check.</p>
  </div>
  <div class="page-actions">
    <label class="u-text-sm u-text-slate" for="analyticsForecastYear">Forecast year</label>
    <select class="input input-sm" id="analyticsForecastYear"><?php foreach ($forecastYears as $year): ?><option value="<?= $year ?>" <?= $year === $forecastYear ? 'selected' : '' ?>><?= $year ?></option><?php endforeach; ?></select>
    <a class="btn btn-primary" href="maintenance.php">Open Maintenance Log</a>
  </div>
</div>

<div class="kpi-row">
  <div class="kpi-card"><span class="kpi-label">High Risk</span><div class="kpi-value"><?= $fleet['critical'] ?> <small>units</small></div><div class="kpi-delta down">Require immediate inspection</div></div>
  <div class="kpi-card"><span class="kpi-label">Maintenance Forecast</span><div class="kpi-value"><?= number_format($workloadWindows['week'] + $workloadWindows['month']) ?> <small>units</small></div><div class="kpi-delta down">High risk or due within 30 days</div></div>
  <div class="kpi-card"><span class="kpi-label">Scheduled</span><div class="kpi-value"><?= $scheduledUnits ?> <small>units</small></div><div class="kpi-delta up">Already assigned for service</div></div>
  <div class="kpi-card"><span class="kpi-label">Fleet Health</span><div class="kpi-value"><?= $fleet['avg_health'] ?> <small>% average</small></div><div class="kpi-delta <?= $annualHealthChange < 0 ? 'down' : 'up' ?>"><?php if ($annualHealthChange < 0): ?>Down <?= abs($annualHealthChange) ?>% from previous year<?php elseif ($annualHealthChange > 0): ?>Up <?= $annualHealthChange ?>% from previous year<?php else: ?>No change from previous year<?php endif; ?></div></div>
</div>

<div class="grid-2">
  <div class="panel">
    <div class="panel-head"><div><h3>What needs attention?</h3></div></div>
    <div class="panel-body admin-priority-list">
      <?php if (!$riskUnits): ?>
        <div class="empty-state"><strong>No units are currently flagged.</strong><p>Keep routine maintenance on schedule and check back regularly.</p></div>
      <?php else: foreach ($pagedRiskUnits as $unit): $action = $actionForUnit($unit); $meta = cs_status_meta($unit['status']); $unitRisk = $riskForUnit($unit); ?>
        <article class="admin-priority-card">
          <div class="admin-priority-heading">
            <div class="admin-priority-unit"><span class="status-chip <?= $meta['class'] ?>"><?= htmlspecialchars($meta['label']) ?></span><strong><?= htmlspecialchars($unit['id']) ?></strong><span><?= htmlspecialchars($unit['name']) ?></span></div>
            <strong class="admin-priority-rul"><?= $unitRisk['score'] ?>% Maintenance Risk</strong>
          </div>
          <div class="unit-risk-score"><span>Estimated maintenance: <?= htmlspecialchars($action['when']) ?></span></div>
          <p class="unit-risk-reasons"><strong>Why:</strong> <?= htmlspecialchars(implode(' | ', $unitRisk['reasons'])) ?></p>
          <div class="admin-priority-when"><strong>When:</strong> <?= htmlspecialchars($action['when']) ?></div>
          <p class="admin-priority-recommendation"><strong>Recommended action</strong><span><?= htmlspecialchars($action['action']) ?></span></p>
          <div class="admin-priority-actions">
            <a class="btn btn-ghost btn-sm" href="unit-detail.php?id=<?= urlencode($unit['id']) ?>">Review Unit</a>
            <a class="btn btn-ghost btn-sm" href="analytics.php?inspection=1&amp;unit=<?= urlencode($unit['id']) ?>&amp;task=<?= urlencode('Inspection: ' . $action['action']) ?>&amp;priority=<?= $unit['status'] === 'critical' || $unit['rul_days'] <= 14 ? 'urgent' : ($unitRisk['score'] >= 70 ? 'high' : 'normal') ?>&amp;risk=<?= $unitRisk['score'] ?>">Record Inspection</a>
            <a class="btn btn-primary btn-sm" href="maintenance.php?unit=<?= urlencode($unit['id']) ?>&amp;task=<?= urlencode($action['action']) ?>&amp;status=scheduled&amp;priority=<?= $unit['status'] === 'critical' || $unit['rul_days'] <= 14 ? 'urgent' : ($unitRisk['score'] >= 70 ? 'high' : 'normal') ?>&amp;risk=<?= $unitRisk['score'] ?>">Schedule Maintenance</a>
          </div>
        </article>
      <?php endforeach; endif; ?>
      <?php if ($totalRiskPages > 1): ?>
        <nav class="admin-priority-pagination" aria-label="Maintenance priority pages">
          <span>Showing <?= (($riskPage - 1) * $unitsPerPage) + 1 ?>-<?= min($riskPage * $unitsPerPage, count($riskUnits)) ?> of <?= count($riskUnits) ?> units</span>
          <div>
            <?php if ($riskPage > 1): ?><a class="btn btn-ghost btn-sm" href="?forecast_year=<?= $forecastYear ?>&page=<?= $riskPage - 1 ?>">Previous</a><?php endif; ?>
            <?php if ($riskPage < $totalRiskPages): ?><a class="btn btn-ghost btn-sm" href="?forecast_year=<?= $forecastYear ?>&page=<?= $riskPage + 1 ?>">Next</a><?php endif; ?>
          </div>
        </nav>
      <?php endif; ?>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head"><div><h3>Upcoming Maintenance Workload</h3><div class="sub">Projected service demand for <?= $forecastYear ?>; critical units are prioritized this week.</div></div></div>
    <div class="panel-body u-col u-gap-4">
      <div class="workload-row"><span><i class="workload-dot crit"></i>This week</span><b><?= number_format($workloadWindows['week']) ?> units</b></div>
      <div class="workload-row"><span><i class="workload-dot urgent"></i>Next 30 days</span><b><?= number_format($workloadWindows['month']) ?> units</b></div>
      <div class="workload-row"><span><i class="workload-dot warn"></i>31-90 days</span><b><?= number_format($workloadWindows['quarter']) ?> units</b></div>
      <div class="workload-row"><span><i class="workload-dot ok"></i>90+ days</span><b><?= number_format($workloadWindows['later']) ?> units</b></div>
      <div class="workload-hours"><strong>Estimated service workload: <?= number_format($estimatedServiceHours) ?> technician-hours</strong><span>Planning estimate at 2 hours per unit due within 30 days; maintenance records do not include task durations.</span></div>
    </div>
  </div>
</div>
<?php else: ?>
<div class="page-head">
  <div>
    <span class="eyebrow">Model Insights</span>
    <h1>Predictive Analytics</h1>
    <p>Remaining-useful-life ranking, sensor feature importance, and correlation signals from the fleet's predictive model.</p>
  </div>
  <div class="page-actions"><label class="u-text-sm u-text-slate" for="analyticsForecastYear">Forecast year</label><select class="input input-sm" id="analyticsForecastYear"><?php foreach ($forecastYears as $year): ?><option value="<?= $year ?>" <?= $year === $forecastYear ? 'selected' : '' ?>><?= $year ?></option><?php endforeach; ?></select><a class="btn btn-ghost btn-sm" href="alerts.php?forecast_year=<?= $forecastYear ?>">Year alerts</a></div>
</div>

<div class="kpi-row">
  <div class="kpi-card">
    <span class="kpi-label">Records analyzed</span>
    <div class="kpi-value"><?= number_format($fleet['total']) ?></div>
    <div class="kpi-delta up">v3.2 | retrained weekly</div>
  </div>
  <div class="kpi-card">
    <span class="kpi-label">Critical units</span>
    <div class="kpi-value"><?= $fleet['critical'] ?> <small>units</small></div>
    <div class="kpi-delta up">0.8 days better than prior version</div>
  </div>
  <div class="kpi-card">
    <span class="kpi-label">Average fleet health</span>
    <div class="kpi-value"><?= $fleet['avg_health'] ?> <small>%</small></div>
    <div class="kpi-delta up">Across <?= number_format($fleet['total']) ?> monitored units</div>
  </div>
  <div class="kpi-card">
    <span class="kpi-label">Average remaining life</span>
    <div class="kpi-value"><?= $averageRul ?> <small>days</small></div>
    <div class="kpi-delta up">Est. downtime saved: 63h</div>
  </div>
</div>

<div class="grid-2">
  <div>
    <div class="panel">
      <div class="panel-head"><div><h3>Monthly Risk for <?= $forecastYear ?></h3><div class="sub"><?= number_format($currentRisk, 1) ?>% annual risk, <?= $riskChange > 0 ? 'up ' : ($riskChange < 0 ? 'down ' : 'unchanged at ') ?><?= number_format(abs($riskChange), 1) ?> points vs <?= $forecastYear - 1 ?>  | cooling performance averages <?= number_format($averageCooling, 1) ?>%</div></div></div>
      <div class="panel-body"><div class="chart-box sm"><canvas id="monthlyRiskChart"></canvas></div></div>
      <div class="panel-body u-pt-0"><div class="sub" id="monthlyRiskSummary">Monthly risk is seasonally adjusted from the annual fleet forecast. Cooling performance is the average compression and filter condition score.</div></div>
    </div>

    <div class="panel">
      <div class="panel-head"><div><h3>Average Remaining Useful Life</h3><div class="sub">Illustrative forecast with modeled operating-load variation; not measured historical readings</div></div></div>
      <div class="panel-body"><div class="chart-box model-rul-chart-box"><canvas id="averageRulChart"></canvas></div></div>
    </div>

    <div class="panel">
      <div class="panel-head">
        <div><h3>Health vs. Daily Runtime</h3><div class="sub">Average unit health across operating-hour groups</div></div>
      </div>
      <div class="panel-body"><div class="chart-box"><canvas id="runtimeHealthChart"></canvas></div></div>
    </div>
  </div>

  <div>
    <?php if (false): ?><div class="panel">
      <div class="panel-head"><h3>How to read this</h3></div>
      <div class="panel-body recommend-list">
        <div class="recommend-item">
          <span class="ri-icon"><svg width="16" height="16" viewBox="0 0 24 24"><path d="M12 8v5M12 16h.01" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/><circle cx="12" cy="12" r="9.5" stroke="currentColor" stroke-width="1.6" fill="none"/></svg></span>
          <div><b>RUL Ranking</b><p>Units nearest zero should be scheduled first - RUL is a probabilistic estimate, not a hard deadline.</p></div>
        </div>
        <div class="recommend-item">
          <span class="ri-icon"><svg width="16" height="16" viewBox="0 0 24 24"><path d="M9 12.5 11 15l4.5-6" stroke="currentColor" stroke-width="2.2" fill="none" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="12" r="9.5" stroke="currentColor" stroke-width="1.6" fill="none"/></svg></span>
          <div><b>Feature Importance</b><p>Current draw and subcooling delta are the strongest early indicators of compressor and refrigerant issues.</p></div>
        </div>
      </div>
    </div><?php endif; ?>

    <div class="panel">
      <div class="panel-head"><div><h3>Fleet Health Distribution</h3><div class="sub">Health classification for the selected forecast year</div></div></div>
      <div class="panel-body u-row u-gap-5">
        <div class="chart-box sm chart-box-fixed"><canvas id="healthStatusChart"></canvas></div>
        <div class="u-flex-1 u-col u-gap-3">
          <div class="fleet-legend-row"><span><span class="dot ok"></span>Healthy</span><b><?= $fleet['healthy'] ?></b></div>
          <div class="fleet-legend-row"><span><span class="dot warn"></span>At risk</span><b><?= $fleet['warning'] ?></b></div>
          <div class="fleet-legend-row"><span><span class="dot crit"></span>Critical</span><b><?= $fleet['critical'] ?></b></div>
        </div>
      </div>
    </div>

    <div class="panel">
      <div class="panel-head"><div><h3>Model Condition Heatmap</h3><div class="sub">Average condition score by aircon model</div></div></div>
      <div class="panel-body">
        <div class="analytics-heatmap" role="table" aria-label="Aircon model condition scores">
          <div class="heatmap-head">Aircon model</div><div class="heatmap-head">Compression</div><div class="heatmap-head">Filter</div><div class="heatmap-head">Health</div>
          <?php foreach ($modelHealthRows as $row): ?>
            <div class="heatmap-label"><?= htmlspecialchars($row['model']) ?></div>
            <?php foreach (['compression', 'filter', 'health'] as $metric): $score = $row[$metric]; $scoreClass = $score >= 80 ? 'ok' : ($score >= 60 ? 'warn' : 'crit'); ?>
              <div class="heatmap-cell <?= $scoreClass ?>" title="<?= ucfirst($metric) ?>: <?= $score ?>%"><?= $score ?>%</div>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="panel">
      <div class="panel-head"><h3>Risk Watchlist</h3></div>
      <div class="panel-body u-col u-gap-3">
        <?php foreach (array_slice(array_filter($units, fn($u)=>$u['status']!=='healthy'), 0, 6) as $u): $m = cs_status_meta($u['status']); ?>
          <a href="unit-detail.php?id=<?= urlencode($u['id']) ?>" class="fleet-cmp-row">
            <span><b class="u-text-ink"><?= $u['id'] ?></b> | <?= htmlspecialchars($u['name']) ?></span>
            <span class="status-chip <?= $m['class'] ?>"><?= $m['label'] ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
<?php if (!$isShopAdmin): ?>
<script>
  const analyticsRiskYears = <?= json_encode($forecastYears) ?>;
  const analyticsRiskValues = <?= json_encode($forecastRisk) ?>;
  const monthlyLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  const seasonalLoad = [.88, .92, 1.01, 1.10, 1.18, 1.03, .98, .97, .98, 1.00, 1.03, .91];
  let monthlyRiskChart = null;

  function renderMonthlyRisk(year) {
    const yearIndex = analyticsRiskYears.indexOf(Number(year));
    if (yearIndex < 0) return;
    const annualRisk = analyticsRiskValues[yearIndex];
    const monthlyRisk = seasonalLoad.map(factor => Math.min(95, Math.max(5, Number((annualRisk * factor).toFixed(1)))));
    const colors = monthlyRisk.map(value => value >= 60 ? '#b4432d' : (value >= 40 ? '#c67c2e' : '#1b6b4c'));
    if (!monthlyRiskChart) {
      monthlyRiskChart = csBarChart('monthlyRiskChart', monthlyLabels, monthlyRisk, colors);
    } else {
      monthlyRiskChart.data.datasets[0].data = monthlyRisk;
      monthlyRiskChart.data.datasets[0].backgroundColor = colors;
      monthlyRiskChart.update();
    }
    const peak = Math.max(...monthlyRisk);
    const peakMonth = monthlyLabels[monthlyRisk.indexOf(peak)];
    document.getElementById('monthlyRiskSummary').textContent = `${year} forecast: ${annualRisk}% annual risk; highest estimated month is ${peakMonth} at ${peak}%. Cooling performance averages <?= number_format($averageCooling, 1) ?>% (compression and filter condition).`;
  }

  renderMonthlyRisk(<?= $forecastYear ?>);
  document.getElementById('analyticsForecastYear')?.addEventListener('change', function () {
    const url = new URL(window.location.href);
    url.searchParams.set('forecast_year', this.value);
    window.location.assign(url.toString());
  });
  csLineChart('runtimeHealthChart', <?= json_encode($runtimeLabels) ?>, <?= json_encode($runtimeHealth) ?>, { label: 'Average health', min: 0, max: 100 });
  csDoughnut('healthStatusChart', ['Healthy', 'At Risk', 'Critical'], [<?= $fleet['healthy'] ?>, <?= $fleet['warning'] ?>, <?= $fleet['critical'] ?>], ['#1b6b4c', '#c67c2e', '#b4432d']);
  csMultiLineChart('averageRulChart', <?= json_encode($modelRulYears) ?>, <?= json_encode($modelRulDatasets, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>, { yTitle: 'Average months remaining', unit: 'months', maxTicks: 8 });
</script>
<?php else: ?>
<script>
  document.getElementById('analyticsForecastYear')?.addEventListener('change', function () {
    const url = new URL(window.location.href);
    url.searchParams.set('forecast_year', this.value);
    url.searchParams.delete('page');
    window.location.assign(url.toString());
  });
</script>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer-end.php'; ?>

