<?php
$pageTitle = 'Unit Detail';
$activeNav = 'units';
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/helpers.php';

$id = $_GET['id'] ?? 'AC-101';
$u = cs_unit($id);
if (!$u) { $u = cs_units()[0]; $id = $u['id']; }
$meta = cs_status_meta($u['status']);
$pageTitle = $u['id'] . ' · ' . $u['name'];

require __DIR__ . '/includes/header.php';

$tempHist = cs_history($id, 20, $u['temp']);
$labels = array_map(fn($i) => "-".( (19-$i) )."h", range(0, 19));
$pressHist = cs_history($id.'-p', 20, $u['pressure']);
$currHist = cs_history($id.'-c', 20, $u['current']);
$vibHist = cs_history($id.'-v', 20, $u['vibration'] * 40);

$recommendations = [];
if ($u['status'] === 'critical') {
    $recommendations = [
        ['title' => 'Schedule compressor inspection within 48 hours', 'desc' => 'Current draw and subcooling trends match the failure signature seen 9-14 days before compressor lockout in similar units.'],
        ['title' => 'Check refrigerant charge & search for leaks', 'desc' => 'Subcooling delta has narrowed below 2°C, consistent with a slow refrigerant leak at the flare fittings.'],
        ['title' => 'De-prioritize non-critical load on this circuit', 'desc' => 'Reduces compressor cycling stress until service is completed.'],
    ];
} elseif ($u['status'] === 'warning') {
    $recommendations = [
        ['title' => 'Inspect condenser fan bearing & motor mounts', 'desc' => 'Vibration has trended upward over the past 7 days — early bearing wear signature.'],
        ['title' => 'Clean condenser coil and check airflow path', 'desc' => 'Restricted airflow raises head pressure and accelerates component fatigue.'],
    ];
} else {
    $recommendations = [
        ['title' => 'No action required — continue routine monitoring', 'desc' => 'All sensor readings are within nominal range for this unit\'s operating profile.'],
        ['title' => 'Next scheduled filter service in ~30 days', 'desc' => 'Based on standard maintenance interval and current runtime accumulation.'],
    ];
}

$unitLog = array_filter(cs_maintenance_log(), fn($m) => $m['unit'] === $id);
?>
<div class="detail-hero">
  <div>
    <span class="eyebrow"><?= $u['id'] ?> · <?= $meta['label'] ?></span>
    <h1><?= htmlspecialchars($u['name']) ?></h1>
    <div class="loc"><?= htmlspecialchars($u['location']) ?> · <?= htmlspecialchars($u['model']) ?></div>
  </div>
  <div class="detail-hero-metrics">
    <div class="dh-metric"><span>Temperature</span><strong data-live data-live-val="<?= $u['temp'] ?>" data-min="<?= $u['temp']-2 ?>" data-max="<?= $u['temp']+2 ?>" data-decimals="1" data-suffix="°C"><?= $u['temp'] ?>°C</strong></div>
    <div class="dh-metric"><span>Pressure</span><strong data-live data-live-val="<?= $u['pressure'] ?>" data-min="<?= $u['pressure']-8 ?>" data-max="<?= $u['pressure']+8 ?>" data-decimals="0" data-suffix=" psi"><?= $u['pressure'] ?> psi</strong></div>
    <div class="dh-metric"><span>Current Draw</span><strong data-live data-live-val="<?= $u['current'] ?>" data-min="<?= $u['current']-0.6 ?>" data-max="<?= $u['current']+0.6 ?>" data-decimals="1" data-suffix=" A"><?= $u['current'] ?> A</strong></div>
    <div class="dh-metric"><span>Vibration</span><strong data-live data-live-val="<?= $u['vibration'] ?>" data-min="0" data-max="<?= $u['vibration']+0.3 ?>" data-decimals="2" data-suffix=" mm/s"><?= $u['vibration'] ?> mm/s</strong></div>
  </div>
</div>

<div class="grid-2">
  <div>
    <div class="panel">
      <div class="panel-head">
        <div><h3>Evaporator Temperature Trend</h3><div class="sub">Last 20 hours · °C</div></div>
      </div>
      <div class="panel-body"><div class="chart-box"><canvas id="tempChart"></canvas></div></div>
    </div>

    <div class="panel">
      <div class="panel-head"><div><h3>System Pressure</h3><div class="sub">Discharge line · psi</div></div></div>
      <div class="panel-body"><div class="chart-box sm"><canvas id="pressChart"></canvas></div></div>
    </div>

    <div class="grid-2 u-cols-2">
      <div class="panel">
        <div class="panel-head"><div><h3>Compressor Current</h3><div class="sub">Amps</div></div></div>
        <div class="panel-body"><div class="chart-box sm"><canvas id="currChart"></canvas></div></div>
      </div>
      <div class="panel">
        <div class="panel-head"><div><h3>Vibration</h3><div class="sub">mm/s RMS</div></div></div>
        <div class="panel-body"><div class="chart-box sm"><canvas id="vibChart"></canvas></div></div>
      </div>
    </div>

    <div class="panel">
      <div class="panel-head"><h3>Maintenance History — <?= $id ?></h3></div>
      <div class="panel-body">
        <?php if (empty($unitLog)): ?>
          <div class="empty-state u-pad-sm">
            <p>No maintenance records yet for this unit.</p>
          </div>
        <?php else: ?>
          <div class="table-wrap">
            <table>
              <thead><tr><th>Date</th><th>Task</th><th>Technician</th><th>Status</th></tr></thead>
              <tbody>
                <?php foreach ($unitLog as $m): ?>
                  <tr>
                    <td><?= date('M j, Y', strtotime($m['date'])) ?></td>
                    <td class="strong"><?= htmlspecialchars($m['task']) ?></td>
                    <td><?= htmlspecialchars($m['tech']) ?></td>
                    <td><span class="mstatus <?= $m['status'] ?>"><?= ucfirst($m['status']) ?></span></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div>
    <div class="panel">
      <div class="panel-head"><h3>Predictive Health Score</h3></div>
      <div class="panel-body">
        <div class="big-gauge-row">
          <?= cs_gauge($u['health'], $u['status'], 108, 'Score') ?>
          <div class="u-flex-1">
            <div class="spec-row"><span>Status</span><b><span class="status-chip <?= $meta['class'] ?>"><?= $meta['label'] ?></span></b></div>
            <div class="spec-row"><span>Est. RUL</span><b class="rul-tag <?= cs_rul_class($u['rul_days']) ?>"><?= $u['rul_days'] ?> days</b></div>
            <div class="spec-row"><span>Confidence</span><b><?= $u['status']==='critical' ? '94%' : ($u['status']==='warning' ? '87%' : '96%') ?></b></div>
          </div>
        </div>
      </div>
    </div>

    <div class="panel">
      <div class="panel-head"><h3>Recommended Actions</h3></div>
      <div class="panel-body">
        <div class="recommend-list">
          <?php foreach ($recommendations as $r): ?>
            <div class="recommend-item">
              <span class="ri-icon">
                <svg width="16" height="16" viewBox="0 0 24 24"><path d="M9 12.5 11 15l4.5-6" stroke="currentColor" stroke-width="2.2" fill="none" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="12" r="9.5" stroke="currentColor" stroke-width="1.6" fill="none"/></svg>
              </span>
              <div>
                <b><?= htmlspecialchars($r['title']) ?></b>
                <p><?= htmlspecialchars($r['desc']) ?></p>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <button class="btn btn-primary u-w-full btn-block-top" data-modal-open="#modalMaint">Log Maintenance for <?= $id ?></button>
      </div>
    </div>

    <div class="panel">
      <div class="panel-head"><h3>Unit Specifications</h3></div>
      <div class="panel-body spec-list">
        <div class="spec-row"><span>Unit ID</span><b class="mono"><?= $u['id'] ?></b></div>
        <div class="spec-row"><span>Model</span><b><?= htmlspecialchars($u['model']) ?></b></div>
        <div class="spec-row"><span>Installed</span><b><?= date('F j, Y', strtotime($u['installed'])) ?></b></div>
        <div class="spec-row"><span>Total Runtime</span><b><?= number_format($u['runtime_hrs']) ?> hrs</b></div>
        <div class="spec-row"><span>Relative Humidity</span><b><?= $u['humidity'] ?>%</b></div>
        <div class="spec-row"><span>Location</span><b><?= htmlspecialchars($u['location']) ?></b></div>
      </div>
    </div>
  </div>
</div>

<div class="modal-backdrop" id="modalMaint">
  <div class="modal">
    <div class="modal-head">
      <h3>Log Maintenance — <?= $id ?></h3>
      <button class="modal-close" data-modal-close aria-label="Close">✕</button>
    </div>
    <form id="maintForm">
      <div class="field">
        <label for="fTask2">Task Description</label>
        <input id="fTask2" class="input" type="text" placeholder="e.g. Refrigerant recharge" required>
      </div>
      <div class="field">
        <label for="fDate2">Scheduled Date</label>
        <input id="fDate2" class="input" type="date" required>
      </div>
      <div class="field">
        <label for="fNotes2">Notes</label>
        <textarea id="fNotes2" class="input" placeholder="Optional notes for the technician…"></textarea>
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
  const labels = <?= json_encode($labels) ?>;
  const tempChart = csLineChart('tempChart', labels, <?= json_encode($tempHist) ?>, { label: 'Temperature' });
  if (tempChart) tempChart._live = true;
  csLineChart('pressChart', labels, <?= json_encode($pressHist) ?>, { label: 'Pressure' });
  csLineChart('currChart', labels, <?= json_encode($currHist) ?>, { label: 'Current' });
  csLineChart('vibChart', labels, <?= json_encode($vibHist) ?>, { label: 'Vibration' });
</script>

<?php require __DIR__ . '/includes/footer-end.php'; ?>
