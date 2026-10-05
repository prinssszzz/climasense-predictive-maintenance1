<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/data.php';
cs_require_permission('units.view_own');
if (cs_primary_role_for_user() !== 'consumer') { header('Location: ' . cs_home_for_user()); exit; }
cs_ensure_consumer_unit_schema();
$user = cs_current_user();
cs_ensure_user_phone_column();
$contactQuery = cs_db()->prepare('SELECT phone FROM users WHERE id=? LIMIT 1');
$contactQuery->execute([$user['id']]);
$contactNumber = $contactQuery->fetchColumn();
$pageTitle = 'My AC Units';
$activeNav = 'units';
$db = cs_db();
$addUnitError = null;
$addUnitValues = ['brand' => '', 'model' => '', 'capacity' => '', 'location' => '', 'installed_on' => ''];
$capacities = ['0.5 HP', '0.75 HP', '1 HP', '1.5 HP', '2 HP', '2.5 HP', '3 HP'];
$shopQuery = $user['organization_id']
    ? $db->prepare('SELECT id FROM organizations WHERE id=? LIMIT 1')
    : $db->query('SELECT id FROM organizations ORDER BY id LIMIT 2');
if ($user['organization_id']) $shopQuery->execute([$user['organization_id']]);
$linkedShops = $shopQuery->fetchAll();
$submissionOrganizationId = count($linkedShops) === 1 ? (int)$linkedShops[0]['id'] : null;
if (empty($_SESSION['cs_consumer_unit_csrf'])) $_SESSION['cs_consumer_unit_csrf'] = bin2hex(random_bytes(32));
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_consumer_unit'])) {
    foreach ($addUnitValues as $field => $_) $addUnitValues[$field] = trim((string)($_POST[$field] ?? ''));
    $token = (string)($_POST['csrf_token'] ?? '');
    $installedDate = DateTime::createFromFormat('!Y-m-d', $addUnitValues['installed_on']);
    if (!hash_equals($_SESSION['cs_consumer_unit_csrf'], $token)) {
        $addUnitError = 'Your session expired. Refresh the page and try again.';
    } elseif (!$submissionOrganizationId || $addUnitValues['brand'] === '' || strlen($addUnitValues['brand']) > 80 || $addUnitValues['model'] === '' || strlen($addUnitValues['model']) > 150 || !in_array($addUnitValues['capacity'], $capacities, true) || $addUnitValues['location'] === '' || strlen($addUnitValues['location']) > 180 || !$installedDate || $installedDate->format('Y-m-d') !== $addUnitValues['installed_on'] || $addUnitValues['installed_on'] > date('Y-m-d')) {
        $addUnitError = $submissionOrganizationId ? 'Enter a brand, model, valid capacity, location, and installation date that is today or earlier.' : 'Your account is not linked to a service provider yet. Contact your shop to connect your account.';
    } else {
        $unitCodeLock = false;
        try {
            $unitCodeLock = (int)$db->query("SELECT GET_LOCK('climasense_consumer_unit_code', 5)")->fetchColumn() === 1;
            if (!$unitCodeLock) throw new RuntimeException('Could not reserve a unit ID.');
            $db->beginTransaction();
            $maxCode = (int)$db->query("SELECT COALESCE(MAX(CAST(SUBSTRING(unit_code, 4) AS UNSIGNED)), 0) FROM ac_units WHERE unit_code REGEXP '^AC-[0-9]+$'")->fetchColumn();
            do {
                $maxCode++;
                $unitCode = 'AC-' . str_pad((string)$maxCode, 3, '0', STR_PAD_LEFT);
            } while (cs_unit($unitCode));
            $unitName = $addUnitValues['brand'] . ' ' . $addUnitValues['model'];
            $insert = $db->prepare('INSERT INTO ac_units (organization_id,consumer_user_id,unit_code,name,brand,ac_type,location,model,capacity,installed_on,verification_status) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
            $insert->execute([$submissionOrganizationId, $user['id'], $unitCode, $unitName, $addUnitValues['brand'], 'Split-Type', $addUnitValues['location'], $addUnitValues['model'], $addUnitValues['capacity'], $addUnitValues['installed_on'], 'pending']);
            $db->commit();
            $_SESSION['cs_consumer_unit_flash'] = 'AC Unit Submitted. ' . $unitCode . ' is awaiting verification by your service provider.';
            $db->query("SELECT RELEASE_LOCK('climasense_consumer_unit_code')");
            $unitCodeLock = false;
            header('Location: consumer-units.php#my-units');
            exit;
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            $addUnitError = 'The unit could not be registered. Please review the details and try again.';
        } finally {
            if ($unitCodeLock) $db->query("SELECT RELEASE_LOCK('climasense_consumer_unit_code')");
        }
    }
}
$unitFlash = $_SESSION['cs_consumer_unit_flash'] ?? null;
unset($_SESSION['cs_consumer_unit_flash']);
$query = $db->prepare('SELECT unit_code,name,brand,ac_type,location,model,capacity,installed_on,verification_status FROM ac_units WHERE consumer_user_id=? ORDER BY id DESC');
$query->execute([$user['id']]);
$units = $query->fetchAll();
$activeUnits = array_filter($units, fn($unit) => $unit['verification_status'] === 'active');
$unitNames = array_column($activeUnits, 'name', 'unit_code');
require_once __DIR__ . '/includes/data.php';
$serviceLog = array_values(array_filter(cs_maintenance_log(), fn($entry) => isset($unitNames[$entry['unit']])));
usort($serviceLog, fn($a, $b) => strcmp($b['date'], $a['date']));
$unitService = [];
foreach ($units as $unit) {
    $records = array_values(array_filter($serviceLog, fn($entry) => $entry['unit'] === $unit['unit_code']));
    $active = array_values(array_filter($records, fn($entry) => in_array($entry['status'], ['scheduled', 'urgent'], true)));
    usort($active, fn($a, $b) => strcmp($a['date'], $b['date']));
    $completed = array_values(array_filter($records, fn($entry) => $entry['status'] === 'completed'));
    $unitService[$unit['unit_code']] = ['active' => $active[0] ?? null, 'last' => $completed[0] ?? null, 'records' => $records, 'verification_status' => $unit['verification_status']];
}
$maintenanceNotifications = cs_sync_consumer_maintenance_notifications((int)$user['id'], $unitService);
$activeCount = count(array_filter($unitService, fn($service) => $service['active'] !== null));
$urgentCount = count(array_filter($unitService, fn($service) => ($service['active']['status'] ?? '') === 'urgent'));
$latestServiceDate = null;
foreach ($unitService as $service) {
    if ($service['last'] && (!$latestServiceDate || $service['last']['date'] > $latestServiceDate)) $latestServiceDate = $service['last']['date'];
}
require __DIR__ . '/includes/header.php';
?>
<div class="page-head" id="my-units"><div><span class="eyebrow">Monitor</span><h1>My AC Units</h1><p>View and manage your registered air-conditioning units.</p></div><div class="page-actions"><button type="button" class="btn btn-primary" data-modal-open="#modalAddConsumerUnit">＋ Add AC Unit</button><a class="btn btn-ghost" href="consumer-service-history.php">Service History</a></div></div>
<?php if (!$submissionOrganizationId): ?><div class="panel"><div class="panel-body">Your account is not linked to a service provider yet. Contact your shop to connect your account before submitting an AC unit.</div></div><?php endif; ?>
<?php if (!$contactNumber): ?><div class="panel consumer-contact-reminder"><div class="panel-body"><strong>Add your contact number</strong><p>Your service provider needs a current phone number to arrange visits and send service updates.</p><a class="btn btn-primary btn-sm" href="profile.php">Complete your profile</a></div></div><?php endif; ?>
<?php if ($unitFlash): ?><div class="panel"><div class="panel-body" role="status" style="color:var(--forest-600)"><?= htmlspecialchars($unitFlash) ?></div></div><?php endif; ?>
<?php if ($addUnitError): ?><div class="panel"><div class="panel-body" role="alert" style="color:var(--rust)"><?= htmlspecialchars($addUnitError) ?></div></div><?php endif; ?>
<?php if (!$units): ?>
<div class="panel"><div class="panel-body"><p>You don’t have any AC units linked yet. Add your unit to get started.</p><button type="button" class="btn btn-primary" data-modal-open="#modalAddConsumerUnit">＋ Add AC Unit</button></div></div>
<?php else: foreach ($units as $unit): $service = $unitService[$unit['unit_code']]; $active = $service['active']; $last = $service['last']; $historyRecords = array_slice($service['records'], 0, 3); $displayModel = trim(implode(' ', array_filter([$unit['brand'] ?? '', $unit['model'] ?? '', $unit['capacity'] ?? '']))); if ($displayModel === '') $displayModel = $unit['name']; $verificationStatus = $unit['verification_status']; $maintenancePattern = $verificationStatus === 'active' ? cs_maintenance_interval($service['records']) : null; $estimatedStatus = cs_maintenance_status($maintenancePattern['next_date'] ?? null); $statusDot = $verificationStatus === 'pending' ? 'pending-verification' : ($verificationStatus === 'rejected' ? 'rejected' : ($active ? ($active['status'] === 'urgent' ? 'urgent' : 'scheduled') : ($maintenancePattern['average_days'] !== null ? ($estimatedStatus['key'] === 'due' ? 'due' : ($estimatedStatus['key'] === 'recommended' ? 'urgent' : 'no-recommendation')) : ($last ? 'complete' : 'no-recommendation')))); $statusLabel = $verificationStatus === 'pending' ? 'Pending Verification' : ($verificationStatus === 'rejected' ? 'Verification Rejected' : ($active ? ($active['status'] === 'urgent' ? 'Maintenance Recommended' : 'Maintenance Scheduled') : ($maintenancePattern['average_days'] !== null ? $estimatedStatus['label'] : ($last ? 'Maintenance Completed' : 'No Maintenance Needed')))); ?>
<article class="panel consumer-maintenance-card"><div class="panel-body">
  <span class="eyebrow"><?= htmlspecialchars($unit['unit_code']) ?></span>
  <h3><?= htmlspecialchars($displayModel) ?></h3>
  <p><?= htmlspecialchars($unit['location']) ?> · <?= htmlspecialchars($unit['ac_type'] ?: 'Split-Type') ?></p>
  <div class="consumer-maintenance-state"><span class="consumer-status-dot <?= htmlspecialchars($statusDot) ?>"></span><strong><?= htmlspecialchars($statusLabel) ?></strong></div>
  <?php if ($verificationStatus === 'pending'): ?>
    <p><strong>AC Unit Submitted</strong></p>
    <p class="consumer-maintenance-copy">Your AC unit has been submitted and is awaiting verification by your service provider.</p>
  <?php elseif ($verificationStatus === 'rejected'): ?>
    <p class="consumer-maintenance-copy">Your service provider could not verify this AC unit. Please contact the shop for help with the registration.</p>
  <?php elseif ($active): ?>
    <?php if ($active['status'] === 'scheduled'): ?>
      <p><strong><?= htmlspecialchars($active['unit']) ?> — <?= htmlspecialchars($displayModel) ?></strong></p>
      <p><strong>Date:</strong> <?= htmlspecialchars(date('F j, Y', strtotime($active['date']))) ?></p>
      <?php if (!empty($active['time'])): ?><p><strong>Time:</strong> <?= htmlspecialchars(date('g:i A', strtotime($active['time']))) ?></p><?php endif; ?>
      <p><strong>Service:</strong> <?= htmlspecialchars($active['task']) ?></p>
      <p class="consumer-maintenance-copy">Your service provider has scheduled maintenance for your AC.</p>
    <?php else: ?>
      <p><strong>Recommended service:</strong> <?= htmlspecialchars($active['task']) ?></p>
      <p><strong>Status:</strong> Awaiting service arrangement</p>
      <p class="consumer-maintenance-copy">Maintenance has been recommended for your AC. Your service provider will contact you regarding the maintenance schedule.</p>
    <?php endif; ?>
  <?php elseif ($maintenancePattern['average_days'] !== null && in_array($estimatedStatus['key'], ['recommended', 'due'], true)): ?>
    <p><strong><?= htmlspecialchars($unit['unit_code']) ?> — <?= htmlspecialchars($displayModel) ?></strong></p>
    <p><strong>Recommended date:</strong> <?= htmlspecialchars(date('F j, Y', strtotime($maintenancePattern['next_date']))) ?></p>
    <p class="consumer-maintenance-copy">Based on your previous maintenance visits, your AC is approaching its typical maintenance interval.</p>
    <p class="consumer-maintenance-copy">Your service provider will contact you to arrange a suitable visit.</p>
  <?php elseif ($last): $report = $last['inspection_report'] ?? []; $workPerformed = array_values(array_filter((array)($report['work'] ?? []), 'is_string')); ?>
    <p><strong><?= htmlspecialchars($unit['unit_code']) ?> — <?= htmlspecialchars($displayModel) ?></strong></p>
    <p><strong>Service completed:</strong> <?= htmlspecialchars(date('F j, Y', strtotime($last['date']))) ?></p>
    <p><strong>Service performed:</strong> <?= htmlspecialchars($workPerformed ? implode(', ', $workPerformed) : $last['task']) ?></p>
    <?php if (!empty($report['final_condition'])): ?><p><strong>Result:</strong> <?= htmlspecialchars($report['final_condition']) ?></p><?php endif; ?>
    <details class="consumer-service-report"><summary>View Service Report →</summary>
      <div class="consumer-service-report-body">
        <p><strong>Service:</strong> <?= htmlspecialchars($last['task']) ?></p>
        <p><strong>Completed:</strong> <?= htmlspecialchars(date('F j, Y', strtotime($last['date']))) ?></p>
        <?php if (!empty($last['tech'])): ?><p><strong>Technician:</strong> <?= htmlspecialchars($last['tech']) ?></p><?php endif; ?>
        <?php if ($workPerformed): ?><p><strong>Work performed:</strong> <?= htmlspecialchars(implode(', ', $workPerformed)) ?></p><?php endif; ?>
        <?php foreach (['compressor' => 'Compressor', 'filter' => 'Filter', 'refrigerant' => 'Refrigerant', 'final_condition' => 'Result'] as $field => $label): if (!empty($report[$field])): ?><p><strong><?= $label ?>:</strong> <?= htmlspecialchars($report[$field]) ?></p><?php endif; endforeach; ?>
        <?php if (!empty($report['findings'])): ?><p><strong>Service notes:</strong> <?= nl2br(htmlspecialchars($report['findings'])) ?></p><?php endif; ?>
        <?php $details = $report['service_details'] ?? []; foreach (['maintenance_type' => 'Maintenance type', 'fault_reported' => 'Fault reported', 'repair_performed' => 'Repair performed', 'replaced_components' => 'Replaced components', 'remarks' => 'Remarks'] as $field => $label): if (!empty($details[$field])): ?><p><strong><?= $label ?>:</strong> <?= nl2br(htmlspecialchars($details[$field])) ?></p><?php endif; endforeach; ?>
      </div>
    </details>
    <p class="consumer-maintenance-copy">Your AC maintenance has been completed.</p>
  <?php else: ?>
    <p class="consumer-maintenance-copy">No active maintenance recommendation is recorded for this AC based on the available service records.</p>
  <?php endif; ?>
  <?php if ($verificationStatus === 'active'): ?><div class="consumer-card-history">
    <?php if ($maintenancePattern['average_days'] !== null): ?><p class="consumer-maintenance-copy"><strong>Estimated maintenance date:</strong> <?= htmlspecialchars(date('F j, Y', strtotime($maintenancePattern['next_date']))) ?><br><strong>Typical maintenance interval:</strong> <?= (int)$maintenancePattern['average_days'] ?> days</p><?php elseif (count(array_filter($service['records'], fn($record) => $record['status'] === 'completed')) < 2): ?><p class="consumer-history-empty">We’ll estimate your maintenance interval after more completed service visits.</p><?php endif; ?>
    <h4>Maintenance History</h4>
    <?php if (!$historyRecords): ?><p class="consumer-history-empty">No maintenance records are available yet.</p>
    <?php else: ?><ul class="consumer-history-list"><?php foreach ($historyRecords as $record): ?>
      <li><span class="consumer-history-date"><?= htmlspecialchars(date('M j, Y', strtotime($record['date']))) ?></span><span class="consumer-history-task"><?= htmlspecialchars($record['task']) ?></span><span class="consumer-history-status <?= htmlspecialchars($record['status']) ?>"><?= htmlspecialchars(ucfirst($record['status'] === 'urgent' ? 'recommended' : $record['status'])) ?></span></li>
    <?php endforeach; ?></ul><?php endif; ?>
    <a class="consumer-full-history" href="consumer-service-history.php?unit=<?= urlencode($unit['unit_code']) ?>">View Full History →</a>
  </div><?php else: ?><p class="consumer-history-empty">Maintenance records will be available after verification.</p><?php endif; ?>
</div></article>
<?php endforeach; endif; ?>
<div class="modal-backdrop<?= $addUnitError ? ' open' : '' ?>" id="modalAddConsumerUnit" aria-hidden="<?= $addUnitError ? 'false' : 'true' ?>">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="addConsumerUnitTitle">
    <div class="modal-head"><h3 id="addConsumerUnitTitle">Add AC Unit</h3><button type="button" class="modal-close" data-modal-close aria-label="Close">×</button></div>
    <form method="post" action="consumer-units.php#my-units">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['cs_consumer_unit_csrf']) ?>">
      <input type="hidden" name="add_consumer_unit" value="1">
      <div class="field"><label for="consumerUnitBrand">Brand</label><input class="input" id="consumerUnitBrand" name="brand" type="text" maxlength="80" placeholder="e.g. Carrier" required value="<?= htmlspecialchars($addUnitValues['brand']) ?>"></div>
      <div class="field"><label for="consumerUnitModel">Model</label><input class="input" id="consumerUnitModel" name="model" type="text" maxlength="150" placeholder="e.g. Inverter" required value="<?= htmlspecialchars($addUnitValues['model']) ?>"></div>
      <div class="field"><label for="consumerUnitType">AC Type</label><input class="input" id="consumerUnitType" type="text" value="Split-Type" readonly><small class="profile-help">Split-type is the supported AC type.</small></div>
      <div class="field"><label for="consumerUnitCapacity">Capacity</label><select class="input" id="consumerUnitCapacity" name="capacity" required><option value="">Select capacity</option><?php foreach ($capacities as $capacity): ?><option value="<?= htmlspecialchars($capacity) ?>" <?= $addUnitValues['capacity'] === $capacity ? 'selected' : '' ?>><?= htmlspecialchars($capacity) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="consumerUnitLocation">Location</label><input class="input" id="consumerUnitLocation" name="location" type="text" maxlength="180" placeholder="e.g. Living Room" required value="<?= htmlspecialchars($addUnitValues['location']) ?>"></div>
      <div class="field"><label for="consumerUnitInstalled">Installation Date</label><input class="input" id="consumerUnitInstalled" name="installed_on" type="date" max="<?= date('Y-m-d') ?>" required value="<?= htmlspecialchars($addUnitValues['installed_on']) ?>"></div>
      <div class="modal-actions"><button type="button" class="btn btn-ghost" data-modal-close>Cancel</button><button type="submit" class="btn btn-primary">Add AC Unit</button></div>
    </form>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; require __DIR__ . '/includes/footer-end.php'; ?>
