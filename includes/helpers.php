<?php
/** Renders an animated ring gauge. $value 0-100, $status healthy|warning|critical */
function cs_gauge($value, $status, $size = 76, $label = 'Health') {
    $cls = cs_status_meta($status)['class'];
    $r = ($size / 2) - 6;
    $c = round(2 * M_PI * $r, 2);
    $offset = round($c - ($c * max(0, min(100, $value)) / 100), 2);
    ob_start();
    ?>
    <div class="gauge-wrap" style="width:<?= $size ?>px;height:<?= $size ?>px" data-gauge>
      <svg viewBox="0 0 <?= $size ?> <?= $size ?>" width="<?= $size ?>" height="<?= $size ?>">
        <circle class="gauge-track" cx="<?= $size/2 ?>" cy="<?= $size/2 ?>" r="<?= $r ?>"></circle>
        <circle class="gauge-fill <?= $cls ?>" cx="<?= $size/2 ?>" cy="<?= $size/2 ?>" r="<?= $r ?>"
          stroke-dasharray="<?= $c ?>" stroke-dashoffset="<?= $c ?>" data-offset="<?= $offset ?>"></circle>
      </svg>
      <div class="gauge-value">
        <strong><?= $value ?></strong>
        <span><?= htmlspecialchars($label) ?></span>
      </div>
    </div>
    <?php
    return ob_get_clean();
}

function cs_rul_class($days) {
    if ($days <= 14) return 'crit';
    if ($days <= 60) return 'warn';
    return 'ok';
}

function cs_initials($name) {
    $parts = preg_split('/\s+/', trim($name));
    $ini = '';
    foreach (array_slice($parts, 0, 2) as $p) $ini .= mb_strtoupper(mb_substr($p, 0, 1));
    return $ini ?: '?';
}
