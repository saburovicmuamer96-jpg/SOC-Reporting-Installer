<?php
/**
 * SOC Reporting System - Admin API
 * Handles AJAX admin operations.
 */

Session::requireLogin();
Session::requireAdmin();

header('Content-Type: application/json');

$action = get('action', post('action'));

try {
    switch ($action) {
        case 'get_audit_log':
            $page = max(1, (int) get('p', 1));
            $limit = 50;
            $offset = ($page - 1) * $limit;

            $logs = Database::fetchAll(Database::system(),
                "SELECT * FROM audit_log ORDER BY created_at DESC LIMIT $limit OFFSET $offset"
            );
            $total = Database::fetchOne(Database::system(),
                "SELECT COUNT(*) as total FROM audit_log"
            );

            echo json_encode([
                'logs' => $logs,
                'total' => $total['total'],
                'page' => $page,
                'pages' => ceil($total['total'] / $limit)
            ]);
            break;

        case 'get_users':
            $users = Database::fetchAll(Database::system(),
                "SELECT id, username, display_name, role, language, is_active, created_at
                 FROM users ORDER BY created_at DESC"
            );
            echo json_encode(['users' => $users]);
            break;

        case 'get_machine_stats':
            $stats = [];
            $allMachines = Database::fetchAll(Database::system(),
                "SELECT * FROM machines ORDER BY slot_number"
            );
            foreach ($allMachines as $m) {
                try {
                    $db = Database::machine($m['slot_number']);
                    $count = Database::fetchOne($db, "SELECT COUNT(*) as total FROM reports");
                    $today = Database::fetchOne($db,
                        "SELECT COUNT(*) as cnt FROM reports WHERE DATE(created_at) = CURDATE()"
                    );
                    $stats[] = [
                        'machine' => $m,
                        'total_reports' => $count['total'] ?? 0,
                        'today_reports' => $today['cnt'] ?? 0
                    ];
                } catch (Exception $e) {
                    $stats[] = [
                        'machine' => $m,
                        'total_reports' => 0,
                        'today_reports' => 0,
                        'error' => $e->getMessage()
                    ];
                }
            }
            echo json_encode(['stats' => $stats]);
            break;

        default:
            echo json_encode(['error' => 'Unknown action']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
