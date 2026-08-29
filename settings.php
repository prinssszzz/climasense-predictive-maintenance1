<?php
$pageTitle = 'Settings';
$activeNav = 'settings';
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div>
    <span class="eyebrow">System Configuration</span>
    <h1>Settings</h1>
    <p>Tune alert thresholds and manage how ClimaSense monitors your fleet.</p>
  </div>
</div>

<div class="grid-2">
  <div>
    <div class="panel">
      <div class="panel-head"><div><h3>Alert Thresholds</h3><div class="sub">Trigger points for warning and critical status</div></div></div>
      <div class="panel-body">
        <form onsubmit="event.preventDefault(); csToast('Thresholds saved.');">
          <div class="field">
            <label for="tCurrent">Compressor current draw — warning above (A)</label>
            <input id="tCurrent" class="input" type="number" step="0.1" value="7.0">
          </div>
          <div class="field">
            <label for="tCurrentCrit">Compressor current draw — critical above (A)</label>
            <input id="tCurrentCrit" class="input" type="number" step="0.1" value="9.0">
          </div>
          <div class="field">
            <label for="tVib">Vibration RMS — warning above (mm/s)</label>
            <input id="tVib" class="input" type="number" step="0.01" value="0.35">
          </div>
          <div class="field">
            <label for="tRul">Remaining useful life — critical below (days)</label>
            <input id="tRul" class="input" type="number" value="14">
          </div>
          <button type="submit" class="btn btn-primary">Save Thresholds</button>
        </form>
      </div>
    </div>
  </div>

  <div>
    <div class="panel">
      <div class="panel-head"><h3>Notification Channels</h3></div>
      <div class="panel-body u-col u-gap-4">
        <label class="u-row u-gap-3 u-text-base"><input type="checkbox" checked> Email alerts to facilities team</label>
        <label class="u-row u-gap-3 u-text-base"><input type="checkbox" checked> SMS for critical alerts only</label>
        <label class="u-row u-gap-3 u-text-base"><input type="checkbox"> Weekly PDF health summary</label>
      </div>
    </div>

    <div class="panel">
      <div class="panel-head"><h3>About This System</h3></div>
      <div class="panel-body spec-list">
        <div class="spec-row"><span>Platform</span><b>ClimaSense v1.0</b></div>
        <div class="spec-row"><span>Sensor gateway</span><b>Firmware 2.4.1</b></div>
        <div class="spec-row"><span>Prediction model</span><b>Gradient-Boosted RUL v3.2</b></div>
        <div class="spec-row"><span>Monitored units</span><b><?= cs_fleet_summary()['total'] ?></b></div>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
<?php require __DIR__ . '/includes/footer-end.php'; ?>
