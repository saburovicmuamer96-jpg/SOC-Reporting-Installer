<?php
/**
 * SOC Reporting System - Form Fields API
 * Returns form field configuration for AJAX requests.
 */

Session::requireLogin();

header('Content-Type: application/json');

$action = get('action', post('action'));
$machineSlot = (int) get('machine', 0);

if ($machineSlot < 1 || $machineSlot > MACHINE_SLOTS) {
    echo json_encode(['error' => 'Invalid machine']);
    exit;
}

$machine = getMachineBySlot($machineSlot);
if (!$machine) {
    echo json_encode(['error' => 'Machine not found']);
    exit;
}

try {
    switch ($action) {
        case 'get_fields':
            $fields = getFormFields($machine['id']);

            // Include dropdown options for each dropdown field
            foreach ($fields as &$field) {
                if ($field['field_type'] === 'dropdown') {
                    $field['options'] = getDropdownOptions($field['id']);
                }
            }

            echo json_encode(['fields' => $fields]);
            break;

        default:
            echo json_encode(['error' => 'Unknown action']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
