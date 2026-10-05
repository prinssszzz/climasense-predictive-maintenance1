<?php
require_once __DIR__ . '/includes/auth.php';
cs_require_permission('units.view_own');
if (cs_primary_role_for_user() !== 'consumer') { header('Location: ' . cs_home_for_user()); exit; }
$user = cs_current_user();
cs_ensure_user_phone_column();
$contactQuery = cs_db()->prepare('SELECT phone FROM users WHERE id=? LIMIT 1');
$contactQuery->execute([$user['id']]);
$contactNumber = $contactQuery->fetchColumn();
cs_ensure_consumer_unit_schema();
$query = cs_db()->prepare('SELECT unit_code,name,verification_status FROM ac_units WHERE consumer_user_id=? ORDER BY id DESC');
$query->execute([$user['id']]);
$units = $query->fetchAll();
$unitNames = array_column(array_filter($units, fn($unit) => $unit['verification_status'] === 'active'), 'name', 'unit_code');
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
$pageTitle = 'Customer Dashboard';
$activeNav = 'dashboard';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head"><div><span class="eyebrow">Consumer Portal</span><h1>Hello, <?= htmlspecialchars(explode(' ', trim($user['name']))[0] ?: 'there') ?>!</h1><p>Here’s the latest maintenance information for your AC.</p></div><div class="page-actions"><a class="btn btn-primary" href="consumer-units.php">My AC Units</a></div></div>
<?php if (!$contactNumber): ?><div class="panel consumer-contact-reminder"><div class="panel-body"><strong>Add your contact number</strong><p>Your service provider needs a current phone number to arrange visits and send service updates.</p><a class="btn btn-primary btn-sm" href="profile.php">Complete your profile</a></div></div><?php endif; ?>
<div class="page-head"><div><h2>Top summary</h2></div></div>
<?php if ($maintenanceNotifications): ?><div class="panel"><div class="panel-body"><h2>Maintenance reminders</h2><?php foreach ($maintenanceNotifications as $notice): ?><p><strong><?= htmlspecialchars($notice['title']) ?></strong><br><?= htmlspecialchars($notice['message']) ?></p><?php endforeach; ?></div></div><?php endif; ?>
<div class="kpi-row consumer-summary">
  <div class="kpi-card"><span class="kpi-label">My AC Units</span><div class="kpi-value"><?= count($units) ?></div><div class="kpi-delta"><a href="consumer-units.php">View your registered units</a></div></div>
  <div class="kpi-card"><span class="kpi-label">Service Status</span><div class="kpi-value consumer-summary-status"><?= $urgentCount ? 'Awaiting contact' : ($activeCount ? 'Scheduled' : ($latestServiceDate ? 'Completed' : 'No recommendation')) ?></div><div class="kpi-delta"><?= $activeCount ? $activeCount . ' service' . ($activeCount === 1 ? '' : 's') . ' awaiting or arranged' : ($latestServiceDate ? 'Most recent work is complete' : 'No active recommendation recorded') ?></div></div>
  <div class="kpi-card"><span class="kpi-label">Last Service</span><div class="kpi-value consumer-summary-date"><?= $latestServiceDate ? htmlspecialchars(date('M j, Y', strtotime($latestServiceDate))) : '—' ?></div><div class="kpi-delta">Most recent completed service</div></div>
</div>
<div class="page-head"><div><span class="eyebrow">Service</span><h2>Maintenance Status</h2><p>Current service information for the AC units registered to your account.</p></div><div class="page-actions"><a class="btn btn-ghost" href="consumer-service-history.php">Service History</a></div></div>
<?php if (!$units): ?>
<div class="panel"><div class="panel-body"><p>You don’t have any AC units registered yet.</p><a class="btn btn-primary" href="consumer-units.php">Add an AC Unit</a></div></div>
<?php else: ?>
<div class="panel"><div class="panel-body"><div class="table-wrap"><table><thead><tr><th>Unit</th><th>Maintenance status</th><th>Service information</th></tr></thead><tbody>
<?php foreach ($units as $unit): $service = $unitService[$unit['unit_code']]; $active = $service['active']; $last = $service['last']; $maintenancePattern = $unit['verification_status'] === 'active' ? cs_maintenance_interval($service['records']) : null; $estimatedStatus = cs_maintenance_status($maintenancePattern['next_date'] ?? null); ?>
<tr><td><strong><?= htmlspecialchars($unit['unit_code']) ?></strong><br><span class="u-text-slate u-text-xs"><?= htmlspecialchars($unit['name']) ?></span></td><td><?= $unit['verification_status'] !== 'active' ? ($unit['verification_status'] === 'pending' ? 'Pending Verification' : 'Verification Rejected') : ($active ? ($active['status'] === 'scheduled' ? 'Maintenance Scheduled' : 'Maintenance Recommended') : $estimatedStatus['label']) ?></td><td><?php if ($unit['verification_status'] === 'pending'): ?>Your AC unit is awaiting verification by your service provider.<?php elseif ($unit['verification_status'] === 'rejected'): ?>Contact your service provider about this registration.<?php elseif ($active): ?><?= htmlspecialchars($active['task']) ?><?php if ($active['status'] === 'scheduled'): ?> · <?= htmlspecialchars(date('M j, Y', strtotime($active['date']))) ?><?php else: ?> · Your provider will contact you<?php endif; ?><?php elseif ($maintenancePattern && in_array($estimatedStatus['key'], ['recommended', 'due'], true)): ?>Based on your previous service visits, your AC is approaching its typical maintenance interval. Your service provider will contact you to arrange a visit.<?php elseif ($last): ?><?= htmlspecialchars($last['task']) ?> · <?= htmlspecialchars(date('M j, Y', strtotime($last['date']))) ?><?php else: ?>No active recommendation is recorded.<?php endif; ?><?php if ($maintenancePattern && $maintenancePattern['average_days'] !== null): ?><br><span class="u-text-slate u-text-xs">Typical interval: <?= (int)$maintenancePattern['average_days'] ?> days · Estimated next: <?= htmlspecialchars(date('M j, Y', strtotime($maintenancePattern['next_date']))) ?></span><?php endif; ?></td></tr>
<?php endforeach; ?>
</tbody></table></div></div></div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; require __DIR__ . '/includes/footer-end.php'; ?>
