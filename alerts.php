<?php
$pageTitle = 'Alerts';
$activeNav = 'alerts';
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/header.php';

$alerts = cs_alerts();
$counts = ['critical'=>0,'warning'=>0,'info'=>0];
foreach ($alerts as $a) $counts[$a['severity']]++;
?>
<div class="page-head">
  <div>
    <span class="eyebrow">Notification Center</span>
    <h1>Alerts</h1>
    <p>Real-time and predictive alerts generated from anomaly detection across the fleet.</p>
  </div>
</div>

<div class="kpi-row">
  <div class="kpi-card">
    <span class="kpi-icon" style="color:var(--rust);background:var(--rust-bg)"><svg width="17" height="17" viewBox="0 0 24 24"><path d="M12 8v5M12 16h.01" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2" fill="none"/></svg></span>
    <span class="kpi-label">Critical</span>
    <div class="kpi-value"><?= $counts['critical'] ?></div>
    <div class="kpi-delta down">Immediate action needed</div>
  </div>
  <div class="kpi-card">
    <span class="kpi-icon" style="color:var(--amber);background:var(--amber-bg)"><svg width="17" height="17" viewBox="0 0 24 24"><path d="M12 3 2 20h20L12 3Z" stroke="currentColor" stroke-width="2" fill="none" stroke-linejoin="round"/><path d="M12 10v4M12 17h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span>
    <span class="kpi-label">Warning</span>
    <div class="kpi-value"><?= $counts['warning'] ?></div>
    <div class="kpi-delta down">Monitor closely</div>
  </div>
  <div class="kpi-card">
    <span class="kpi-icon"><svg width="17" height="17" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2" fill="none"/><path d="M12 8v.01M12 11v5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span>
    <span class="kpi-label">Info</span>
    <div class="kpi-value"><?= $counts['info'] ?></div>
    <div class="kpi-delta up">Informational only</div>
  </div>
  <div class="kpi-card">
    <span class="kpi-label">Avg. Time to Acknowledge</span>
    <div class="kpi-value">18 <small>min</small></div>
    <div class="kpi-delta up">Team response speed</div>
  </div>
</div>

<div class="filter-bar">
  <select data-filter-select="severity">
    <option value="">All severities</option>
    <option value="critical">Critical</option>
    <option value="warning">Warning</option>
    <option value="info">Info</option>
  </select>
</div>

<div class="panel">
  <div class="panel-body">
    <?php foreach ($alerts as $a): ?>
      <div class="alert-item" data-searchable="<?= htmlspecialchars($a['unit'].' '.$a['name'].' '.$a['message']) ?>" data-severity="<?= $a['severity'] ?>">
        <span class="alert-dot <?= $a['severity'] ?>"></span>
        <div class="alert-body">
          <div class="alert-top">
            <strong><?= htmlspecialchars($a['name']) ?> <span class="mono u-text-slate u-fw-500">· <?= $a['unit'] ?></span></strong>
            <span class="alert-time"><?= $a['time'] ?></span>
          </div>
          <p class="alert-msg"><?= htmlspecialchars($a['message']) ?></p>
          <div class="alert-tags u-row u-gap-2">
            <span class="tag"><?= htmlspecialchars($a['type']) ?></span>
            <a href="unit-detail.php?id=<?= urlencode($a['unit']) ?>" class="tag tag-ok">View Unit</a>
            <?php if ($a['severity'] !== 'info'): ?>
              <button class="btn btn-ghost btn-sm u-ml-auto" data-ack-alert>Acknowledge</button>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
<?php require __DIR__ . '/includes/footer-end.php'; ?>
