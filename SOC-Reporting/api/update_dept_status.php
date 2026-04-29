<?php
/**
 * SOC Reporting System - Update Department Status API
 * Updates the department_status field for a report
 */

Session::requireLogin();

header('Content-Type: application/json');

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        throw new Exception('Invalid JSON input');
    }

    $machine = (int) ($input['machine'] ?? 0);
    $reportId = (int) ($input['report_id'] ?? 0);
    $departmentStatus = $input['department_status'] ?? '';
    $resolutionDatetime = $input['resolution_datetime'] ?? null;

    // Validate inputs
    if ($machine < 1 || $machine > MACHINE_SLOTS) {
        throw new Exception('Invalid machine slot');
    }

    if ($reportId < 1) {
        throw new Exception('Invalid report ID');
    }

    if (!in_array($departmentStatus, ['informed', 'resolved'])) {
        throw new Exception('Invalid department status');
    }

    // Prepare update data
    $updateData = ['department_status' => $departmentStatus];

    // If resolving, store the resolution datetime
    if ($departmentStatus === 'resolved' && $resolutionDatetime) {
        // Convert from datetime-local format to MySQL datetime
        $updateData['resolution_datetime'] = date('Y-m-d H:i:s', strtotime($resolutionDatetime));
    }

    // Update the report
    $db = Database::machine($machine);
    Database::update($db, 'reports',
        $updateData,
        'id = ?',
        [$reportId]
    );

    // Audit log
    auditLog('dept_status_update', "Updated department status for report M$machine-$reportId to $departmentStatus");

    // Calculate response time if resolved
    $responseTime = null;
    if ($departmentStatus === 'resolved' && isset($updateData['resolution_datetime'])) {
        // Get the report to calculate response time
        $report = Database::fetchOne($db, "SELECT created_at FROM reports WHERE id = ?", [$reportId]);
        if ($report) {
            $createdTime = strtotime($report['created_at']);
            $resolvedTime = strtotime($updateData['resolution_datetime']);
            $diffSeconds = $resolvedTime - $createdTime;

            if ($diffSeconds > 0) {
                $hours = floor($diffSeconds / 3600);
                $minutes = floor(($diffSeconds % 3600) / 60);

                if ($hours > 0) {
                    $responseTime = $hours . 'h ' . $minutes . 'm';
                } else {
                    $responseTime = $minutes . 'm';
                }
            }
        }
    }

    $response = [
        'success' => true,
        'message' => 'Department status updated successfully'
    ];

    if ($responseTime) {
        $response['response_time'] = $responseTime;
    }

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
