<?php
/**
 * SOC Reporting System - Reports API
 * Handles AJAX operations for reports.
 */

Session::requireLogin();

header('Content-Type: application/json');

$action = get('action', post('action'));
$machineSlot = (int) (get('machine') ?: post('machine'));

if ($machineSlot < 1 || $machineSlot > MACHINE_SLOTS) {
    echo json_encode(['error' => 'Invalid machine']);
    exit;
}

try {
    $db = Database::machine($machineSlot);

    switch ($action) {
        case 'list':
            $page = max(1, (int) get('p', 1));
            $offset = ($page - 1) * REPORTS_PER_PAGE;

            $where = [];
            $params = [];

            if ($eventId = get('event_id')) {
                $where[] = "event_id LIKE ?";
                $params[] = "%$eventId%";
            }
            if ($dateFrom = get('date_from')) {
                $where[] = "DATE(created_at) >= ?";
                $params[] = $dateFrom;
            }
            if ($dateTo = get('date_to')) {
                $where[] = "DATE(created_at) <= ?";
                $params[] = $dateTo;
            }
            if ($status = get('status')) {
                $where[] = "status = ?";
                $params[] = $status;
            }

            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

            $count = Database::fetchOne($db, "SELECT COUNT(*) as total FROM reports $whereClause", $params);
            $reports = Database::fetchAll($db,
                "SELECT * FROM reports $whereClause ORDER BY created_at DESC LIMIT " . REPORTS_PER_PAGE . " OFFSET $offset",
                $params
            );

            echo json_encode([
                'reports' => $reports,
                'total' => $count['total'],
                'page' => $page,
                'pages' => ceil($count['total'] / REPORTS_PER_PAGE)
            ]);
            break;

        case 'get':
            $id = (int) get('id');
            $report = Database::fetchOne($db, "SELECT * FROM reports WHERE id = ?", [$id]);
            if (!$report) {
                echo json_encode(['error' => 'Report not found']);
                exit;
            }

            $reportData = Database::fetchAll($db,
                "SELECT * FROM report_data WHERE report_id = ? ORDER BY id",
                [$id]
            );

            echo json_encode([
                'report' => $report,
                'data' => $reportData
            ]);
            break;

        case 'update_status':
            if (!Session::isAdmin()) {
                echo json_encode(['error' => 'Admin access required']);
                exit;
            }

            $id = (int) post('id');
            $status = post('status');
            $validStatuses = ['draft', 'submitted', 'reviewed', 'closed'];

            if (!in_array($status, $validStatuses)) {
                echo json_encode(['error' => 'Invalid status']);
                exit;
            }

            Database::update($db, 'reports', ['status' => $status], 'id = ?', [$id]);
            auditLog('report_status_changed', "Report $id status changed to $status");

            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['error' => 'Unknown action']);
    }

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
