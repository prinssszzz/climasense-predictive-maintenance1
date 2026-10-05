<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/data.php';
cs_require_login();
if (cs_has_role('super_admin')) {
    header('Location: super-admin-dashboard.php');
    exit;
}
if (!cs_has_role('client_admin') && !cs_has_role('admin_level_1') && !cs_has_role('admin_level_2')) {
    http_response_code(403);
    exit('You do not have permission to view this page.');
}
cs_require_permission('analytics.view');
$pageTitle = 'Admin Dashboard';
$activeNav = 'dashboard';
require __DIR__ . '/includes/header.php';
$u = cs_current_user();
$db = cs_db();
$orgId = $u['organization_id'] ?? null;
// These cards use the same fleet, predictive alert, and maintenance-log sources
// as Units, Alerts, and Maintenance. The CSV fleet is shared demo data until
// unit ownership is linked to organizations in the live database.
$fleetUnits = cs_units();
$units = count($fleetUnits);
$alerts = count(array_filter(cs_predictive_alerts((int)date('Y'), max(1, $units)), fn($alert) => in_array($alert['severity'], ['critical', 'warning'], true)));
$tasks = count(array_filter(cs_maintenance_log(), fn($task) => $task['status'] !== 'completed'));
if ($orgId) {
    $countByOrg = $db->prepare("SELECT COUNT(DISTINCT u.id) FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id WHERE r.code = 'consumer' AND u.organization_id = ?");
    $countByOrg->execute([$orgId]);
    $consumers = $countByOrg->fetchColumn();
} else {
    $consumers = 0;
}
?>
<div class="page-head">
  <div>
    <span class="eyebrow">Operations Dashboard</span>
    <h1>Good afternoon, <?= htmlspecialchars($u['name']) ?>.</h1>
    <p>Monitor service performance, maintenance tasks, and equipment health for your organization.</p>
  </div>
  <div class="page-actions">
    <button class="btn btn-ghost" type="button" onclick="window.location.href='units.php'">View Units</button>
    <button class="btn btn-primary" type="button" onclick="window.location.href='maintenance.php'">Create Task</button>
  </div>
</div>

<div class="kpi-row">
  <div class="kpi-card">
    <span class="kpi-icon"><svg width="17" height="17" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="10" rx="2" stroke="currentColor" stroke-width="2" fill="none"/><path d="M7 19h10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span>
    <span class="kpi-label">Units Managed</span>
    <div class="kpi-value"><?= (int)$units ?> <small>units</small></div>
    <div class="kpi-delta up">Monitored fleet</div>
  </div>
  <div class="kpi-card">
    <span class="kpi-icon"><svg width="17" height="17" viewBox="0 0 24 24"><path d="M12 3 2 20h20L12 3Z" stroke="currentColor" stroke-width="2" fill="none" stroke-linejoin="round"/><path d="M12 10v4M12 17h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span>
    <span class="kpi-label">Active Alerts</span>
    <div class="kpi-value"><?= (int)$alerts ?> <small>open</small></div>
    <div class="kpi-delta down">Predictive risks · <?= (int)date('Y') ?></div>
  </div>
  <div class="kpi-card">
    <span class="kpi-icon" style="color:var(--amber);background:var(--amber-bg)"><svg width="17" height="17" viewBox="0 0 24 24"><path d="M4 12h16M12 4v16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="12" cy="12" r="8" stroke="currentColor" stroke-width="2" fill="none"/></svg></span>
    <span class="kpi-label">Open Tasks</span>
    <div class="kpi-value"><?= (int)$tasks ?> <small>jobs</small></div>
    <div class="kpi-delta up">Scheduled or urgent</div>
  </div>
  <div class="kpi-card">
    <span class="kpi-icon" style="color:var(--forest);background:var(--mint-50)"><svg width="17" height="17" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" stroke="currentColor" stroke-width="2" fill="none"/><circle cx="10" cy="7" r="4" stroke="currentColor" stroke-width="2" fill="none"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" stroke="currentColor" stroke-width="2" fill="none" /></svg></span>
    <span class="kpi-label">Consumers</span>
    <div class="kpi-value"><?= (int)$consumers ?> <small>users</small></div>
    <div class="kpi-delta up">Connected clients</div>
  </div>
</div>

<div class="grid-2">
  <div class="panel">
    <div class="panel-head">
      <div>
        <h3>Maintenance Overview</h3>
        <div class="sub">Service backlog and response health</div>
      </div>
    </div>
    <div class="panel-body u-col u-gap-3">
      <button type="button" class="btn btn-primary btn-block" onclick="window.location.href='maintenance.php'">Open Maintenance Log</button>
      <button type="button" class="btn btn-ghost btn-block" onclick="window.location.href='alerts.php'">View Alerts</button>
      <button type="button" class="btn btn-ghost btn-block" onclick="window.location.href='units.php'">Review Units</button>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head">
      <div>
        <h3>Quick Access</h3>
        <div class="sub">Operational shortcuts</div>
      </div>
    </div>
    <div class="panel-body u-col u-gap-3">
      <button type="button" class="btn btn-primary btn-block" onclick="window.location.href='analytics.php'">Predictive Analytics</button>
      <button type="button" class="btn btn-ghost btn-block" onclick="window.location.href='units.php'">Manage Shop Units</button>
      <button type="button" class="btn btn-ghost btn-block" onclick="window.location.href='logout.php'">Log Out</button>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; require __DIR__ . '/includes/footer-end.php'; ?>
