<?php
require_once __DIR__ . '/includes/auth.php'; cs_require_role('consumer');
$pageTitle='My Air Conditioner'; $activeNav='units'; require __DIR__ . '/includes/header.php';
$u=cs_current_user();
$q=cs_db()->prepare('SELECT unit_code,name,location,model FROM ac_units WHERE consumer_user_id=? ORDER BY id DESC'); $q->execute([$u['id']]); $units=$q->fetchAll();
?>
<div class="page-head"><div><span class="eyebrow">Consumer Portal</span><h1>My air-conditioning units</h1><p>View the health, alerts, and upcoming maintenance for your registered units.</p></div></div>
<div class="panel"><div class="panel-body"><?php if (!$units): ?><p>No unit has been assigned to your account yet. Ask your service shop to link your air-conditioner to <?= htmlspecialchars($u['email']) ?>.</p><?php else: ?><div class="unit-grid"><?php foreach($units as $unit): ?><div class="unit-card"><div class="unit-card-id"><?= htmlspecialchars($unit['unit_code']) ?></div><div class="unit-card-name"><?= htmlspecialchars($unit['name']) ?></div><div class="unit-card-loc"><?= htmlspecialchars($unit['location']) ?></div><p><?= htmlspecialchars($unit['model'] ?? 'Model not recorded') ?></p></div><?php endforeach; ?></div><?php endif; ?></div></div>
<?php require __DIR__ . '/includes/footer.php'; require __DIR__ . '/includes/footer-end.php'; ?>
