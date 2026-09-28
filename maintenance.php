<?php
$pageTitle = 'Maintenance Log';
$activeNav = 'maintenance';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/helpers.php';
$requireOrgId = cs_current_user()['organization_id'] ?? null;
cs_require_permission('maintenance.manage', $requireOrgId);

if (empty($_SESSION['csrf_maintenance'])) $_SESSION['csrf_maintenance'] = bin2hex(random_bytes(32));
$flash = $_SESSION['maintenance_flash'] ?? null;
unset($_SESSION['maintenance_flash']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = (string)($_POST['csrf_token'] ?? '');
  $date = trim((string)($_POST['date'] ?? ''));
  $unitId = trim((string)($_POST['unit'] ?? ''));
  $task = trim((string)($_POST['task'] ?? ''));
  $technician = trim((string)($_POST['technician'] ?? 'Unassigned'));
  $status = trim((string)($_POST['status'] ?? 'scheduled'));
  $unit = cs_unit($unitId);
  $validDate = DateTime::createFromFormat('!Y-m-d', $date);
  if (!hash_equals($_SESSION['csrf_maintenance'], $token)) {
    $flash = ['type' => 'error', 'message' => 'Your session expired. Refresh the page and try again.'];
  } elseif (!$validDate || $validDate->format('Y-m-d') !== $date || !$unit || $task === '' || strlen($task) > 255 || strlen($technician) > 120 || !in_array($status, ['scheduled', 'urgent', 'completed'], true)) {
    $flash = ['type' => 'error', 'message' => 'Check the date, unit, task, technician, and status, then try again.'];
  } else {
    try {
      cs_add_maintenance_log(['date' => $date, 'unit' => $unitId, 'name' => $unit['name'], 'task' => $task, 'tech' => $technician, 'status' => $status], (int)(cs_current_user()['id'] ?? 0) ?: null);
      $_SESSION['maintenance_flash'] = ['type' => 'success', 'message' => 'Maintenance task saved to the log.'];
      header('Location: maintenance.php');
      exit;
    } catch (Throwable $e) {
      $flash = ['type' => 'error', 'message' => 'The task could not be saved. Check the database connection and try again.'];
    }
  }
  $_SESSION['maintenance_flash'] = $flash;
  header('Location: maintenance.php');
  exit;
}

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

<?php if ($flash): ?>
  <div class="panel"><div class="panel-body" style="color:<?= $flash['type'] === 'success' ? 'var(--forest-600)' : 'var(--rust)' ?>;"><?= htmlspecialchars($flash['message']) ?></div></div>
<?php endif; ?>

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
    <form id="maintForm" method="post" action="maintenance.php">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_maintenance']) ?>">
      <div class="field">
        <label for="fUnit">AC Unit</label>
        <select id="fUnit" name="unit" class="input" required>
          <?php foreach (cs_units() as $u): ?>
            <option value="<?= $u['id'] ?>"><?= $u['id'] ?> — <?= htmlspecialchars($u['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="fTask">Task Description</label>
        <input id="fTask" name="task" class="input" type="text" maxlength="255" placeholder="e.g. Refrigerant recharge" required>
      </div>
      <div class="field">
        <label for="fTech">Assign Technician</label>
        <select id="fTech" name="technician" class="input" required>
          <option>Unassigned</option>
          <option>J. Ramos</option>
          <option>M. Santos</option>
        </select>
      </div>
      <div class="field">
        <label for="fDate">Scheduled Date</label>
        <input id="fDate" name="date" class="input" type="date" required>
      </div>
      <div class="field">
        <label for="fStatus">Status</label>
        <select id="fStatus" name="status" class="input" required>
          <option value="scheduled">Scheduled</option>
          <option value="urgent">Urgent</option>
          <option value="completed">Completed</option>
        </select>
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
