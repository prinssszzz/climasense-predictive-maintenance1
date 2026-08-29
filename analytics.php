<?php
$pageTitle = 'Predictive Analytics';
$activeNav = 'analytics';
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/header.php';

$units = cs_units();
usort($units, fn($a, $b) => $a['rul_days'] <=> $b['rul_days']);
$rulLabels = array_column($units, 'id');
$rulValues = array_column($units, 'rul_days');
$rulColors = array_map(fn($u) => $u['status'] === 'critical' ? '#b4432d' : ($u['status'] === 'warning' ? '#c67c2e' : '#1b6b4c'), $units);

$features = ['Current Draw', 'Subcooling Δ', 'Vibration RMS', 'Coil ΔT', 'Runtime Hrs', 'Cycle Frequency'];
$importance = [28, 24, 19, 14, 9, 6];
?>
<div class="page-head">
  <div>
    <span class="eyebrow">Model Insights</span>
    <h1>Predictive Analytics</h1>
    <p>Remaining-useful-life ranking, sensor feature importance, and correlation signals from the fleet's predictive model.</p>
  </div>
</div>

<div class="kpi-row">
  <div class="kpi-card">
    <span class="kpi-label">Model</span>
    <div class="kpi-value kpi-value-text">Gradient-Boosted RUL</div>
    <div class="kpi-delta up">v3.2 · retrained weekly</div>
  </div>
  <div class="kpi-card">
    <span class="kpi-label">Mean Absolute Error</span>
    <div class="kpi-value">4.6 <small>days</small></div>
    <div class="kpi-delta up">−0.8d vs prior version</div>
  </div>
  <div class="kpi-card">
    <span class="kpi-label">Avg. Prediction Confidence</span>
    <div class="kpi-value">92 <small>%</small></div>
    <div class="kpi-delta up">Across 6 monitored units</div>
  </div>
  <div class="kpi-card">
    <span class="kpi-label">Alerts Prevented (90d)</span>
    <div class="kpi-value">11 <small>failures</small></div>
    <div class="kpi-delta up">Est. downtime saved: 63h</div>
  </div>
</div>

<div class="grid-2">
  <div>
    <div class="panel">
      <div class="panel-head">
        <div><h3>Remaining Useful Life Ranking</h3><div class="sub">Days until predicted service threshold, most urgent first</div></div>
      </div>
      <div class="panel-body"><div class="chart-box"><canvas id="rulChart"></canvas></div></div>
    </div>

    <div class="panel">
      <div class="panel-head">
        <div><h3>Sensor Feature Importance</h3><div class="sub">Contribution to the RUL prediction model</div></div>
      </div>
      <div class="panel-body"><div class="chart-box"><canvas id="featChart"></canvas></div></div>
    </div>
  </div>

  <div>
    <div class="panel">
      <div class="panel-head"><h3>How to read this</h3></div>
      <div class="panel-body recommend-list">
        <div class="recommend-item">
          <span class="ri-icon"><svg width="16" height="16" viewBox="0 0 24 24"><path d="M12 8v5M12 16h.01" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/><circle cx="12" cy="12" r="9.5" stroke="currentColor" stroke-width="1.6" fill="none"/></svg></span>
          <div><b>RUL Ranking</b><p>Units nearest zero should be scheduled first — RUL is a probabilistic estimate, not a hard deadline.</p></div>
        </div>
        <div class="recommend-item">
          <span class="ri-icon"><svg width="16" height="16" viewBox="0 0 24 24"><path d="M9 12.5 11 15l4.5-6" stroke="currentColor" stroke-width="2.2" fill="none" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="12" r="9.5" stroke="currentColor" stroke-width="1.6" fill="none"/></svg></span>
          <div><b>Feature Importance</b><p>Current draw and subcooling delta are the strongest early indicators of compressor and refrigerant issues.</p></div>
        </div>
      </div>
    </div>

    <div class="panel">
      <div class="panel-head"><h3>Fleet Comparison</h3><div class="sub">Health score vs. runtime hours</div></div>
      <div class="panel-body"><div class="chart-box sm"><canvas id="scatterChart"></canvas></div></div>
    </div>

    <div class="panel">
      <div class="panel-head"><h3>Risk Watchlist</h3></div>
      <div class="panel-body u-col u-gap-3">
        <?php foreach (array_filter($units, fn($u)=>$u['status']!=='healthy') as $u): $m = cs_status_meta($u['status']); ?>
          <a href="unit-detail.php?id=<?= urlencode($u['id']) ?>" class="fleet-cmp-row">
            <span><b class="u-text-ink"><?= $u['id'] ?></b> · <?= htmlspecialchars($u['name']) ?></span>
            <span class="status-chip <?= $m['class'] ?>"><?= $m['label'] ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
<script>
  csBarChart('rulChart', <?= json_encode($rulLabels) ?>, <?= json_encode($rulValues) ?>, <?= json_encode($rulColors) ?>);
  csBarChart('featChart', <?= json_encode($features) ?>, <?= json_encode($importance) ?>, '#4e9575');

  (function(){
    const canvas = document.getElementById('scatterChart');
    if (canvas && window.Chart) {
      const points = <?= json_encode(array_map(fn($u) => ['x' => $u['runtime_hrs'], 'y' => $u['health'], 'label' => $u['id']], $units)) ?>;
      new Chart(canvas, {
        type: 'scatter',
        data: { datasets: [{ data: points.map(p => ({x:p.x,y:p.y})), backgroundColor: '#1b6b4c', pointRadius: 6, pointHoverRadius: 8 }] },
        options: {
          responsive:true, maintainAspectRatio:false,
          plugins: { legend:{display:false}, tooltip: { callbacks: { label: (ctx) => points[ctx.dataIndex].label + ': ' + ctx.parsed.y + '% health' } } },
          scales: {
            x: { title: { display:true, text:'Runtime hours' }, grid:{ color:'rgba(18,33,27,.06)' } },
            y: { title: { display:true, text:'Health %' }, grid:{ color:'rgba(18,33,27,.06)' } }
          }
        }
      });
    }
  })();
</script>

<?php require __DIR__ . '/includes/footer-end.php'; ?>
