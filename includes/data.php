<?php
/**
 * ClimaSense — Predictive Maintenance Data Layer
 * Simulated data source. Swap the body of these functions for real
 * database / sensor-gateway queries when a backend is connected.
 */

function cs_units() {
    static $units = null;
    if ($units !== null) return $units;

    $csvPath = __DIR__ . '/../data/ac_dummy_dataset_1250.csv';
    $units = [];

    if (is_file($csvPath) && is_readable($csvPath)) {
        $handle = fopen($csvPath, 'r');
        $header = fgetcsv($handle);
        $index = array_flip($header);

        $seq = 101;
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < count($header)) continue;
            $data = array_combine($header, $row);
            $temp = (float) ($data['operating_temperature_c'] ?? 25);
            $hours = (float) ($data['operating_hours_per_day'] ?? 10);
            $energy = (float) ($data['energy_consumption_kwh'] ?? 1.1);
            $comp = (float) ($data['compression_condition_score'] ?? 80);
            $filter = (float) ($data['filter_condition_score'] ?? 80);

            $health = round((($comp + $filter) / 2) - max(0, abs($temp - 25) * 1.8) * 0.5 + max(0, (10 - $hours) * 1.4), 0);
            $health = max(25, min(99, $health));

            if ($health >= 80) $status = 'healthy';
            elseif ($health >= 60) $status = 'warning';
            else $status = 'critical';

            $rul = max(7, min(365, (int) round(($health / 100) * 180)));
            $pressure = (int) round(105 + ($temp * 2.4) + (($hours - 10) * 4) + ((100 - $comp) * 0.5));
            $current = round(2.6 + ($energy * 2.2) + (($health < 60) ? 2.4 : 0), 2);
            $vibration = round(0.12 + ((100 - $comp) / 100) * 0.9 + (($status === 'critical') ? 0.25 : 0), 2);
            $humidity = (int) round(42 + max(0, ($temp - 24) * 2.3) + (($health < 60) ? 12 : 0));

            $units[] = [
                'id' => 'AC-' . $seq,
                'name' => trim(($data['brand'] ?? 'ClimaSense') . ' ' . ($data['model'] ?? 'Inverter')),
                'location' => trim(($data['location'] ?? 'Metro Manila') . ' · ' . ($data['brand'] ?? 'AC')),
                'model' => trim(($data['brand'] ?? 'ClimaSense') . ' ' . ($data['model'] ?? 'Inverter')),
                'installed' => date('Y-m-d', strtotime('-' . rand(8, 58) . ' months')),
                'health' => (int) $health,
                'status' => $status,
                'rul_days' => $rul,
                'temp' => round($temp, 1),
                'pressure' => $pressure,
                'current' => $current,
                'vibration' => $vibration,
                'humidity' => $humidity,
                'runtime_hrs' => (int) round(($hours * 30) * 8),
                'operating_hours_per_day' => $hours,
                'energy_consumption_kwh' => $energy,
                'compression_condition_score' => $comp,
                'filter_condition_score' => $filter,
            ];
            $seq++;
        }
        fclose($handle);
    }

    if (!$units) {
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
    }

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

/** Build a short, year-specific watchlist from the analytics unit projections. */
function cs_predictive_alerts(int $forecastYear, int $limit = 12): array {
    $units = array_values(array_filter(cs_units_for_forecast_year($forecastYear), fn($unit) => $unit['status'] !== 'healthy'));
    usort($units, fn($a, $b) => $a['health'] <=> $b['health']);
    return array_map(function ($unit) use ($forecastYear) {
        $critical = $unit['status'] === 'critical';
        return [
            'id' => 'forecast-' . $forecastYear . '-' . $unit['id'],
            'unit' => $unit['id'],
            'name' => $unit['name'],
            'severity' => $critical ? 'critical' : 'warning',
            'message' => $critical
                ? "{$forecastYear} projection estimates {$unit['health']}% health and {$unit['rul_days']} days of remaining life. Prioritize an inspection."
                : "{$forecastYear} projection estimates {$unit['health']}% health and {$unit['rul_days']} days of remaining life. Schedule a condition check.",
            'time' => $forecastYear . ' forecast',
            'type' => 'Predictive Health',
        ];
    }, array_slice($units, 0, max(0, $limit)));
}

function cs_maintenance_log() {
    $demoLog = [
        ['date' => '2026-08-11', 'unit' => 'AC-101', 'name' => 'Executive Office 3F', 'task' => 'Routine filter cleaning', 'tech' => 'J. Ramos', 'status' => 'completed'],
        ['date' => '2026-08-09', 'unit' => 'AC-103', 'name' => 'Conference Hall B', 'task' => 'Coil inspection + condensate check', 'tech' => 'M. Santos', 'status' => 'completed'],
        ['date' => '2026-08-05', 'unit' => 'AC-106', 'name' => 'Faculty Lounge', 'task' => 'Airflow blockage diagnostics', 'tech' => 'J. Ramos', 'status' => 'completed'],
        ['date' => '2026-08-20', 'unit' => 'AC-104', 'name' => 'Nurses Station', 'task' => 'Emergency compressor inspection', 'tech' => 'Unassigned', 'status' => 'urgent'],
        ['date' => '2026-08-22', 'unit' => 'AC-102', 'name' => 'Server Room A', 'task' => 'Fan bearing replacement', 'tech' => 'M. Santos', 'status' => 'scheduled'],
        ['date' => '2026-08-27', 'unit' => 'AC-106', 'name' => 'Faculty Lounge', 'task' => 'Evaporator coil deep clean', 'tech' => 'Unassigned', 'status' => 'scheduled'],
        ['date' => '2026-09-02', 'unit' => 'AC-105', 'name' => 'Records Storage', 'task' => 'Quarterly performance audit', 'tech' => 'J. Ramos', 'status' => 'scheduled'],
    ];
    try {
        require_once __DIR__ . '/db.php';
        $db = cs_db();
        $db->exec("CREATE TABLE IF NOT EXISTS maintenance_log_entries (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            service_date DATE NOT NULL,
            unit_code VARCHAR(40) NOT NULL,
            unit_name VARCHAR(150) NOT NULL,
            task VARCHAR(255) NOT NULL,
            technician VARCHAR(120) NOT NULL DEFAULT 'Unassigned',
            status ENUM('scheduled','urgent','completed') NOT NULL DEFAULT 'scheduled',
            created_by BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX(service_date), INDEX(unit_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        if ((int)$db->query('SELECT COUNT(*) FROM maintenance_log_entries')->fetchColumn() === 0) {
            $seed = $db->prepare('INSERT INTO maintenance_log_entries (service_date, unit_code, unit_name, task, technician, status) VALUES (?, ?, ?, ?, ?, ?)');
            foreach ($demoLog as $entry) {
                $seed->execute([$entry['date'], $entry['unit'], $entry['name'], $entry['task'], $entry['tech'], $entry['status']]);
            }
        }
        $rows = $db->query('SELECT service_date, unit_code, unit_name, task, technician, status FROM maintenance_log_entries ORDER BY service_date DESC, id DESC')->fetchAll();
        $savedLog = array_map(fn($row) => [
            'date' => $row['service_date'], 'unit' => $row['unit_code'], 'name' => $row['unit_name'],
            'task' => $row['task'], 'tech' => $row['technician'], 'status' => $row['status'],
        ], $rows);
        return $savedLog;
    } catch (Throwable $e) {
        return $demoLog;
    }
}

/** Return the same year-adjusted unit projection used by Predictive Analytics. */
function cs_units_for_forecast_year(int $forecastYear): array {
    $yearOffset = $forecastYear - 2026;
    return array_map(function ($unit) use ($yearOffset) {
        $annualWear = 0.9
            + max(0, ($unit['operating_hours_per_day'] ?? 8) - 8) * 0.22
            + abs(($unit['temp'] ?? 25) - 25) * 0.06
            + ($unit['energy_consumption_kwh'] ?? 1) * 0.10
            + (100 - ($unit['compression_condition_score'] ?? 80)) * 0.018
            + (100 - ($unit['filter_condition_score'] ?? 80)) * 0.014;
        $unit['health'] = (int) round(max(25, min(99, $unit['health'] - ($yearOffset * $annualWear))));
        $unit['compression_condition_score'] = round(max(25, min(99, ($unit['compression_condition_score'] ?? 80) - ($yearOffset * $annualWear * 0.55))));
        $unit['filter_condition_score'] = round(max(20, min(99, ($unit['filter_condition_score'] ?? 80) - ($yearOffset * $annualWear * 0.75))));
        $unit['energy_consumption_kwh'] = round(max(0.1, ($unit['energy_consumption_kwh'] ?? 1) * (1 + ($yearOffset * 0.012))), 2);
        $unit['rul_days'] = max(7, min(365, (int) round(($unit['health'] / 100) * 180)));
        $unit['status'] = $unit['health'] >= 80 ? 'healthy' : ($unit['health'] >= 60 ? 'warning' : 'critical');
        return $unit;
    }, cs_units());
}

function cs_add_maintenance_log(array $entry, ?int $userId = null): void {
    require_once __DIR__ . '/db.php';
    $db = cs_db();
    $db->exec("CREATE TABLE IF NOT EXISTS maintenance_log_entries (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        service_date DATE NOT NULL,
        unit_code VARCHAR(40) NOT NULL,
        unit_name VARCHAR(150) NOT NULL,
        task VARCHAR(255) NOT NULL,
        technician VARCHAR(120) NOT NULL DEFAULT 'Unassigned',
        status ENUM('scheduled','urgent','completed') NOT NULL DEFAULT 'scheduled',
        created_by BIGINT UNSIGNED NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX(service_date), INDEX(unit_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $q = $db->prepare('INSERT INTO maintenance_log_entries (service_date, unit_code, unit_name, task, technician, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $q->execute([$entry['date'], $entry['unit'], $entry['name'], $entry['task'], $entry['tech'], $entry['status'], $userId]);
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
