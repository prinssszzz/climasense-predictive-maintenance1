<?php
$pageTitle = 'Maintenance Log';
$activeNav = 'maintenance';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/helpers.php';
$requireOrgId = cs_current_user()['organization_id'] ?? null;
cs_require_permission('maintenance.manage', $requireOrgId);
cs_ensure_consumer_unit_schema();
$shopRegisteredUnits = cs_registered_units_for_shop((int)$requireOrgId);
$maintenanceUnitsById = [];
foreach (array_merge(cs_units(), $shopRegisteredUnits) as $maintenanceUnit) $maintenanceUnitsById[$maintenanceUnit['id']] = $maintenanceUnit;
$maintenanceUnits = array_values($maintenanceUnitsById);
$findMaintenanceUnit = static function (string $unitCode) use ($shopRegisteredUnits): ?array {
  foreach ($shopRegisteredUnits as $registeredUnit) if ($registeredUnit['id'] === $unitCode) return $registeredUnit;
  return cs_unit($unitCode);
};
$prefillUnit = trim((string)($_GET['unit'] ?? ''));
$inspectionMode = ($_GET['inspection'] ?? '') === '1' && $prefillUnit !== '';
$prefillTask = trim((string)($_GET['task'] ?? ''));
$prefillStatus = (string)($_GET['status'] ?? 'scheduled');
if (!$findMaintenanceUnit($prefillUnit)) $prefillUnit = '';
if (!in_array($prefillStatus, ['scheduled', 'urgent', 'completed'], true)) $prefillStatus = 'scheduled';
$prefillUnitData = $prefillUnit ? $findMaintenanceUnit($prefillUnit) : null;
$riskValue = filter_input(INPUT_GET, 'risk', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100]]);
if ($riskValue === false || $riskValue === null) $riskValue = $prefillUnitData ? max(5, min(95, 100 - (int)$prefillUnitData['health'])) : null;
$prefillPriority = (string)($_GET['priority'] ?? ($prefillStatus === 'urgent' ? 'urgent' : (($riskValue ?? 0) >= 70 ? 'high' : 'normal')));
if (!in_array($prefillPriority, ['urgent', 'high', 'normal', 'low'], true)) $prefillPriority = 'normal';
$prefillDate = date('Y-m-d', strtotime($prefillStatus === 'urgent' ? '+2 days' : '+7 days'));
if (isset($_GET['date'])) {
  $requestedDate = (string)$_GET['date'];
  $parsedDate = DateTime::createFromFormat('!Y-m-d', $requestedDate);
  if ($parsedDate && $parsedDate->format('Y-m-d') === $requestedDate) $prefillDate = $requestedDate;
}

if (empty($_SESSION['csrf_maintenance'])) $_SESSION['csrf_maintenance'] = bin2hex(random_bytes(32));
$flash = $_SESSION['maintenance_flash'] ?? null;
unset($_SESSION['maintenance_flash']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = (string)($_POST['csrf_token'] ?? '');
  $date = trim((string)($_POST['date'] ?? ''));
  $time = trim((string)($_POST['time'] ?? ''));
  $unitId = trim((string)($_POST['unit'] ?? ''));
  $task = trim((string)($_POST['task'] ?? ''));
  $maintenanceType = trim((string)($_POST['maintenance_type'] ?? 'Cleaning'));
  $faultReported = trim((string)($_POST['fault_reported'] ?? ''));
  $repairPerformed = trim((string)($_POST['repair_performed'] ?? ''));
  $replacedComponents = trim((string)($_POST['replaced_components'] ?? ''));
  $remarks = trim((string)($_POST['remarks'] ?? ''));
  $technician = trim((string)($_POST['technician'] ?? 'Unassigned'));
  $status = trim((string)($_POST['status'] ?? 'scheduled'));
  $priority = trim((string)($_POST['priority'] ?? 'normal'));
  $unit = $findMaintenanceUnit($unitId);
  $postedRisk = filter_input(INPUT_POST, 'risk', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100]]);
  if ($postedRisk === false || $postedRisk === null) $postedRisk = $unit ? max(5, min(95, 100 - (int)$unit['health'])) : null;
  $inspectionReport = null;
  if (isset($_POST['inspection_report']) && is_array($_POST['inspection_report'])) {
    $submitted = $_POST['inspection_report'];
    $allowedWork = ['Filter cleaned', 'Filter replaced', 'Refrigerant added', 'Electrical connection repaired', 'Compressor repaired', 'Other', 'Component replaced'];
    $inspectionReport = [
      'compressor' => in_array($submitted['compressor'] ?? '', ['Normal', 'Warning', 'Abnormal'], true) ? $submitted['compressor'] : 'Normal',
      'filter' => in_array($submitted['filter'] ?? '', ['Clean', 'Dirty', 'Replace', 'Needs replacement'], true) ? (($submitted['filter'] ?? '') === 'Needs replacement' ? 'Replace' : $submitted['filter']) : 'Clean',
      'refrigerant' => in_array($submitted['refrigerant'] ?? '', ['Normal', 'Low', 'Possible leak'], true) ? $submitted['refrigerant'] : 'Normal',
      'work' => array_values(array_intersect($allowedWork, array_map('strval', (array)($submitted['work'] ?? [])))),
      'findings' => trim(substr((string)($submitted['findings'] ?? ''), 0, 2000)),
      'final_condition' => match ($submitted['final_condition'] ?? '') {
        'Good', 'Resolved' => 'Resolved',
        'Monitor' => 'Monitor',
        'Needs Follow-up', 'Follow-up required' => 'Follow-up required',
        'Needs Major Repair', 'Major repair required' => 'Major repair required',
        default => 'Resolved',
      },
    ];
    $inspectionReport['readings'] = $unit && empty($unit['registered']) ? array_intersect_key($unit, array_flip(['pressure', 'current', 'vibration', 'humidity', 'temp', 'operating_hours_per_day'])) : [];
    $maintenanceType = 'Inspection';
    $faultReported = trim((string)($submitted['fault_reported'] ?? ''));
    $repairPerformed = implode(', ', $inspectionReport['work']);
    $replacedComponents = trim((string)($submitted['replaced_components'] ?? ''));
    $remarks = trim((string)($submitted['remarks'] ?? $inspectionReport['findings']));
  }
  $validDate = DateTime::createFromFormat('!Y-m-d', $date);
  if (!hash_equals($_SESSION['csrf_maintenance'], $token)) {
    $flash = ['type' => 'error', 'message' => 'Your session expired. Refresh the page and try again.'];
  } elseif (!$validDate || $validDate->format('Y-m-d') !== $date || ($time !== '' && !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', $time)) || !$unit || $task === '' || strlen($task) > 255 || !in_array($maintenanceType, ['Cleaning', 'Preventive Maintenance', 'Repair', 'Inspection', 'Other'], true) || strlen($faultReported) > 2000 || strlen($repairPerformed) > 2000 || strlen($replacedComponents) > 1000 || strlen($remarks) > 2000 || strlen($technician) > 120 || !in_array($status, ['scheduled', 'urgent', 'completed'], true) || !in_array($priority, ['urgent', 'high', 'normal', 'low'], true)) {
    $flash = ['type' => 'error', 'message' => 'Check the date, unit, task, technician, priority, and status, then try again.'];
  } else {
    try {
      cs_add_maintenance_log(['date' => $date, 'time' => $status === 'scheduled' && $time !== '' ? $time : null, 'unit' => $unitId, 'name' => $unit['name'], 'task' => $task, 'tech' => $technician, 'status' => $status, 'priority' => $priority, 'risk' => $postedRisk, 'inspection_report' => $inspectionReport, 'service_details' => ['maintenance_type' => $maintenanceType, 'fault_reported' => $faultReported, 'repair_performed' => $repairPerformed, 'replaced_components' => $replacedComponents, 'remarks' => $remarks]], (int)(cs_current_user()['id'] ?? 0) ?: null);
      $_SESSION['maintenance_flash'] = ['type' => 'success', 'message' => $inspectionReport ? 'Inspection saved successfully. ' . $unitId . ' has been updated.' : 'Maintenance task saved to the log.'];
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
$needsAttention = 0;
$inspectedUnitsThisMonth = [];
$monthStart = date('Y-m-01');
$nextMonthStart = date('Y-m-01', strtotime('+1 month'));
foreach ($log as $l) {
  if (isset($counts[$l['status']])) $counts[$l['status']]++;
  $report = $l['inspection_report'] ?? null;
  if ($report) {
    if (in_array($report['final_condition'] ?? '', ['Follow-up required', 'Major repair required'], true)) $needsAttention++;
    if (($l['date'] ?? '') >= $monthStart && ($l['date'] ?? '') < $nextMonthStart) $inspectedUnitsThisMonth[$l['unit']] = true;
  }
}
?>
  <div class="page-head">
  <div>
    <?php if (!$inspectionMode): ?><span class="eyebrow">Service History</span><?php endif; ?>
    <h1><?= $inspectionMode ? 'Record AC Inspection' : 'Maintenance Log' ?></h1>
    <p><?= $inspectionMode ? 'Record the current condition and maintenance performed for this unit.' : 'Completed, scheduled, and urgent service tasks across the fleet.' ?></p>
  </div>
  <div class="page-actions">
    <?php if ($inspectionMode): ?>
      <a class="btn btn-ghost" href="analytics.php">Back to Analytics</a>
    <?php else: ?><button class="btn btn-primary" data-modal-open="#modalMaint">
      <svg width="15" height="15" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
      Log Task
    </button><?php endif; ?>
  </div>
</div>

<?php if ($flash): ?>
  <div class="panel"><div class="panel-body" style="color:<?= $flash['type'] === 'success' ? 'var(--forest-600)' : 'var(--rust)' ?>;"><?= htmlspecialchars($flash['message']) ?></div></div>
<?php endif; ?>

<?php require __DIR__ . '/includes/inspection-popup.php'; ?>
<?php if (!$inspectionMode): ?>
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
    <span class="kpi-label">Needs Attention</span>
    <div class="kpi-value" style="color:var(--rust);"><?= $needsAttention ?></div>
    <div class="kpi-delta down">Require follow-up</div>
  </div>
  <div class="kpi-card">
    <span class="kpi-label">Units Inspected</span>
    <div class="kpi-value"><?= count($inspectedUnitsThisMonth) ?></div>
    <div class="kpi-delta up">This month</div>
  </div>
</div>
<?php endif; ?>

<?php if (!$inspectionMode): ?>
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
          <tr><th>Date</th><th>Unit</th><th>Task</th><th>Risk</th><th>Technician</th><th>Priority</th><th>Status</th></tr>
        </thead>
        <tbody>
          <?php foreach ($log as $m): ?>
            <tr data-searchable="<?= htmlspecialchars($m['unit'].' '.$m['name'].' '.$m['task'].' '.$m['tech'].' '.($m['priority'] ?? 'normal')) ?>" data-status="<?= $m['status'] ?>">
              <td class="mono"><?= date('M j, Y', strtotime($m['date'])) ?></td>
              <td><a href="unit-detail.php?id=<?= urlencode($m['unit']) ?>" class="strong"><?= $m['unit'] ?></a><br><span class="u-text-slate u-text-xs"><?= htmlspecialchars($m['name']) ?></span></td>
              <td><?= htmlspecialchars($m['task']) ?><?php if (!empty($m['inspection_report'])): $report = $m['inspection_report']; $conditionLabels = ['Resolved' => 'Good', 'Monitor' => 'Monitor', 'Follow-up required' => 'Needs Follow-up', 'Major repair required' => 'Needs Major Repair']; ?><details class="inspection-saved"><summary>View inspection report</summary><div class="inspection-saved-grid"><?php foreach (($report['readings'] ?? []) as $key => $value): ?><span><?= htmlspecialchars(ucwords(str_replace('_', ' ', $key))) ?></span><b><?= htmlspecialchars((string)$value) ?></b><?php endforeach; ?><span>Compressor</span><b><?= htmlspecialchars($report['compressor'] ?? '') ?></b><span>Filter</span><b><?= htmlspecialchars($report['filter'] ?? '') ?></b><span>Refrigerant</span><b><?= htmlspecialchars($report['refrigerant'] ?? '') ?></b><span>Work performed</span><b><?= htmlspecialchars(implode(', ', $report['work'] ?? [])) ?: 'None recorded' ?></b><span>After Maintenance</span><b><?= htmlspecialchars($conditionLabels[$report['final_condition'] ?? ''] ?? ($report['final_condition'] ?? '')) ?></b><?php if (!empty($report['findings'])): ?><span>Notes</span><b><?= nl2br(htmlspecialchars($report['findings'])) ?></b><?php endif; ?></div></details><?php endif; ?></td>
              <td><?= isset($m['risk']) && $m['risk'] !== null ? (int)$m['risk'] . '%' : '—' ?></td>
              <td><?= htmlspecialchars($m['tech']) ?></td>
              <td><span class="mstatus <?= htmlspecialchars($m['priority'] ?? 'normal') ?>"><?= ucfirst(htmlspecialchars($m['priority'] ?? 'normal')) ?></span></td>
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
      <h3><?= $prefillUnit ? 'Assign Service · ' . htmlspecialchars($prefillUnit) : 'Log Maintenance Task' ?></h3>
      <button class="modal-close" data-modal-close aria-label="Close">✕</button>
    </div>
    <form id="maintForm" method="post" action="maintenance.php">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_maintenance']) ?>">
      <?php if ($riskValue !== null): ?><input type="hidden" name="risk" value="<?= (int)$riskValue ?>"><?php endif; ?>
      <?php if ($prefillUnitData && empty($prefillUnitData['registered'])): ?>
        <div class="panel-body" style="padding:12px 0 4px">
          <div class="spec-row"><span>Maintenance risk</span><b><?= (int)$riskValue ?>%</b></div>
          <div class="spec-row"><span>Service due</span><b><?= (int)$prefillUnitData['rul_days'] ?> days</b></div>
          <div class="spec-row"><span>Recommended priority</span><b><?= ucfirst(htmlspecialchars($prefillPriority)) ?></b></div>
        </div>
      <?php elseif ($prefillUnitData): ?>
        <div class="panel-body" style="padding:12px 0 4px"><p>This verified customer unit is ready for service scheduling.</p></div>
      <?php endif; ?>
      <div class="field">
        <label for="fUnit">AC Unit</label>
        <select id="fUnit" name="unit" class="input" required>
          <?php foreach ($maintenanceUnits as $u): ?>
            <option value="<?= htmlspecialchars($u['id']) ?>" <?= $u['id'] === $prefillUnit ? 'selected' : '' ?>><?= htmlspecialchars($u['id']) ?> — <?= htmlspecialchars($u['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="fTask">Task Description</label>
        <input id="fTask" name="task" class="input" type="text" maxlength="255" value="<?= htmlspecialchars($prefillTask) ?>" placeholder="e.g. Refrigerant recharge" required>
      </div>
      <div class="field">
        <label for="fMaintenanceType">Maintenance Type</label>
        <select id="fMaintenanceType" name="maintenance_type" class="input" required>
          <?php foreach (['Cleaning', 'Preventive Maintenance', 'Repair', 'Inspection', 'Other'] as $type): ?><option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label for="fFault">Fault Reported</label><textarea id="fFault" name="fault_reported" class="input" maxlength="2000" placeholder="Describe the customer's reported issue"></textarea></div>
      <div class="field"><label for="fRepair">Repair Performed</label><textarea id="fRepair" name="repair_performed" class="input" maxlength="2000" placeholder="Describe the work completed"></textarea></div>
      <div class="field"><label for="fComponents">Replaced Components</label><input id="fComponents" name="replaced_components" class="input" type="text" maxlength="1000" placeholder="List components replaced, if any"></div>
      <div class="field"><label for="fRemarks">Remarks</label><textarea id="fRemarks" name="remarks" class="input" maxlength="2000" placeholder="Additional service notes"></textarea></div>
      <div class="field">
        <label for="fTech">Assign Technician</label>
        <select id="fTech" name="technician" class="input" required>
          <option value="" disabled selected>Select technician</option>
          <option value="Unassigned">Unassigned</option>
          <option>J. Ramos</option>
          <option>M. Santos</option>
        </select>
      </div>
      <div class="field">
        <label for="fPriority">Priority</label>
        <select id="fPriority" name="priority" class="input" required>
          <?php foreach (['urgent' => 'Urgent', 'high' => 'High', 'normal' => 'Normal', 'low' => 'Low'] as $value => $label): ?>
            <option value="<?= $value ?>" <?= $prefillPriority === $value ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="fDate">Scheduled Date</label>
        <input id="fDate" name="date" class="input" type="date" value="<?= htmlspecialchars($prefillDate) ?>" required>
      </div>
      <div class="field">
        <label for="fTime">Appointment Time <span class="u-text-slate">(optional)</span></label>
        <input id="fTime" name="time" class="input" type="time" value="">
      </div>
      <div class="field">
        <label for="fStatus">Status</label>
        <select id="fStatus" name="status" class="input" required>
          <option value="scheduled" <?= $prefillStatus === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
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

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
<?php require __DIR__ . '/includes/footer-end.php'; ?>
