<aside class="sidebar" id="sidebar">
  <div class="brand">
    <span class="brand-mark" aria-hidden="true">
      <svg viewBox="0 0 40 40" width="26" height="26">
        <path d="M20 4c0 8-9 10-9 18a9 9 0 0 0 18 0c0-8-9-10-9-18Z" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linejoin="round"/>
        <path d="M20 15c0 4-4.5 5-4.5 9a4.5 4.5 0 0 0 9 0c0-4-4.5-5-4.5-9Z" fill="currentColor"/>
      </svg>
    </span>
    <div class="brand-text">
      <strong>ClimaSense</strong>
      <span>Predictive Maintenance</span>
    </div>
  </div>

  <?php $consumerPortal = cs_primary_role_for_user() === 'consumer'; ?>
  <nav class="nav">
    <?php if ($consumerPortal): ?>
    <p class="nav-label">Monitor</p>
    <a href="consumer-dashboard.php" class="nav-item <?= $activeNav==='dashboard'?'active':'' ?>">🏠 <span>Dashboard</span></a>
    <a href="consumer-units.php" class="nav-item <?= $activeNav==='units'?'active':'' ?>">❄️ <span>My AC Units</span></a>
    <p class="nav-label">Service</p>
    <a href="consumer-service-history.php" class="nav-item <?= $activeNav==='service-history'?'active':'' ?>">▤ <span>Service History</span></a>
    <p class="nav-label">Account</p>
    <a href="profile.php" class="nav-item <?= $activeNav==='profile'?'active':'' ?>">👤 <span>Profile</span></a>
    <?php else: ?>
    <p class="nav-label">Monitor</p>
    <a href="index.php" class="nav-item <?= $activeNav==='dashboard'?'active':'' ?>">
      <svg viewBox="0 0 24 24" width="18" height="18"><rect x="3" y="3" width="8" height="8" rx="1.5" stroke="currentColor" stroke-width="2" fill="none"/><rect x="13" y="3" width="8" height="5" rx="1.5" stroke="currentColor" stroke-width="2" fill="none"/><rect x="13" y="10" width="8" height="11" rx="1.5" stroke="currentColor" stroke-width="2" fill="none"/><rect x="3" y="13" width="8" height="8" rx="1.5" stroke="currentColor" stroke-width="2" fill="none"/></svg>
      Dashboard
    </a>
    <a href="units.php" class="nav-item <?= $activeNav==='units'?'active':'' ?>">
      <svg viewBox="0 0 24 24" width="18" height="18"><rect x="3" y="5" width="18" height="10" rx="2" stroke="currentColor" stroke-width="2" fill="none"/><path d="M7 19h10M9 15v4M15 15v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      AC Units
    </a>
    <?php if (cs_user_can('units.manage') && !empty(cs_current_user()['organization_id'])): ?><a href="unit-registrations.php" class="nav-item <?= $activeNav==='unit-registrations'?'active':'' ?>">▣ <span>Unit Registrations</span></a><?php endif; ?>
    <a href="analytics.php" class="nav-item <?= $activeNav==='analytics'?'active':'' ?>">
      <svg viewBox="0 0 24 24" width="18" height="18"><path d="M4 19V9M11 19V4M18 19v-7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M3 19h18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      Predictive Analytics
    </a>

    <p class="nav-label">Operations</p>
    <a href="maintenance.php" class="nav-item <?= $activeNav==='maintenance'?'active':'' ?>">
      <svg viewBox="0 0 24 24" width="18" height="18"><path d="M14.7 6.3a4 4 0 0 1-5.4 5.4L4 17l3 3 5.3-5.3a4 4 0 0 1 5.4-5.4l-2.3 2.3-2-2 2.3-2.3Z" stroke="currentColor" stroke-width="2" fill="none" stroke-linejoin="round"/></svg>
      Maintenance Log
    </a>
    <a href="alerts.php" class="nav-item <?= $activeNav==='alerts'?'active':'' ?>">
      <svg viewBox="0 0 24 24" width="18" height="18"><path d="M12 3 2 20h20L12 3Z" stroke="currentColor" stroke-width="2" fill="none" stroke-linejoin="round"/><path d="M12 10v4M12 17h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      Alerts
      <?php if (cs_has_role('super_admin') && ($fleet['critical'] ?? 0) > 0): ?><span class="nav-pill"><?= $fleet['critical'] ?></span><?php endif; ?>
    </a>
    <?php if (cs_has_role('super_admin')): ?><a href="settings.php" class="nav-item <?= $activeNav==='settings'?'active':'' ?>">
      <svg viewBox="0 0 24 24" width="18" height="18"><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2" fill="none"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3H9a1.7 1.7 0 0 0 1-1.6V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9V9a1.7 1.7 0 0 0 1.6 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.6 1Z" stroke="currentColor" stroke-width="1.6" fill="none" stroke-linejoin="round"/></svg>
      System Settings
    </a><?php endif; ?>
    <?php endif; ?>
  </nav>

  <?php if (cs_has_role('super_admin')): ?><div class="sidebar-footer">
    <div class="fleet-mini">
      <span class="fleet-mini-label">Fleet Health</span>
      <div class="fleet-mini-bar">
        <span style="width:<?= (int)$fleet['avg_health'] ?>%"></span>
      </div>
      <span class="fleet-mini-val"><?= $fleet['avg_health'] ?>%</span>
    </div>
  </div><?php endif; ?>
</aside>
<div class="sidebar-scrim" id="sidebarScrim"></div>
