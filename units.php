<?php
$pageTitle = 'AC Units';
$activeNav = 'units';
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/header.php';

$units = cs_units();
$buildings = array_unique(array_map(fn($u) => explode(' · ', $u['location'])[0], $units));
?>
<div class="page-head">
  <div>
    <span class="eyebrow">Fleet Registry</span>
    <h1>AC Units</h1>
    <p>All split-type units under active monitoring, with live predictive health scores.</p>
  </div>
  <div class="page-actions">
    <button class="btn btn-primary" data-modal-open="#modalAdd">
      <svg width="15" height="15" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
      Register Unit
    </button>
  </div>
</div>

<div class="filter-bar">
  <select data-filter-select="status">
    <option value="">All statuses</option>
    <option value="healthy">Healthy</option>
    <option value="warning">At Risk</option>
    <option value="critical">Critical</option>
  </select>
  <select data-filter-select="building">
    <option value="">All buildings</option>
    <?php foreach ($buildings as $b): ?>
      <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
    <?php endforeach; ?>
  </select>
  <span class="u-ml-auto u-text-sm u-text-slate"><?= count($units) ?> units registered</span>
</div>

<div class="unit-grid">
  <?php foreach ($units as $u): $meta = cs_status_meta($u['status']); $building = explode(' · ', $u['location'])[0]; ?>
    <a class="unit-card" href="unit-detail.php?id=<?= urlencode($u['id']) ?>"
       data-searchable="<?= htmlspecialchars($u['id'].' '.$u['name'].' '.$u['location'].' '.$u['model']) ?>"
       data-status="<?= $u['status'] ?>" data-building="<?= htmlspecialchars($building) ?>">
      <div class="unit-card-top">
        <div>
          <div class="unit-card-id"><?= $u['id'] ?></div>
          <div class="unit-card-name"><?= htmlspecialchars($u['name']) ?></div>
          <div class="unit-card-loc"><?= htmlspecialchars($u['location']) ?></div>
        </div>
        <span class="status-chip <?= $meta['class'] ?>"><?= $meta['label'] ?></span>
      </div>
      <div class="unit-card-mid">
        <?= cs_gauge($u['health'], $u['status'], 72) ?>
        <div class="unit-card-metrics">
          <div class="metric">Model <b class="u-text-xs"><?= htmlspecialchars(explode(' (', $u['model'])[0]) ?></b></div>
          <div class="metric">Installed <b class="u-text-xs"><?= date('M Y', strtotime($u['installed'])) ?></b></div>
          <div class="metric">Runtime <b><?= number_format($u['runtime_hrs']) ?> h</b></div>
          <div class="metric">Humidity <b><?= $u['humidity'] ?>%</b></div>
        </div>
      </div>
      <div class="unit-card-foot">
        <span>Est. Remaining Useful Life</span>
        <span class="rul-tag <?= cs_rul_class($u['rul_days']) ?>"><?= $u['rul_days'] ?> days</span>
      </div>
    </a>
  <?php endforeach; ?>
</div>

<div class="empty-state u-hidden" id="emptyState">
  <svg width="40" height="40" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2" fill="none"/><path d="M21 21l-4.3-4.3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
  <h4>No units match your filters</h4>
  <p>Try clearing the search or filter selections to see the full fleet.</p>
</div>

<!-- Register Unit Modal -->
<div class="modal-backdrop" id="modalAdd">
  <div class="modal">
    <div class="modal-head">
      <h3>Register New Unit</h3>
      <button class="modal-close" data-modal-close aria-label="Close">✕</button>
    </div>
    <form id="addUnitForm" onsubmit="event.preventDefault(); document.getElementById('modalAdd').classList.remove('open'); csToast('Unit registered — sensor pairing pending.'); this.reset();">
      <div class="field">
        <label for="nUnitName">Unit Label</label>
        <input id="nUnitName" class="input" type="text" placeholder="e.g. Reception Area 1F" required>
      </div>
      <div class="field">
        <label for="nUnitModel">Model</label>
        <input id="nUnitModel" class="input" type="text" placeholder="e.g. Daikin FTKC50 (1.5HP)" required>
      </div>
      <div class="field">
        <label for="nUnitLoc">Location</label>
        <input id="nUnitLoc" class="input" type="text" placeholder="Building · Floor" required>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary">Register</button>
      </div>
    </form>
  </div>
</div>

<script>
  // show empty state when all cards hidden by filters/search
  const grid = document.querySelector('.unit-grid');
  const emptyState = document.getElementById('emptyState');
  const observer = new MutationObserver(checkEmpty);
  function checkEmpty(){
    const visible = [...grid.children].some(c => c.style.display !== 'none');
    emptyState.style.display = visible ? 'none' : 'block';
  }
  document.querySelectorAll('[data-filter-select], #globalSearch').forEach(el => {
    el.addEventListener('input', () => setTimeout(checkEmpty, 10));
    el.addEventListener('change', () => setTimeout(checkEmpty, 10));
  });
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
<?php require __DIR__ . '/includes/footer-end.php'; ?>
