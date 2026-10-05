<?php
require_once __DIR__ . '/includes/auth.php';
cs_require_login();
if (!in_array(cs_primary_role_for_user(), ['client_admin', 'admin_level_1', 'admin_level_2'], true)) {
    http_response_code(403);
    exit('Only shop administrators can verify consumer unit registrations.');
}
cs_require_permission('units.manage');
$user = cs_current_user();
$organizationId = (int)($user['organization_id'] ?? 0);
if (!$organizationId) { http_response_code(403); exit('This administrator account is not linked to a service shop.'); }
cs_ensure_user_phone_column();
cs_ensure_consumer_unit_schema();
$db = cs_db();
$pageTitle = 'AC Unit Registrations';
$activeNav = 'unit-registrations';
$error = null;
if (empty($_SESSION['cs_unit_review_csrf'])) $_SESSION['cs_unit_review_csrf'] = bin2hex(random_bytes(32));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf_token'] ?? '');
    $unitId = filter_input(INPUT_POST, 'unit_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $decision = (string)($_POST['decision'] ?? '');
    if (!hash_equals($_SESSION['cs_unit_review_csrf'], $token)) {
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif (!$unitId || !in_array($decision, ['approve', 'reject'], true)) {
        $error = 'Choose a valid registration and action.';
    } else {
        $newStatus = $decision === 'approve' ? 'active' : 'rejected';
        $update = $db->prepare("UPDATE ac_units SET verification_status=? WHERE id=? AND organization_id=? AND verification_status='pending'");
        $update->execute([$newStatus, $unitId, $organizationId]);
        if ($update->rowCount() === 1) {
            $_SESSION['cs_unit_review_flash'] = $decision === 'approve' ? 'AC unit verified and activated.' : 'AC unit registration rejected.';
            header('Location: unit-registrations.php');
            exit;
        }
        $error = 'That registration is no longer pending or is not part of your shop.';
    }
}
$flash = $_SESSION['cs_unit_review_flash'] ?? null;
unset($_SESSION['cs_unit_review_flash']);
$query = $db->prepare("SELECT a.id,a.unit_code,a.brand,a.model,a.ac_type,a.capacity,a.location,a.installed_on,a.created_at,u.name AS customer_name,u.email AS customer_email,u.phone AS customer_phone FROM ac_units a JOIN users u ON u.id=a.consumer_user_id WHERE a.organization_id=? AND a.verification_status='pending' ORDER BY a.created_at ASC");
$query->execute([$organizationId]);
$registrations = $query->fetchAll();
$activeQuery = $db->prepare("SELECT unit_code,name,brand,model,capacity,location FROM ac_units WHERE organization_id=? AND consumer_user_id IS NOT NULL AND verification_status='active' ORDER BY id DESC");
$activeQuery->execute([$organizationId]);
$verifiedUnits = $activeQuery->fetchAll();
require __DIR__ . '/includes/header.php';
?>
<div class="page-head"><div><span class="eyebrow">Shop Administration</span><h1>AC Unit Registrations</h1><p>Verify customer-submitted AC details before activating units in your maintenance records.</p></div></div>
<?php if ($error): ?><div class="panel"><div class="panel-body" role="alert" style="color:var(--rust)"><?= htmlspecialchars($error) ?></div></div><?php endif; ?>
<?php if ($flash): ?><div class="panel"><div class="panel-body" role="status" style="color:var(--forest-600)"><?= htmlspecialchars($flash) ?></div></div><?php endif; ?>
<?php if (!$registrations): ?><div class="panel"><div class="panel-body">There are no AC unit registrations waiting for verification.</div></div>
<?php else: foreach ($registrations as $registration): ?>
<article class="panel unit-registration-card"><div class="panel-body">
  <div class="unit-registration-heading"><div><span class="eyebrow"><?= htmlspecialchars($registration['unit_code']) ?></span><h2><?= htmlspecialchars(trim(($registration['brand'] ?? '') . ' ' . ($registration['model'] ?? '') . ' ' . ($registration['capacity'] ?? ''))) ?></h2></div><span class="unit-registration-status">● Pending Verification</span></div>
  <div class="unit-registration-grid"><div><span>Customer</span><b><?= htmlspecialchars($registration['customer_name']) ?></b></div><div><span>Email</span><b><?= htmlspecialchars($registration['customer_email']) ?></b></div><div><span>Contact</span><b><?= htmlspecialchars($registration['customer_phone'] ?: 'Not provided') ?></b></div><div><span>AC Type</span><b><?= htmlspecialchars($registration['ac_type']) ?></b></div><div><span>Location</span><b><?= htmlspecialchars($registration['location']) ?></b></div><div><span>Installation Date</span><b><?= $registration['installed_on'] ? htmlspecialchars(date('M j, Y', strtotime($registration['installed_on']))) : 'Not provided' ?></b></div><div><span>Submitted</span><b><?= htmlspecialchars(date('M j, Y', strtotime($registration['created_at']))) ?></b></div></div>
  <form method="post" action="unit-registrations.php" class="unit-registration-actions"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['cs_unit_review_csrf']) ?>"><input type="hidden" name="unit_id" value="<?= (int)$registration['id'] ?>"><button class="btn btn-ghost" type="submit" name="decision" value="reject">Reject</button><button class="btn btn-primary" type="submit" name="decision" value="approve">Approve &amp; Activate</button></form>
</div></article>
<?php endforeach; endif; ?>
<div class="page-head"><div><span class="eyebrow">Approved</span><h2>Verified Customer Units</h2><p>Approved units are available for service scheduling.</p></div></div>
<?php if (!$verifiedUnits): ?><div class="panel"><div class="panel-body">No verified customer units yet.</div></div><?php else: foreach ($verifiedUnits as $unit): ?><div class="panel unit-registration-card"><div class="panel-body unit-registration-heading"><div><span class="eyebrow"><?= htmlspecialchars($unit['unit_code']) ?> · Active</span><h2><?= htmlspecialchars(trim(($unit['brand'] ?? '') . ' ' . ($unit['model'] ?? '') . ' ' . ($unit['capacity'] ?? ''))) ?></h2><p><?= htmlspecialchars($unit['name']) ?> · <?= htmlspecialchars($unit['location']) ?></p></div><a class="btn btn-primary" href="maintenance.php?unit=<?= urlencode($unit['unit_code']) ?>">Schedule Maintenance</a></div></div><?php endforeach; endif; ?>
<?php require __DIR__ . '/includes/footer.php'; require __DIR__ . '/includes/footer-end.php'; ?>
