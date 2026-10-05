<?php if (!empty($inspectionMode) && !empty($prefillUnitData)): ?>
<div class="inspection-popup">
<div class="modal-backdrop inspection-backdrop">
<section class="modal inspection-dialog" role="dialog" aria-modal="true" aria-labelledby="inspectionDialogTitle">
  <div class="modal-head"><div><h2 id="inspectionDialogTitle">Record AC Inspection</h2><p class="sub inspection-intro">Record the current condition and maintenance performed for this unit.</p></div><a class="modal-close" href="analytics.php" aria-label="Close inspection">×</a></div>
<?php
$attentionItems = [];
if ((float)($prefillUnitData['current'] ?? 0) > 6.5) $attentionItems[] = 'Compressor current is higher than normal.';
if ((float)($prefillUnitData['vibration'] ?? 0) > 0.4) $attentionItems[] = 'Vibration is above the expected range.';
if ((float)($prefillUnitData['pressure'] ?? 0) > 220) $attentionItems[] = 'Pressure is above the expected range.';
if ((float)($prefillUnitData['humidity'] ?? 0) > 60) $attentionItems[] = 'Humidity is above the expected range.';
if ((float)($prefillUnitData['temp'] ?? 0) > 26) $attentionItems[] = 'Temperature is above the expected range.';
if ((float)($prefillUnitData['operating_hours_per_day'] ?? 0) > 12) $attentionItems[] = 'Operating hours are above the expected range.';
?>
<section class="panel" aria-label="Unit risk summary" style="margin-bottom:16px;">
  <div class="panel-body">
    <h2 style="margin:0 0 10px;font-size:18px;"><?= htmlspecialchars($prefillUnit) ?> — <?= htmlspecialchars($prefillUnitData['name']) ?></h2>
    <?php $riskStatusLabel = $prefillUnitData['status'] === 'critical' ? 'High Risk' : ($prefillUnitData['status'] === 'warning' ? 'At Risk' : 'Healthy'); ?>
    <div><strong>Status:</strong> <span style="display:inline-flex;align-items:center;gap:6px;"><span aria-hidden="true" style="width:9px;height:9px;border-radius:50%;background:<?= $prefillUnitData['status'] === 'critical' ? '#e84c5b' : ($prefillUnitData['status'] === 'warning' ? '#d58b28' : '#2e9a69') ?>;"></span><?= htmlspecialchars($riskStatusLabel) ?></span></div>
    <div style="margin-top:4px;"><strong>Maintenance Risk:</strong> <?= (int)$riskValue ?>%</div>
  </div>
</section>
<form class="panel inspection-form" method="post" action="maintenance.php">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_maintenance']) ?>">
  <input type="hidden" name="unit" value="<?= htmlspecialchars($prefillUnit) ?>">
  <input type="hidden" name="task" value="<?= htmlspecialchars($prefillTask ?: 'Inspection report') ?>">
  <input type="hidden" name="technician" value="Unassigned">
  <input type="hidden" name="status" value="completed">
  <input type="hidden" name="priority" value="<?= htmlspecialchars($prefillPriority) ?>">
  <input type="hidden" name="maintenance_type" value="Inspection">
  <input type="hidden" name="risk" value="<?= (int)$riskValue ?>">
  <input type="hidden" name="date" value="<?= htmlspecialchars($today = date('Y-m-d')) ?>">
  <div class="panel-body">
    <h3>Current Readings</h3>
    <div class="inspection-readings"><div class="inspection-reading-head"><b>Measurement</b><b>Value</b></div>
      <?php foreach ([['Pressure', 'pressure', 'PSI'], ['Current', 'current', 'A'], ['Vibration', 'vibration', ''], ['Humidity', 'humidity', '%'], ['Temperature', 'temp', '°C'], ['Operating Hours', 'operating_hours_per_day', 'hrs/day']] as [$label, $key, $suffix]): ?>
        <?php if (isset($prefillUnitData[$key])):
          $readingValue = (float)$prefillUnitData[$key];
          $readingLevel = match ($key) {
            'pressure' => $readingValue > 250 ? 'bad' : ($readingValue > 220 ? 'warn' : 'good'),
            'current' => $readingValue > 7.5 ? 'bad' : ($readingValue > 6.5 ? 'warn' : 'good'),
            'vibration' => $readingValue > 0.7 ? 'bad' : ($readingValue > 0.4 ? 'warn' : 'good'),
            'humidity' => $readingValue > 70 ? 'bad' : ($readingValue > 60 ? 'warn' : 'good'),
            'temp' => $readingValue > 30 ? 'bad' : ($readingValue > 26 ? 'warn' : 'good'),
            'operating_hours_per_day' => $readingValue > 16 ? 'bad' : ($readingValue > 12 ? 'warn' : 'good'),
            default => 'good',
          };
          $readingColor = $readingLevel === 'bad' ? '#e84c5b' : ($readingLevel === 'warn' ? '#f39a38' : '#39c985');
          $readingLabel = $readingLevel === 'bad' ? 'Above recommended range' : ($readingLevel === 'warn' ? 'Near recommended limit' : 'Within recommended range');
        ?><div class="inspection-reading"><span><?= $label ?></span><strong style="display:inline-flex;align-items:center;gap:7px;"><?= htmlspecialchars((string)$prefillUnitData[$key]) ?><?= $suffix ? ' ' . $suffix : '' ?><span role="img" aria-label="<?= $readingLabel ?>" title="<?= $readingLabel ?>" style="width:10px;height:10px;flex:none;border-radius:50%;background:<?= $readingColor ?>;"></span></strong></div><?php endif; ?>
      <?php endforeach; ?>
    </div>
    <section class="inspection-attention" aria-labelledby="attentionHeading">
      <h3 id="attentionHeading">⚠ What needs attention?</h3>
      <p>Based on the current readings:</p>
      <?php if ($attentionItems): foreach ($attentionItems as $item): ?><p class="inspection-attention-item"><?= htmlspecialchars($item) ?></p><?php endforeach; ?>
      <p class="inspection-attention-item">Further inspection recommended.</p>
      <?php else: ?><p class="inspection-attention-item">Current readings are within the expected range.</p><?php endif; ?>
    </section>
    <h3>Condition Check</h3>
    <?php foreach ([
      'compressor' => ['Compressor', ['Normal', 'Warning', 'Abnormal']],
      'filter' => ['Filter', ['Clean', 'Dirty', 'Replace']],
      'refrigerant' => ['Refrigerant', ['Normal', 'Low', 'Possible leak']],
    ] as $field => [$label, $choices]): ?>
      <fieldset class="inspection-condition"><legend><?= $label ?></legend><div class="inspection-options">
        <?php foreach ($choices as $choice): ?><label><input type="radio" name="inspection_report[<?= $field ?>]" value="<?= htmlspecialchars($choice) ?>" <?= $choice === $choices[0] ? 'checked' : '' ?> required><span class="inspection-dot <?= $loopClass = ($field === 'filter' ? ($choice === 'Clean' ? 'good' : ($choice === 'Dirty' ? 'warn' : 'bad')) : ($choice === $choices[0] ? 'good' : ($choice === $choices[1] ? 'warn' : 'bad'))) ?>"></span><?= htmlspecialchars($choice) ?></label><?php endforeach; ?>
      </div></fieldset>
    <?php endforeach; ?>
    <fieldset class="inspection-condition inspection-work"><legend>Maintenance Performed</legend><div class="inspection-options">
      <?php foreach (['Filter cleaned', 'Filter replaced', 'Refrigerant added', 'Electrical connection repaired', 'Compressor repaired', 'Component replaced', 'Other'] as $work): ?><label><input type="checkbox" name="inspection_report[work][]" value="<?= htmlspecialchars($work) ?>"><?= htmlspecialchars($work) ?></label><?php endforeach; ?>
    </div></fieldset>
    <div class="field"><label for="inspectionFault">Fault Reported</label><textarea id="inspectionFault" name="inspection_report[fault_reported]" class="input" maxlength="2000" placeholder="What issue did the customer report?"></textarea></div>
    <div class="field"><label for="inspectionComponents">Replaced Components</label><input id="inspectionComponents" name="inspection_report[replaced_components]" class="input" type="text" maxlength="1000" placeholder="List replaced components, if any"></div>
    <div class="field"><label for="inspectionFindings">Inspection Findings</label><textarea id="inspectionFindings" name="inspection_report[findings]" class="input" maxlength="2000" placeholder="What did you find?"></textarea></div>
    <div class="field"><label for="inspectionRemarks">Remarks</label><textarea id="inspectionRemarks" name="inspection_report[remarks]" class="input" maxlength="2000" placeholder="Additional service notes"></textarea></div>
    <fieldset class="inspection-condition"><legend>After Maintenance</legend><div class="inspection-options">
      <?php foreach (['Good', 'Monitor', 'Needs Follow-up', 'Needs Major Repair'] as $condition): ?><label><input type="radio" name="inspection_report[final_condition]" value="<?= htmlspecialchars($condition) ?>" <?= $condition === 'Good' ? 'checked' : '' ?> required><span class="inspection-dot <?= $condition === 'Good' ? 'good' : ($condition === 'Monitor' ? 'warn' : ($condition === 'Needs Follow-up' ? 'warn' : 'bad')) ?>"></span><?= htmlspecialchars($condition) ?></label><?php endforeach; ?>
    </div></fieldset>
    <div class="modal-actions"><a class="btn btn-ghost" href="analytics.php">Cancel</a><button type="submit" class="btn btn-primary">Save Inspection</button></div>
  </div>
</form>
</section>
</div>
</div>
<?php endif; ?>
