<?php
require_once __DIR__ . '/includes/auth.php';
cs_require_role('client_admin');
$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/header.php';

$units = cs_units();
$fleet = cs_fleet_summary();
$alerts = array_slice(cs_alerts(), 0, 5);
$history = cs_history('FLEET', 24, $fleet['avg_health']);
$hlabels = array_map(fn($i) => sprintf('%02d:00', ($i * 1) % 24), range(0, 23));
?>
<div class="page-head">
  <div>
    <span class="eyebrow">Live Fleet Overview</span>
    <h1>Good afternoon, Julius.</h1>
    <p>6 split-type units are online across 3 buildings. Predictive models flag 1 unit for urgent attention.</p>
  </div>
  <div class="page-actions">
    <button class="btn btn-ghost" id="exportBtn">
      <svg width="15" height="15" viewBox="0 0 24 24"><path d="M12 3v12m0 0-4-4m4 4 4-4M5 21h14" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
      Export Report
    </button>
    <button class="btn btn-primary" data-modal-open="#modalMaint">
      <svg width="15" height="15" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
      Log Maintenance
    </button>
  </div>
</div>

<div class="kpi-row">
  <div class="kpi-card">
    <span class="kpi-icon">
      <svg width="17" height="17" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="10" rx="2" stroke="currentColor" stroke-width="2" fill="none"/><path d="M7 19h10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
    </span>
    <span class="kpi-label">Total Units</span>
    <div class="kpi-value"><?= $fleet['total'] ?> <small>units</small></div>
    <div class="kpi-delta up">Across 3 buildings</div>
  </div>
  <div class="kpi-card">
    <span class="kpi-icon">
      <svg width="17" height="17" viewBox="0 0 24 24"><path d="M12 3c0 8-9 10-9 18a9 9 0 0 0 18 0c0-8-9-10-9-18Z" stroke="currentColor" stroke-width="2" fill="none"/></svg>
    </span>
    <span class="kpi-label">Avg. Fleet Health</span>
    <div class="kpi-value" data-live data-live-val="<?= $fleet['avg_health'] ?>" data-min="60" data-max="90" data-decimals="0" data-suffix="%"><?= $fleet['avg_health'] ?>%</div>
    <div class="kpi-delta down">−3.1% vs last week</div>
  </div>
  <div class="kpi-card">
    <span class="kpi-icon" style="color:var(--amber);background:var(--amber-bg)">
      <svg width="17" height="17" viewBox="0 0 24 24"><path d="M12 3 2 20h20L12 3Z" stroke="currentColor" stroke-width="2" fill="none" stroke-linejoin="round"/><path d="M12 10v4M12 17h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
    </span>
    <span class="kpi-label">Units At Risk</span>
    <div class="kpi-value"><?= $fleet['warning'] ?> <small>warning</small></div>
    <div class="kpi-delta down">RUL under 60 days</div>
  </div>
  <div class="kpi-card">
    <span class="kpi-icon" style="color:var(--rust);background:var(--rust-bg)">
      <svg width="17" height="17" viewBox="0 0 24 24"><path d="M12 8v5M12 16h.01" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2" fill="none"/></svg>
    </span>
    <span class="kpi-label">Critical Units</span>
    <div class="kpi-value"><?= $fleet['critical'] ?> <small>needs action</small></div>
    <div class="kpi-delta down">AC-104 · RUL 9 days</div>
  </div>
</div>

<div class="grid-2">
  <div>
    <div class="panel">
      <div class="panel-head">
        <div>
          <h3>Fleet Health Trend</h3>
          <div class="sub">Composite predictive score, rolling 24h — <span data-live data-live-val="<?= $fleet['avg_health'] ?>" data-min="60" data-max="90" data-decimals="0" data-suffix="% avg"><?= $fleet['avg_health'] ?>% avg</span></div>
        </div>
        <div class="tabs">
          <button class="tab active">24h</button>
          <button class="tab">7d</button>
          <button class="tab">30d</button>
        </div>
      </div>
      <div class="panel-body">
        <div class="chart-box">
          <canvas id="fleetTrendChart"></canvas>
        </div>
      </div>
    </div>

    <div class="panel-head panel-head-flush">
      <h3>Monitored Units</h3>
      <a href="units.php" class="btn btn-ghost btn-sm">View all →</a>
    </div>
    <div class="unit-grid">
      <?php foreach ($units as $u): $meta = cs_status_meta($u['status']); ?>
        <a class="unit-card" href="unit-detail.php?id=<?= urlencode($u['id']) ?>" data-searchable="<?= htmlspecialchars($u['id'].' '.$u['name'].' '.$u['location']) ?>">
          <div class="unit-card-top">
            <div>
              <div class="unit-card-id"><?= $u['id'] ?></div>
              <div class="unit-card-name"><?= htmlspecialchars($u['name']) ?></div>
              <div class="unit-card-loc"><?= htmlspecialchars($u['location']) ?></div>
            </div>
            <span class="status-chip <?= $meta['class'] ?>"><?= $meta['label'] ?></span>
          </div>
          <div class="unit-card-mid">
            <?= cs_gauge($u['health'], $u['status'], 72) ?>
            <div class="unit-card-metrics">
              <div class="metric">Temp <b><?= $u['temp'] ?>°C</b></div>
              <div class="metric">Pressure <b><?= $u['pressure'] ?> psi</b></div>
              <div class="metric">Current <b><?= $u['current'] ?> A</b></div>
              <div class="metric">Vibration <b><?= $u['vibration'] ?> mm/s</b></div>
            </div>
          </div>
          <div class="unit-card-foot">
            <span>Est. Remaining Useful Life</span>
            <span class="rul-tag <?= cs_rul_class($u['rul_days']) ?>"><?= $u['rul_days'] ?> days</span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <div>
    <div class="panel">
      <div class="panel-head">
        <div>
          <h3>Active Alerts</h3>
          <div class="sub">Predictive model flags, newest first</div>
        </div>
        <a href="alerts.php" class="btn btn-ghost btn-sm">All alerts</a>
      </div>
      <div class="panel-body">
        <?php foreach ($alerts as $a): ?>
          <div class="alert-item">
            <span class="alert-dot <?= $a['severity'] ?>"></span>
            <div class="alert-body">
              <div class="alert-top">
                <strong><?= htmlspecialchars($a['name']) ?> <span class="mono u-text-slate u-fw-500">· <?= $a['unit'] ?></span></strong>
                <span class="alert-time"><?= $a['time'] ?></span>
              </div>
              <p class="alert-msg"><?= htmlspecialchars($a['message']) ?></p>
              <div class="alert-tags"><span class="tag"><?= htmlspecialchars($a['type']) ?></span></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="panel">
      <div class="panel-head">
        <div>
          <h3>Fleet Status Split</h3>
          <div class="sub">Share of units by predictive status</div>
        </div>
      </div>
      <div class="panel-body u-row u-gap-5">
        <div class="chart-box sm chart-box-fixed">
          <canvas id="statusDoughnut"></canvas>
        </div>
        <div class="u-flex-1 u-col u-gap-3">
          <div class="fleet-legend-row"><span><span class="dot ok"></span>Healthy</span><b><?= $fleet['healthy'] ?></b></div>
          <div class="fleet-legend-row"><span><span class="dot warn"></span>At Risk</span><b><?= $fleet['warning'] ?></b></div>
          <div class="fleet-legend-row"><span><span class="dot crit"></span>Critical</span><b><?= $fleet['critical'] ?></b></div>
        </div>
      </div>
    </div>

    <div class="panel">
      <div class="panel-head"><h3>Upcoming Maintenance</h3></div>
      <div class="panel-body u-col u-gap-4">
        <?php foreach (array_slice(array_filter(cs_maintenance_log(), fn($m)=>$m['status']!=='completed'), 0, 3) as $m): ?>
          <div class="upcoming-row">
            <div>
              <div class="u-fw-600 u-text-ink"><?= htmlspecialchars($m['task']) ?></div>
              <div class="u-text-slate u-mt-1"><?= $m['unit'] ?> · <?= date('M j', strtotime($m['date'])) ?></div>
            </div>
            <span class="mstatus <?= $m['status'] ?>"><?= ucfirst($m['status']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- Log Maintenance Modal -->
<div class="modal-backdrop" id="modalMaint">
  <div class="modal">
    <div class="modal-head">
      <h3>Log Maintenance Task</h3>
      <button class="modal-close" data-modal-close aria-label="Close">✕</button>
    </div>
    <form id="maintForm">
      <div class="field">
        <label for="fUnit">AC Unit</label>
        <select id="fUnit" class="input" required>
          <?php foreach ($units as $u): ?>
            <option value="<?= $u['id'] ?>"><?= $u['id'] ?> — <?= htmlspecialchars($u['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="fTask">Task Description</label>
        <input id="fTask" class="input" type="text" placeholder="e.g. Refrigerant recharge" required>
      </div>
      <div class="field">
        <label for="fDate">Scheduled Date</label>
        <input id="fDate" class="input" type="date" required>
      </div>
      <div class="field">
        <label for="fNotes">Notes</label>
        <textarea id="fNotes" class="input" placeholder="Optional notes for the technician…"></textarea>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary">Save Task</button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
<script>
  const fleetLabels = <?= json_encode($hlabels) ?>;
  const fleetData = <?= json_encode($history) ?>;
  const trendChart = csLineChart('fleetTrendChart', fleetLabels, fleetData, { label: 'Fleet Health', min: Math.min(...fleetData) - 2, max: Math.max(...fleetData) + 2 });
  if (trendChart) trendChart._live = true;

  csDoughnut('statusDoughnut',
    ['Healthy', 'At Risk', 'Critical'],
    [<?= $fleet['healthy'] ?>, <?= $fleet['warning'] ?>, <?= $fleet['critical'] ?>],
    ['#1b6b4c', '#c67c2e', '#b4432d']
  );

  document.getElementById('exportBtn')?.addEventListener('click', () => csToast('Report export started — you will be notified when ready.'));
</script>

<?php require __DIR__ . '/includes/footer-end.php'; ?>
