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

/** Return verified customer-registered units belonging to one service shop. */
function cs_registered_units_for_shop(int $organizationId): array {
    if ($organizationId < 1) return [];
    try {
        $query = cs_db()->prepare("SELECT unit_code,name,brand,model,capacity,location,installed_on,runtime_hours FROM ac_units WHERE organization_id=? AND verification_status='active' ORDER BY id DESC");
        $query->execute([$organizationId]);
        return array_map(fn($row) => [
            'id' => $row['unit_code'], 'name' => $row['name'], 'location' => $row['location'],
            'model' => trim(implode(' ', array_filter([$row['brand'] ?? '', $row['model'] ?? '', $row['capacity'] ?? '']))),
            'installed' => $row['installed_on'] ?: date('Y-m-d'), 'health' => 100, 'status' => 'healthy',
            'rul_days' => null, 'temp' => null, 'pressure' => null, 'current' => null, 'vibration' => null,
            'humidity' => 50, 'runtime_hrs' => (int)$row['runtime_hours'], 'registered' => true,
        ], $query->fetchAll());
    } catch (Throwable $e) {
        return [];
    }
}

function cs_registered_unit_for_shop(string $unitCode, int $organizationId): ?array {
    foreach (cs_registered_units_for_shop($organizationId) as $unit) {
        if ($unit['id'] === $unitCode) return $unit;
    }
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
        ['date' => '2026-08-11', 'unit' => 'AC-101', 'name' => 'Executive Office 3F', 'task' => 'Routine filter cleaning', 'tech' => 'J. Ramos', 'status' => 'completed', 'priority' => 'normal'],
        ['date' => '2026-08-09', 'unit' => 'AC-103', 'name' => 'Conference Hall B', 'task' => 'Coil inspection + condensate check', 'tech' => 'M. Santos', 'status' => 'completed', 'priority' => 'normal'],
        ['date' => '2026-08-05', 'unit' => 'AC-106', 'name' => 'Faculty Lounge', 'task' => 'Airflow blockage diagnostics', 'tech' => 'J. Ramos', 'status' => 'completed', 'priority' => 'normal'],
        ['date' => '2026-08-20', 'unit' => 'AC-104', 'name' => 'Nurses Station', 'task' => 'Emergency compressor inspection', 'tech' => 'Unassigned', 'status' => 'urgent', 'priority' => 'urgent'],
        ['date' => '2026-08-22', 'unit' => 'AC-102', 'name' => 'Server Room A', 'task' => 'Fan bearing replacement', 'tech' => 'M. Santos', 'status' => 'scheduled', 'priority' => 'high'],
        ['date' => '2026-08-27', 'unit' => 'AC-106', 'name' => 'Faculty Lounge', 'task' => 'Evaporator coil deep clean', 'tech' => 'Unassigned', 'status' => 'scheduled', 'priority' => 'normal'],
        ['date' => '2026-09-02', 'unit' => 'AC-105', 'name' => 'Records Storage', 'task' => 'Quarterly performance audit', 'tech' => 'J. Ramos', 'status' => 'scheduled', 'priority' => 'normal'],
    ];
    try {
        require_once __DIR__ . '/db.php';
        $db = cs_db();
        $db->exec("CREATE TABLE IF NOT EXISTS maintenance_log_entries (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            service_date DATE NOT NULL,
            scheduled_time TIME NULL,
            unit_code VARCHAR(40) NOT NULL,
            unit_name VARCHAR(150) NOT NULL,
            task VARCHAR(255) NOT NULL,
            technician VARCHAR(120) NOT NULL DEFAULT 'Unassigned',
            status ENUM('scheduled','urgent','completed') NOT NULL DEFAULT 'scheduled',
            priority VARCHAR(20) NOT NULL DEFAULT 'normal',
            risk_score TINYINT UNSIGNED NULL,
            inspection_report JSON NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX(service_date), INDEX(unit_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $priorityColumn = $db->query("SHOW COLUMNS FROM maintenance_log_entries LIKE 'priority'")->fetch();
        if (!$priorityColumn) $db->exec("ALTER TABLE maintenance_log_entries ADD COLUMN priority VARCHAR(20) NOT NULL DEFAULT 'normal'");
        $riskColumn = $db->query("SHOW COLUMNS FROM maintenance_log_entries LIKE 'risk_score'")->fetch();
        if (!$riskColumn) $db->exec("ALTER TABLE maintenance_log_entries ADD COLUMN risk_score TINYINT UNSIGNED NULL");
        $reportColumn = $db->query("SHOW COLUMNS FROM maintenance_log_entries LIKE 'inspection_report'")->fetch();
        if (!$reportColumn) $db->exec("ALTER TABLE maintenance_log_entries ADD COLUMN inspection_report JSON NULL");
        $timeColumn = $db->query("SHOW COLUMNS FROM maintenance_log_entries LIKE 'scheduled_time'")->fetch();
        if (!$timeColumn) $db->exec("ALTER TABLE maintenance_log_entries ADD COLUMN scheduled_time TIME NULL AFTER service_date");
        if ((int)$db->query('SELECT COUNT(*) FROM maintenance_log_entries')->fetchColumn() === 0) {
            $seed = $db->prepare('INSERT INTO maintenance_log_entries (service_date, unit_code, unit_name, task, technician, status, priority) VALUES (?, ?, ?, ?, ?, ?, ?)');
            foreach ($demoLog as $entry) {
                $seed->execute([$entry['date'], $entry['unit'], $entry['name'], $entry['task'], $entry['tech'], $entry['status'], $entry['priority']]);
            }
        }
        $rows = $db->query('SELECT service_date, scheduled_time, unit_code, unit_name, task, technician, status, priority, risk_score, inspection_report FROM maintenance_log_entries ORDER BY service_date DESC, id DESC')->fetchAll();
        $savedLog = array_map(fn($row) => [
            'date' => $row['service_date'], 'time' => $row['scheduled_time'] ? substr($row['scheduled_time'], 0, 5) : null, 'unit' => $row['unit_code'], 'name' => $row['unit_name'],
            'task' => $row['task'], 'tech' => $row['technician'], 'status' => $row['status'], 'priority' => $row['priority'], 'risk' => $row['risk_score'], 'inspection_report' => $row['inspection_report'] ? json_decode($row['inspection_report'], true) : null,
        ], $rows);
        return $savedLog;
    } catch (Throwable $e) {
        return $demoLog;
    }
}

/** Estimate a per-unit service cadence from completed maintenance dates. */
function cs_maintenance_interval(array $entries): array {
    $completedDates = [];
    foreach ($entries as $entry) {
        if (($entry['status'] ?? '') !== 'completed') continue;
        $dateString = (string)($entry['date'] ?? '');
        $date = DateTime::createFromFormat('!Y-m-d', $dateString);
        if ($date && $date->format('Y-m-d') === $dateString) $completedDates[$dateString] = $date;
    }
    krsort($completedDates);
    $dates = array_values($completedDates);
    $intervals = [];
    for ($index = 0; $index + 1 < count($dates); $index++) {
        $days = (int)$dates[$index]->diff($dates[$index + 1])->days;
        if ($days > 0) $intervals[] = $days;
    }
    if (!$intervals) return ['average_days' => null, 'next_date' => null, 'intervals' => []];
    $average = (int)round(array_sum($intervals) / count($intervals));
    $nextDate = clone $dates[0];
    $nextDate->modify('+' . $average . ' days');
    return ['average_days' => $average, 'next_date' => $nextDate->format('Y-m-d'), 'intervals' => $intervals];
}

/** Label the customer-facing state from an estimated maintenance date. */
function cs_maintenance_status(?string $nextDate): array {
    if (!$nextDate) return ['key' => 'clear', 'label' => 'No Maintenance Needed', 'days_remaining' => null];
    $today = new DateTimeImmutable('today');
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $nextDate);
    if (!$date || $date->format('Y-m-d') !== $nextDate) return ['key' => 'clear', 'label' => 'No Maintenance Needed', 'days_remaining' => null];
    $days = (int)$today->diff($date)->format('%r%a');
    if ($days <= 0) return ['key' => 'due', 'label' => 'Maintenance Due', 'days_remaining' => $days];
    if ($days <= 30) return ['key' => 'recommended', 'label' => 'Maintenance Recommended', 'days_remaining' => $days];
    return ['key' => 'clear', 'label' => 'No Maintenance Needed', 'days_remaining' => $days];
}

/** Create an in-app reminder once a unit reaches its 30-day, 7-day, or due date. */
function cs_sync_consumer_maintenance_notifications(int $userId, array $unitServices): array {
    require_once __DIR__ . '/db.php';
    $db = cs_db();
    $db->exec("CREATE TABLE IF NOT EXISTS consumer_maintenance_notifications (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        unit_code VARCHAR(40) NOT NULL,
        maintenance_date DATE NOT NULL,
        notification_type ENUM('30_day','7_day','due') NOT NULL,
        title VARCHAR(160) NOT NULL,
        message VARCHAR(500) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_consumer_maintenance_notice (user_id, unit_code, maintenance_date, notification_type),
        INDEX consumer_notices_user_date (user_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $insert = $db->prepare('INSERT IGNORE INTO consumer_maintenance_notifications (user_id, unit_code, maintenance_date, notification_type, title, message) VALUES (?, ?, ?, ?, ?, ?)');
    $currentDates = [];
    foreach ($unitServices as $unitCode => $service) {
        if (($service['verification_status'] ?? '') !== 'active') continue;
        if (($service['active']['status'] ?? '') === 'scheduled') continue;
        $pattern = cs_maintenance_interval($service['records'] ?? []);
        $nextDate = $pattern['next_date'] ?? null;
        if (!$nextDate) continue;
        $currentDates[$unitCode] = $nextDate;
        $status = cs_maintenance_status($nextDate);
        $days = $status['days_remaining'];
        if ($days === null || $days > 30) continue;
        if ($days <= 0) {
            $type = 'due'; $title = 'Maintenance is due'; $message = $unitCode . ' is due for maintenance. Please contact your service provider to arrange a visit.';
        } elseif ($days <= 7) {
            $type = '7_day'; $title = 'Maintenance is coming up'; $message = 'Maintenance for ' . $unitCode . ' is estimated for ' . date('F j, Y', strtotime($nextDate)) . '.';
        } else {
            $type = '30_day'; $title = 'Maintenance is approaching'; $message = 'Maintenance for ' . $unitCode . ' is estimated for ' . date('F j, Y', strtotime($nextDate)) . '.';
        }
        $insert->execute([$userId, $unitCode, $nextDate, $type, $title, $message]);
    }
    $query = $db->prepare('SELECT unit_code, maintenance_date, notification_type, title, message, created_at FROM consumer_maintenance_notifications WHERE user_id=? ORDER BY created_at DESC, id DESC LIMIT 10');
    $query->execute([$userId]);
    $rows = $query->fetchAll();
    $notices = [];
    $seenUnits = [];
    foreach ($rows as $row) {
        if (($currentDates[$row['unit_code']] ?? null) !== $row['maintenance_date'] || isset($seenUnits[$row['unit_code']])) continue;
        $notices[] = $row;
        $seenUnits[$row['unit_code']] = true;
    }
    return $notices;
}

function cs_ensure_client_notification_schema(): void {
    cs_db()->exec("CREATE TABLE IF NOT EXISTS client_customer_notifications (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        organization_id BIGINT UNSIGNED NOT NULL,
        customer_id BIGINT UNSIGNED NOT NULL,
        unit_code VARCHAR(40) NOT NULL,
        channel ENUM('email','sms') NOT NULL,
        message TEXT NOT NULL,
        sent_at DATETIME NOT NULL,
        status ENUM('sent','failed','not_configured') NOT NULL,
        INDEX client_notification_org_date (organization_id, sent_at),
        INDEX client_notification_customer (customer_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function cs_send_client_maintenance_email(string $email, string $customerName, string $unitCode, string $maintenanceDate): bool {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !function_exists('mail')) return false;
    $firstName = explode(' ', trim($customerName))[0] ?: 'there';
    $subject = 'Recommended AC Maintenance - ' . $unitCode;
    $message = "Hello {$firstName},\n\nBased on your previous maintenance records, your AC is approaching its recommended maintenance period.\n\nUnit: {$unitCode}\nEstimated maintenance date: " . date('F j, Y', strtotime($maintenanceDate)) . "\n\nPlease contact your service provider to arrange a suitable maintenance visit.\n\nClimaSense";
    $fromHost = preg_replace('/[^A-Za-z0-9.-]/', '', (string)($_SERVER['SERVER_NAME'] ?? 'localhost')) ?: 'localhost';
    return @mail($email, $subject, $message, "From: ClimaSense <noreply@{$fromHost}>\r\nContent-Type: text/plain; charset=UTF-8");
}

function cs_record_client_notification(int $organizationId, int $customerId, string $unitCode, string $channel, string $message, string $status): void {
    if (!in_array($channel, ['email', 'sms'], true) || !in_array($status, ['sent', 'failed', 'not_configured'], true)) return;
    cs_ensure_client_notification_schema();
    $query = cs_db()->prepare('INSERT INTO client_customer_notifications (organization_id, customer_id, unit_code, channel, message, sent_at, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $query->execute([$organizationId, $customerId, $unitCode, $channel, $message, date('Y-m-d H:i:s'), $status]);
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
        scheduled_time TIME NULL,
        unit_code VARCHAR(40) NOT NULL,
        unit_name VARCHAR(150) NOT NULL,
        task VARCHAR(255) NOT NULL,
        technician VARCHAR(120) NOT NULL DEFAULT 'Unassigned',
        status ENUM('scheduled','urgent','completed') NOT NULL DEFAULT 'scheduled',
        priority VARCHAR(20) NOT NULL DEFAULT 'normal',
        risk_score TINYINT UNSIGNED NULL,
        inspection_report JSON NULL,
        created_by BIGINT UNSIGNED NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX(service_date), INDEX(unit_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $priorityColumn = $db->query("SHOW COLUMNS FROM maintenance_log_entries LIKE 'priority'")->fetch();
    if (!$priorityColumn) $db->exec("ALTER TABLE maintenance_log_entries ADD COLUMN priority VARCHAR(20) NOT NULL DEFAULT 'normal'");
    $riskColumn = $db->query("SHOW COLUMNS FROM maintenance_log_entries LIKE 'risk_score'")->fetch();
    if (!$riskColumn) $db->exec("ALTER TABLE maintenance_log_entries ADD COLUMN risk_score TINYINT UNSIGNED NULL");
    $reportColumn = $db->query("SHOW COLUMNS FROM maintenance_log_entries LIKE 'inspection_report'")->fetch();
    if (!$reportColumn) $db->exec("ALTER TABLE maintenance_log_entries ADD COLUMN inspection_report JSON NULL");
    $timeColumn = $db->query("SHOW COLUMNS FROM maintenance_log_entries LIKE 'scheduled_time'")->fetch();
    if (!$timeColumn) $db->exec("ALTER TABLE maintenance_log_entries ADD COLUMN scheduled_time TIME NULL AFTER service_date");
    $q = $db->prepare('INSERT INTO maintenance_log_entries (service_date, scheduled_time, unit_code, unit_name, task, technician, status, priority, risk_score, inspection_report, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $reportData = is_array($entry['inspection_report'] ?? null) ? $entry['inspection_report'] : [];
    $serviceDetails = is_array($entry['service_details'] ?? null) ? $entry['service_details'] : [];
    if ($serviceDetails) {
        $reportData['service_details'] = [
            'maintenance_type' => substr(trim((string)($serviceDetails['maintenance_type'] ?? '')), 0, 40),
            'fault_reported' => substr(trim((string)($serviceDetails['fault_reported'] ?? '')), 0, 2000),
            'repair_performed' => substr(trim((string)($serviceDetails['repair_performed'] ?? '')), 0, 2000),
            'replaced_components' => substr(trim((string)($serviceDetails['replaced_components'] ?? '')), 0, 1000),
            'remarks' => substr(trim((string)($serviceDetails['remarks'] ?? '')), 0, 2000),
        ];
    }
    $report = $reportData ? json_encode($reportData, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null;
    $q->execute([$entry['date'], $entry['time'] ?? null, $entry['unit'], $entry['name'], $entry['task'], $entry['tech'], $entry['status'], $entry['priority'] ?? 'normal', $entry['risk'] ?? null, $report, $userId]);
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
