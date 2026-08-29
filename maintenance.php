<?php
$pageTitle = 'Maintenance Log';
$activeNav = 'maintenance';
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/header.php';

$log = cs_maintenance_log();
usort($log, fn($a,$b) => strtotime($b['date']) <=> strtotime($a['date']));
$counts = ['completed'=>0,'scheduled'=>0,'urgent'=>0];
foreach ($log as $l) $counts[$l['status']]++;
?>
<div class="page-head">
  <div>
    <span class="eyebrow">Service History</span>
    <h1>Maintenance Log</h1>
    <p>Completed, scheduled, and urgent service tasks across the fleet.</p>
  </div>
  <div class="page-actions">
    <button class="btn btn-primary" data-modal-open="#modalMaint">
      <svg width="15" height="15" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
      Log Task
    </button>
  </div>
</div>

<div class="kpi-row">
  <div class="kpi-card">
    <span class="kpi-label">Completed</span>
    <div class="kpi-value" style="color:var(--forest-600);"><?= $counts['completed'] ?></div>
    <div class="kpi-delta up">Last 30 days</div>
  </div>
  <div class="kpi-card">
    <span class="kpi-label">Scheduled</span>
    <div class="kpi-value" style="color:var(--forest-500);"><?= $counts['scheduled'] ?></div>
    <div class="kpi-delta up">Upcoming</div>
  </div>
  <div class="kpi-card">
    <span class="kpi-label">Urgent</span>
    <div class="kpi-value" style="color:var(--rust);"><?= $counts['urgent'] ?></div>
    <div class="kpi-delta down">Needs assignment</div>
  </div>
  <div class="kpi-card">
    <span class="kpi-label">Avg. Response Time</span>
    <div class="kpi-value">3.2 <small>hrs</small></div>
    <div class="kpi-delta up">From alert to dispatch</div>
  </div>
</div>

<div class="filter-bar">
  <select data-filter-select="status">
    <option value="">All statuses</option>
    <option value="completed">Completed</option>
    <option value="scheduled">Scheduled</option>
    <option value="urgent">Urgent</option>
  </select>
</div>

<div class="panel">
  <div class="panel-body">
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Date</th><th>Unit</th><th>Task</th><th>Technician</th><th>Status</th></tr>
        </thead>
        <tbody>
          <?php foreach ($log as $m): ?>
            <tr data-searchable="<?= htmlspecialchars($m['unit'].' '.$m['name'].' '.$m['task'].' '.$m['tech']) ?>" data-status="<?= $m['status'] ?>">
              <td class="mono"><?= date('M j, Y', strtotime($m['date'])) ?></td>
              <td><a href="unit-detail.php?id=<?= urlencode($m['unit']) ?>" class="strong"><?= $m['unit'] ?></a><br><span class="u-text-slate u-text-xs"><?= htmlspecialchars($m['name']) ?></span></td>
              <td><?= htmlspecialchars($m['task']) ?></td>
              <td><?= htmlspecialchars($m['tech']) ?></td>
              <td><span class="mstatus <?= $m['status'] ?>"><?= ucfirst($m['status']) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

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
          <?php foreach (cs_units() as $u): ?>
            <option value="<?= $u['id'] ?>"><?= $u['id'] ?> — <?= htmlspecialchars($u['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="fTask">Task Description</label>
        <input id="fTask" class="input" type="text" placeholder="e.g. Refrigerant recharge" required>
      </div>
      <div class="field">
        <label for="fTech">Assign Technician</label>
        <select id="fTech" class="input">
          <option>Unassigned</option>
          <option>J. Ramos</option>
          <option>M. Santos</option>
        </select>
      </div>
      <div class="field">
        <label for="fDate">Scheduled Date</label>
        <input id="fDate" class="input" type="date" required>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary">Save Task</button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
<?php require __DIR__ . '/includes/footer-end.php'; ?>
