<?php
/**
 * SOC Reporting System - Charts Data API
 * Returns aggregated data for Chart.js (Splunk-style dashboard).
 */

Session::requireLogin();

header('Content-Type: application/json');

$machineSlot = (int) get('machine', 0);
$chartType = get('chart', 'reports_over_time');

// Support both 'days' param and explicit 'date_from'/'date_to' params
$customFrom = get('date_from', '');
$customTo = get('date_to', '');

if ($customFrom && preg_match('/^\d{4}-\d{2}-\d{2}$/', $customFrom)) {
    $dateFrom = $customFrom;
    $dateTo = ($customTo && preg_match('/^\d{4}-\d{2}-\d{2}$/', $customTo)) ? $customTo : date('Y-m-d');
} else {
    $days = max(1, min(3650, (int) get('days', 30)));
    $dateFrom = date('Y-m-d', strtotime("-$days days"));
    $dateTo = date('Y-m-d');
}

try {
    // Determine which machines to query
    $machineSlots = [];
    if ($machineSlot > 0 && $machineSlot <= MACHINE_SLOTS) {
        $machineSlots = [$machineSlot];
    } else {
        for ($i = 1; $i <= MACHINE_SLOTS; $i++) {
            $machineSlots[] = $i;
        }
    }

    $allMachines = Database::fetchAll(Database::system(), "SELECT * FROM machines ORDER BY slot_number");
    $machineNames = [];
    foreach ($allMachines as $m) {
        $machineNames[$m['slot_number']] = $m['name'];
    }

    switch ($chartType) {

        // === KPI: Total reports across selected machines ===
        case 'kpi_totals':
            $total = 0;
            $today = 0;
            $avgReaction = [];
            $activeUsers = [];
            foreach ($machineSlots as $slot) {
                try {
                    $db = Database::machine($slot);
                    $cnt = Database::fetchOne($db,
                        "SELECT COUNT(*) as c FROM reports WHERE DATE(created_at) >= ? AND DATE(created_at) <= ?", [$dateFrom, $dateTo]);
                    $total += $cnt['c'] ?? 0;

                    $todayCnt = Database::fetchOne($db,
                        "SELECT COUNT(*) as c FROM reports WHERE DATE(created_at) = CURDATE()");
                    $today += $todayCnt['c'] ?? 0;

                    $avg = Database::fetchOne($db,
                        "SELECT AVG(reaction_time_seconds) as avg_s FROM reports WHERE reaction_time_seconds IS NOT NULL AND DATE(created_at) >= ? AND DATE(created_at) <= ?",
                        [$dateFrom, $dateTo]);
                    if ($avg && $avg['avg_s'] !== null) $avgReaction[] = (float)$avg['avg_s'];

                    $users = Database::fetchAll($db,
                        "SELECT DISTINCT username FROM reports WHERE DATE(created_at) >= ? AND DATE(created_at) <= ?", [$dateFrom, $dateTo]);
                    foreach ($users as $u) $activeUsers[$u['username']] = true;
                } catch (Exception $e) { continue; }
            }
            $avgReactionMin = !empty($avgReaction) ? round(array_sum($avgReaction) / count($avgReaction) / 60, 1) : null;
            echo json_encode([
                'total' => $total,
                'today' => $today,
                'avg_reaction_min' => $avgReactionMin,
                'active_users' => count($activeUsers)
            ]);
            break;

        // === Multi-machine timeline (each machine is a separate line) ===
        case 'timeline_by_machine':
            $datasets = [];
            $allDates = [];
            foreach ($machineSlots as $slot) {
                $machineData = [];
                try {
                    $db = Database::machine($slot);
                    $rows = Database::fetchAll($db,
                        "SELECT DATE(created_at) as date, COUNT(*) as count
                         FROM reports WHERE DATE(created_at) >= ? AND DATE(created_at) <= ?
                         GROUP BY DATE(created_at) ORDER BY date",
                        [$dateFrom, $dateTo]);
                    foreach ($rows as $row) {
                        $machineData[$row['date']] = (int)$row['count'];
                        $allDates[$row['date']] = true;
                    }
                } catch (Exception $e) { continue; }
                $datasets[$slot] = [
                    'name' => $machineNames[$slot] ?? "Machine $slot",
                    'data' => $machineData
                ];
            }
            ksort($allDates);
            $labels = array_keys($allDates);

            // Fill gaps with 0
            $result = [];
            foreach ($datasets as $slot => $ds) {
                $values = [];
                foreach ($labels as $date) {
                    $values[] = $ds['data'][$date] ?? 0;
                }
                $result[] = ['name' => $ds['name'], 'values' => $values, 'slot' => $slot];
            }
            echo json_encode(['labels' => $labels, 'datasets' => $result]);
            break;

        // === Reports over time (aggregated) ===
        case 'reports_over_time':
            $data = [];
            foreach ($machineSlots as $slot) {
                try {
                    $db = Database::machine($slot);
                    $rows = Database::fetchAll($db,
                        "SELECT DATE(created_at) as date, COUNT(*) as count
                         FROM reports WHERE DATE(created_at) >= ? AND DATE(created_at) <= ?
                         GROUP BY DATE(created_at) ORDER BY date",
                        [$dateFrom, $dateTo]);
                    foreach ($rows as $row) {
                        $data[$row['date']] = ($data[$row['date']] ?? 0) + $row['count'];
                    }
                } catch (Exception $e) { continue; }
            }
            ksort($data);
            echo json_encode(['labels' => array_keys($data), 'values' => array_values($data)]);
            break;

        // === Reports by user ===
        case 'reports_by_user':
            $data = [];
            foreach ($machineSlots as $slot) {
                try {
                    $db = Database::machine($slot);
                    $rows = Database::fetchAll($db,
                        "SELECT username, COUNT(*) as count FROM reports
                         WHERE DATE(created_at) >= ? AND DATE(created_at) <= ? GROUP BY username ORDER BY count DESC",
                        [$dateFrom, $dateTo]);
                    foreach ($rows as $row) {
                        $data[$row['username']] = ($data[$row['username']] ?? 0) + $row['count'];
                    }
                } catch (Exception $e) { continue; }
            }
            arsort($data);
            echo json_encode(['labels' => array_keys($data), 'values' => array_values($data)]);
            break;

        // === Reports by status ===
        case 'reports_by_status':
            $data = [];
            foreach ($machineSlots as $slot) {
                try {
                    $db = Database::machine($slot);
                    $rows = Database::fetchAll($db,
                        "SELECT status, COUNT(*) as count FROM reports
                         WHERE DATE(created_at) >= ? AND DATE(created_at) <= ? GROUP BY status",
                        [$dateFrom, $dateTo]);
                    foreach ($rows as $row) {
                        $data[$row['status']] = ($data[$row['status']] ?? 0) + $row['count'];
                    }
                } catch (Exception $e) { continue; }
            }
            echo json_encode(['labels' => array_keys($data), 'values' => array_values($data)]);
            break;

        // === Reports by machine ===
        case 'reports_by_machine':
            $labels = [];
            $values = [];
            foreach ($allMachines as $m) {
                $labels[] = $m['name'];
                try {
                    $db = Database::machine($m['slot_number']);
                    $count = Database::fetchOne($db,
                        "SELECT COUNT(*) as count FROM reports WHERE DATE(created_at) >= ? AND DATE(created_at) <= ?",
                        [$dateFrom, $dateTo]);
                    $values[] = $count['count'] ?? 0;
                } catch (Exception $e) { $values[] = 0; }
            }
            echo json_encode(['labels' => $labels, 'values' => $values]);
            break;

        // === Reports by hour of day ===
        case 'reports_by_hour':
            $hours = array_fill(0, 24, 0);
            foreach ($machineSlots as $slot) {
                try {
                    $db = Database::machine($slot);
                    $rows = Database::fetchAll($db,
                        "SELECT HOUR(created_at) as hr, COUNT(*) as count FROM reports
                         WHERE DATE(created_at) >= ? AND DATE(created_at) <= ? GROUP BY HOUR(created_at)",
                        [$dateFrom, $dateTo]);
                    foreach ($rows as $row) {
                        $hours[(int)$row['hr']] += (int)$row['count'];
                    }
                } catch (Exception $e) { continue; }
            }
            $labels = [];
            for ($h = 0; $h < 24; $h++) {
                $labels[] = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00';
            }
            echo json_encode(['labels' => $labels, 'values' => array_values($hours)]);
            break;

        // === Reports by day of week ===
        case 'reports_by_weekday':
            $weekdays = array_fill(0, 7, 0);
            foreach ($machineSlots as $slot) {
                try {
                    $db = Database::machine($slot);
                    $rows = Database::fetchAll($db,
                        "SELECT DAYOFWEEK(created_at) as dow, COUNT(*) as count FROM reports
                         WHERE DATE(created_at) >= ? AND DATE(created_at) <= ? GROUP BY DAYOFWEEK(created_at)",
                        [$dateFrom, $dateTo]);
                    foreach ($rows as $row) {
                        $weekdays[((int)$row['dow'] + 5) % 7] += (int)$row['count']; // MySQL: 1=Sun, convert to 0=Mon
                    }
                } catch (Exception $e) { continue; }
            }
            echo json_encode([
                'labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                'values' => array_values($weekdays)
            ]);
            break;

        // === Reaction time trend ===
        case 'reaction_time_trend':
            $data = [];
            foreach ($machineSlots as $slot) {
                try {
                    $db = Database::machine($slot);
                    $rows = Database::fetchAll($db,
                        "SELECT DATE(created_at) as date, AVG(reaction_time_seconds) as avg_s
                         FROM reports
                         WHERE reaction_time_seconds IS NOT NULL AND DATE(created_at) >= ? AND DATE(created_at) <= ?
                         GROUP BY DATE(created_at) ORDER BY date",
                        [$dateFrom, $dateTo]);
                    foreach ($rows as $row) {
                        if (!isset($data[$row['date']])) {
                            $data[$row['date']] = ['sum' => 0, 'cnt' => 0];
                        }
                        $data[$row['date']]['sum'] += (float)$row['avg_s'];
                        $data[$row['date']]['cnt']++;
                    }
                } catch (Exception $e) { continue; }
            }
            ksort($data);
            $labels = [];
            $values = [];
            foreach ($data as $date => $d) {
                $labels[] = $date;
                $values[] = round($d['sum'] / $d['cnt'] / 60, 1); // Convert to minutes
            }
            echo json_encode(['labels' => $labels, 'values' => $values]);
            break;

        // === Reaction time by machine ===
        case 'reaction_by_machine':
            $labels = [];
            $values = [];
            foreach ($allMachines as $m) {
                $labels[] = $m['name'];
                try {
                    $db = Database::machine($m['slot_number']);
                    $avg = Database::fetchOne($db,
                        "SELECT AVG(reaction_time_seconds) as avg_s FROM reports
                         WHERE reaction_time_seconds IS NOT NULL AND DATE(created_at) >= ? AND DATE(created_at) <= ?",
                        [$dateFrom, $dateTo]);
                    $values[] = $avg && $avg['avg_s'] !== null ? round((float)$avg['avg_s'] / 60, 1) : 0;
                } catch (Exception $e) { $values[] = 0; }
            }
            echo json_encode(['labels' => $labels, 'values' => $values]);
            break;

        // === Top field values (most common values for each field) ===
        case 'top_field_values':
            $results = [];
            foreach ($machineSlots as $slot) {
                try {
                    $db = Database::machine($slot);
                    $rows = Database::fetchAll($db,
                        "SELECT field_name, field_value, COUNT(*) as count FROM report_data
                         rd JOIN reports r ON rd.report_id = r.id
                         WHERE DATE(r.created_at) >= ? AND DATE(r.created_at) <= ? AND field_value IS NOT NULL AND field_value != ''
                         GROUP BY field_name, field_value ORDER BY count DESC LIMIT 20",
                        [$dateFrom, $dateTo]);
                    foreach ($rows as $row) {
                        $key = $row['field_name'] . '::' . $row['field_value'];
                        $results[$key] = ($results[$key] ?? 0) + (int)$row['count'];
                    }
                } catch (Exception $e) { continue; }
            }
            arsort($results);
            $topResults = array_slice($results, 0, 15, true);
            $labels = [];
            $values = [];
            foreach ($topResults as $key => $count) {
                $parts = explode('::', $key, 2);
                $labels[] = $parts[1] . ' (' . $parts[0] . ')';
                $values[] = $count;
            }
            echo json_encode(['labels' => $labels, 'values' => $values]);
            break;

        default:
            echo json_encode(['error' => 'Unknown chart type']);
    }

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
