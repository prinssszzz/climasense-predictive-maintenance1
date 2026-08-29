<?php
/**
 * ClimaSense — Predictive Maintenance Data Layer
 * Simulated data source. Swap the body of these functions for real
 * database / sensor-gateway queries when a backend is connected.
 */

function cs_units() {
    static $units = null;
    if ($units !== null) return $units;

    $units = [
        [
            'id' => 'AC-101', 'name' => 'Executive Office 3F', 'location' => 'Admin Building · Floor 3',
            'model' => 'Daikin FTKC50 (1.5HP)', 'installed' => '2021-03-14',
            'health' => 91, 'status' => 'healthy', 'rul_days' => 214,
            'temp' => 23.4, 'pressure' => 118, 'current' => 4.2, 'vibration' => 0.18, 'humidity' => 52,
            'runtime_hrs' => 6120,
        ],
        [
            'id' => 'AC-102', 'name' => 'Server Room A', 'location' => 'Admin Building · Floor 1',
            'model' => 'Carrier XPower 2.0HP', 'installed' => '2019-11-02',
            'health' => 64, 'status' => 'warning', 'rul_days' => 38,
            'temp' => 19.8, 'pressure' => 145, 'current' => 7.9, 'vibration' => 0.44, 'humidity' => 46,
            'runtime_hrs' => 18940,
        ],
        [
            'id' => 'AC-103', 'name' => 'Conference Hall B', 'location' => 'Main Building · Floor 2',
            'model' => 'LG DualCool 2.5HP', 'installed' => '2022-06-20',
            'health' => 97, 'status' => 'healthy', 'rul_days' => 305,
            'temp' => 24.1, 'pressure' => 112, 'current' => 5.1, 'vibration' => 0.12, 'humidity' => 55,
            'runtime_hrs' => 3210,
        ],
        [
            'id' => 'AC-104', 'name' => 'Nurses Station', 'location' => 'Clinic Wing · Floor 1',
            'model' => 'Panasonic Envio 1.0HP', 'installed' => '2018-05-09',
            'health' => 39, 'status' => 'critical', 'rul_days' => 9,
            'temp' => 27.6, 'pressure' => 168, 'current' => 9.6, 'vibration' => 0.81, 'humidity' => 63,
            'runtime_hrs' => 24980,
        ],
        [
            'id' => 'AC-105', 'name' => 'Records Storage', 'location' => 'Admin Building · Basement',
            'model' => 'Daikin FTKC35 (1.0HP)', 'installed' => '2020-09-17',
            'health' => 78, 'status' => 'healthy', 'rul_days' => 132,
            'temp' => 21.9, 'pressure' => 124, 'current' => 4.8, 'vibration' => 0.23, 'humidity' => 49,
            'runtime_hrs' => 11040,
        ],
        [
            'id' => 'AC-106', 'name' => 'Faculty Lounge', 'location' => 'Main Building · Floor 3',
            'model' => 'Carrier Optimax 1.5HP', 'installed' => '2021-01-30',
            'health' => 54, 'status' => 'warning', 'rul_days' => 21,
            'temp' => 25.3, 'pressure' => 151, 'current' => 8.4, 'vibration' => 0.52, 'humidity' => 58,
            'runtime_hrs' => 15680,
        ],
    ];
    return $units;
}

function cs_unit($id) {
    foreach (cs_units() as $u) if ($u['id'] === $id) return $u;
    return null;
}

/** Deterministic pseudo-random sensor history for charts, seeded per unit. */
function cs_history($id, $points = 24, $base = null) {
    $seed = crc32($id);
    mt_srand($seed);
    $unit = cs_unit($id);
    $b = $base ?? ($unit['temp'] ?? 50);
    $status = $unit['status'] ?? 'healthy';
    $drift = ($status === 'critical') ? 1.6 : (($status === 'warning') ? 0.8 : 0.25);
    $series = [];
    $val = $b - $drift * 3;
    for ($i = 0; $i < $points; $i++) {
        $val += ($drift * 0.14) + (mt_rand(-60, 60) / 100);
        $series[] = round($val, 2);
    }
    return $series;
}

function cs_alerts() {
    return [
        ['id' => 1, 'unit' => 'AC-104', 'name' => 'Nurses Station', 'severity' => 'critical',
         'message' => 'Compressor current draw exceeded 9.5A threshold for 3 consecutive cycles.',
         'time' => '12 min ago', 'type' => 'Electrical'],
        ['id' => 2, 'unit' => 'AC-104', 'name' => 'Nurses Station', 'severity' => 'critical',
         'message' => 'Predicted refrigerant leak — subcooling delta dropped below 2°C.',
         'time' => '47 min ago', 'type' => 'Refrigerant'],
        ['id' => 3, 'unit' => 'AC-102', 'name' => 'Server Room A', 'severity' => 'warning',
         'message' => 'Condenser fan vibration trending +38% over 7-day baseline.',
         'time' => '2 hr ago', 'type' => 'Mechanical'],
        ['id' => 4, 'unit' => 'AC-106', 'name' => 'Faculty Lounge', 'severity' => 'warning',
         'message' => 'Evaporator coil temperature differential narrowing — possible airflow blockage.',
         'time' => '5 hr ago', 'type' => 'Airflow'],
        ['id' => 5, 'unit' => 'AC-102', 'name' => 'Server Room A', 'severity' => 'info',
         'message' => 'Scheduled filter inspection due in 6 days.',
         'time' => '1 day ago', 'type' => 'Maintenance'],
        ['id' => 6, 'unit' => 'AC-105', 'name' => 'Records Storage', 'severity' => 'info',
         'message' => 'Firmware for sensor gateway updated to v2.4.1.',
         'time' => '2 days ago', 'type' => 'System'],
    ];
}

function cs_maintenance_log() {
    return [
        ['date' => '2026-08-11', 'unit' => 'AC-101', 'name' => 'Executive Office 3F', 'task' => 'Routine filter cleaning', 'tech' => 'J. Ramos', 'status' => 'completed'],
        ['date' => '2026-08-09', 'unit' => 'AC-103', 'name' => 'Conference Hall B', 'task' => 'Coil inspection + condensate check', 'tech' => 'M. Santos', 'status' => 'completed'],
        ['date' => '2026-08-05', 'unit' => 'AC-106', 'name' => 'Faculty Lounge', 'task' => 'Airflow blockage diagnostics', 'tech' => 'J. Ramos', 'status' => 'completed'],
        ['date' => '2026-08-20', 'unit' => 'AC-104', 'name' => 'Nurses Station', 'task' => 'Emergency compressor inspection', 'tech' => 'Unassigned', 'status' => 'urgent'],
        ['date' => '2026-08-22', 'unit' => 'AC-102', 'name' => 'Server Room A', 'task' => 'Fan bearing replacement', 'tech' => 'M. Santos', 'status' => 'scheduled'],
        ['date' => '2026-08-27', 'unit' => 'AC-106', 'name' => 'Faculty Lounge', 'task' => 'Evaporator coil deep clean', 'tech' => 'Unassigned', 'status' => 'scheduled'],
        ['date' => '2026-09-02', 'unit' => 'AC-105', 'name' => 'Records Storage', 'task' => 'Quarterly performance audit', 'tech' => 'J. Ramos', 'status' => 'scheduled'],
    ];
}

function cs_status_meta($status) {
    $map = [
        'healthy'  => ['label' => 'Healthy',  'class' => 'ok'],
        'warning'  => ['label' => 'At Risk',  'class' => 'warn'],
        'critical' => ['label' => 'Critical', 'class' => 'crit'],
    ];
    return $map[$status] ?? ['label' => ucfirst($status), 'class' => 'ok'];
}

function cs_fleet_summary() {
    $units = cs_units();
    $total = count($units);
    $healthSum = array_sum(array_column($units, 'health'));
    $critical = count(array_filter($units, fn($u) => $u['status'] === 'critical'));
    $warning  = count(array_filter($units, fn($u) => $u['status'] === 'warning'));
    return [
        'total' => $total,
        'avg_health' => round($healthSum / max($total, 1)),
        'critical' => $critical,
        'warning' => $warning,
        'healthy' => $total - $critical - $warning,
    ];
}
