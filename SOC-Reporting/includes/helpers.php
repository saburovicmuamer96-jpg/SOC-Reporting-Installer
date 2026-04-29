<?php
/**
 * SOC Reporting System - Helper Functions
 */

/**
 * Escape output for HTML (XSS prevention).
 */
function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Get the current language from session or default.
 */
function currentLang(): string {
    return $_SESSION['language'] ?? DEFAULT_LANGUAGE;
}

/**
 * Get a translated label based on current language.
 */
function label(string $en, string $de): string {
    return currentLang() === 'de' ? $de : $en;
}

/**
 * Redirect to a URL.
 */
function redirect(string $url): void {
    header("Location: $url");
    exit;
}

/**
 * Get a field label in the current language.
 */
function fieldLabel(array $field): string {
    return currentLang() === 'de' ? $field['field_label_de'] : $field['field_label_en'];
}

/**
 * Get a field placeholder in the current language.
 */
function fieldPlaceholder(array $field): string {
    return currentLang() === 'de' ? ($field['placeholder_de'] ?? '') : ($field['placeholder_en'] ?? '');
}

/**
 * Get a dropdown option label in the current language.
 */
function optionLabel(array $option): string {
    return currentLang() === 'de' ? $option['label_de'] : $option['label_en'];
}

/**
 * Format a datetime string.
 */
function formatDatetime(string $datetime): string {
    return date(DATETIME_FORMAT, strtotime($datetime));
}

/**
 * Format a date string.
 */
function formatDate(string $datetime): string {
    return date(DATE_FORMAT, strtotime($datetime));
}

/**
 * Format a time string.
 */
function formatTime(string $datetime): string {
    return date(TIME_FORMAT, strtotime($datetime));
}

/**
 * Generate the next event ID for a machine.
 * Format: M{slot}-{YYYYMMDD}-{NNN}
 */
function generateEventId(int $machineSlot): string {
    $db = Database::machine($machineSlot);
    $today = date('Y-m-d');
    $dateKey = date('Ymd');

    // Get or create counter for today
    $counter = Database::fetchOne($db,
        "SELECT last_sequence FROM event_counters WHERE date_key = ?",
        [$today]
    );

    if ($counter) {
        $seq = $counter['last_sequence'] + 1;
        Database::update($db, 'event_counters',
            ['last_sequence' => $seq],
            'date_key = ?', [$today]
        );
    } else {
        $seq = 1;
        Database::insert($db, 'event_counters', [
            'date_key' => $today,
            'last_sequence' => $seq
        ]);
    }

    return sprintf('%s%d-%s-%03d', EVENT_ID_PREFIX, $machineSlot, $dateKey, $seq);
}

/**
 * Get all active machines.
 */
function getActiveMachines(): array {
    return Database::fetchAll(Database::system(),
        "SELECT * FROM machines WHERE is_active = 1 ORDER BY slot_number"
    );
}

/**
 * Get a single machine by slot.
 */
function getMachineBySlot(int $slot): ?array {
    return Database::fetchOne(Database::system(),
        "SELECT * FROM machines WHERE slot_number = ?",
        [$slot]
    );
}

/**
 * Get form fields for a machine.
 */
function getFormFields(int $machineId): array {
    return Database::fetchAll(Database::system(),
        "SELECT * FROM form_fields WHERE machine_id = ? AND is_active = 1 ORDER BY field_order",
        [$machineId]
    );
}

/**
 * Get dropdown options for a field.
 */
function getDropdownOptions(int $fieldId): array {
    return Database::fetchAll(Database::system(),
        "SELECT * FROM dropdown_options WHERE field_id = ? AND is_active = 1 ORDER BY sort_order",
        [$fieldId]
    );
}

/**
 * Log an audit event.
 */
function auditLog(string $action, ?string $details = null): void {
    $userId = $_SESSION['user_id'] ?? null;
    $username = $_SESSION['username'] ?? 'system';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    Database::insert(Database::system(), 'audit_log', [
        'user_id' => $userId,
        'username' => $username,
        'action' => $action,
        'details' => $details,
        'ip_address' => $ip
    ]);
}

/**
 * Set a flash message.
 */
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Get and clear flash message.
 */
function getFlash(): ?array {
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

/**
 * Check if the current request is POST.
 */
function isPost(): bool {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/**
 * Get POST value safely.
 */
function post(string $key, $default = ''): string {
    return trim($_POST[$key] ?? $default);
}

/**
 * Get GET value safely.
 */
function get(string $key, $default = ''): string {
    return trim($_GET[$key] ?? $default);
}

/**
 * Sanitize input string.
 */
function sanitize(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Archive a report (soft delete) and remove from active reports.
 */
function archiveReport(int $machineSlot, int $reportId, string $deletedBy): bool {
    $db = Database::machine($machineSlot);

    $report = Database::fetchOne($db, "SELECT * FROM reports WHERE id = ?", [$reportId]);
    if (!$report) return false;

    // Archive the report
    $archivedId = Database::insert($db, 'deleted_reports', [
        'original_report_id' => $report['id'],
        'event_id' => $report['event_id'],
        'user_id' => $report['user_id'],
        'username' => $report['username'],
        'machine_slot' => $report['machine_slot'],
        'status' => $report['status'],
        'notes' => $report['notes'],
        'incident_time' => $report['incident_time'],
        'reaction_time_seconds' => $report['reaction_time_seconds'],
        'created_at' => $report['created_at'],
        'deleted_by' => $deletedBy
    ]);

    // Archive field data
    $fieldData = Database::fetchAll($db, "SELECT * FROM report_data WHERE report_id = ?", [$reportId]);
    foreach ($fieldData as $fd) {
        Database::insert($db, 'deleted_report_data', [
            'deleted_report_id' => $archivedId,
            'field_id' => $fd['field_id'],
            'field_name' => $fd['field_name'],
            'field_value' => $fd['field_value']
        ]);
    }

    // Remove from active tables
    Database::query($db, "DELETE FROM report_data WHERE report_id = ?", [$reportId]);
    Database::query($db, "DELETE FROM reports WHERE id = ?", [$reportId]);

    return true;
}

/**
 * Restore a deleted report from archive.
 */
function restoreReport(int $machineSlot, int $deletedReportId): bool {
    $db = Database::machine($machineSlot);

    $archived = Database::fetchOne($db, "SELECT * FROM deleted_reports WHERE id = ?", [$deletedReportId]);
    if (!$archived) return false;

    // Restore the report
    $newReportId = Database::insert($db, 'reports', [
        'event_id' => $archived['event_id'],
        'user_id' => $archived['user_id'],
        'username' => $archived['username'],
        'machine_slot' => $archived['machine_slot'],
        'status' => $archived['status'],
        'notes' => $archived['notes'],
        'incident_time' => $archived['incident_time'],
        'reaction_time_seconds' => $archived['reaction_time_seconds'],
        'created_at' => $archived['created_at']
    ]);

    // Restore field data
    $archivedData = Database::fetchAll($db, "SELECT * FROM deleted_report_data WHERE deleted_report_id = ?", [$deletedReportId]);
    foreach ($archivedData as $fd) {
        Database::insert($db, 'report_data', [
            'report_id' => $newReportId,
            'field_id' => $fd['field_id'],
            'field_name' => $fd['field_name'],
            'field_value' => $fd['field_value']
        ]);
    }

    // Remove from archive
    Database::query($db, "DELETE FROM deleted_report_data WHERE deleted_report_id = ?", [$deletedReportId]);
    Database::query($db, "DELETE FROM deleted_reports WHERE id = ?", [$deletedReportId]);

    return true;
}
