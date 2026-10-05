<?php
require_once __DIR__ . '/includes/auth.php';
cs_require_permission('units.view_own');
$user = cs_current_user();
cs_ensure_consumer_unit_schema();
$pageTitle = 'Service History';
$activeNav = 'service-history';
$query = cs_db()->prepare("SELECT unit_code,name FROM ac_units WHERE consumer_user_id=? AND verification_status='active'");
$query->execute([$user['id']]);
$units = $query->fetchAll();
$unitNames = array_column($units, 'name', 'unit_code');
$selectedUnit = trim((string)($_GET['unit'] ?? ''));
if ($selectedUnit !== '' && !array_key_exists($selectedUnit, $unitNames)) { http_response_code(404); exit('Unit not found.'); }
require_once __DIR__ . '/includes/data.php';
$history = array_values(array_filter(cs_maintenance_log(), fn($entry) => isset($unitNames[$entry['unit']]) && ($selectedUnit === '' || $entry['unit'] === $selectedUnit)));
usort($history, fn($a, $b) => strcmp($b['date'], $a['date']));
require __DIR__ . '/includes/header.php';
?>
<div class="page-head"><div><span class="eyebrow">Service</span><h1><?= $selectedUnit ? 'Service History · ' . htmlspecialchars($selectedUnit) : 'Service History' ?></h1><p>Cleaning, maintenance, repairs, and other service recorded for your AC units.</p></div><?php if ($selectedUnit): ?><div class="page-actions"><a class="btn btn-ghost" href="consumer-service-history.php">All units</a></div><?php endif; ?></div>
<div class="panel"><div class="panel-body">
<?php if (!$history): ?><p>No service records are available for your registered units yet.</p><?php else: ?><div class="table-wrap"><table><thead><tr><th>Date</th><th>Unit</th><th>Service</th><th>Technician</th><th>Status</th></tr></thead><tbody><?php foreach ($history as $entry): $report = $entry['inspection_report'] ?? []; $details = $report['service_details'] ?? []; ?><tr><td><?= htmlspecialchars(date('M j, Y', strtotime($entry['date']))) ?></td><td><strong><?= htmlspecialchars($entry['unit']) ?></strong><br><span class="u-text-slate u-text-xs"><?= htmlspecialchars($unitNames[$entry['unit']] ?? $entry['name']) ?></span></td><td><?= htmlspecialchars($entry['task']) ?><?php if (!empty($details['maintenance_type'])): ?><br><span class="u-text-slate u-text-xs">Type: <?= htmlspecialchars($details['maintenance_type']) ?></span><?php endif; ?><?php foreach (['fault_reported' => 'Fault reported', 'repair_performed' => 'Repair performed', 'replaced_components' => 'Replaced components', 'remarks' => 'Remarks'] as $field => $label): if (!empty($details[$field])): ?><br><span class="u-text-slate u-text-xs"><?= $label ?>: <?= nl2br(htmlspecialchars($details[$field])) ?></span><?php endif; endforeach; ?><?php if (!empty($report['work'])): ?><br><span class="u-text-slate u-text-xs">Work performed: <?= htmlspecialchars(implode(', ', $report['work'])) ?></span><?php endif; ?><?php if (!empty($report['findings'])): ?><br><span class="u-text-slate u-text-xs">Notes: <?= htmlspecialchars($report['findings']) ?></span><?php endif; ?></td><td><?= htmlspecialchars($entry['tech']) ?></td><td><?= htmlspecialchars(ucfirst($entry['status'])) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</div></div>
<?php require __DIR__ . '/includes/footer.php'; require __DIR__ . '/includes/footer-end.php'; ?>
