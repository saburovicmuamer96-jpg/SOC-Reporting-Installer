<?php
/**
 * SOC Reporting System - Update Threat Log Status API
 * Updates the status field for a threat log entry
 */

Session::requireLogin();

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        throw new Exception('Invalid JSON input');
    }

    $threatId = (int) ($input['threat_id'] ?? 0);
    $status = $input['status'] ?? '';

    if ($threatId < 1) {
        throw new Exception('Invalid threat ID');
    }

    $validStatuses = ['new', 'notified', 'patched', 'mitigated', 'closed'];
    if (!in_array($status, $validStatuses)) {
        throw new Exception('Invalid status');
    }

    $updateData = ['status' => $status];

    $now = date('Y-m-d H:i:s');

    // Set timestamps based on status
    if ($status === 'notified') {
        $updateData['notified_at'] = $now;
    }

    if ($status === 'patched') {
        $updateData['patched_at'] = $now;
    }

    if ($status === 'mitigated') {
        $updateData['mitigated_at'] = $now;
    }

    if ($status === 'closed') {
        $updateData['closed_at'] = $now;
    }

    $db = Database::system();
    Database::update($db, 'threat_logs', $updateData, 'id = ?', [$threatId]);

    auditLog('threat_status_update', "Updated threat log $threatId status to $status");

    echo json_encode([
        'success' => true,
        'message' => 'Status updated successfully'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
