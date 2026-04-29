<?php
/**
 * SOC Reporting System - View Single Report
 */

$machineSlot = (int) get('machine', 0);
$reportId = (int) get('id', 0);

if ($machineSlot < 1 || $machineSlot > MACHINE_SLOTS || $reportId < 1) {
    setFlash('error', label('Invalid report.', 'Ungültiger Bericht.'));
    redirect('index.php?page=dashboard');
}

$machine = getMachineBySlot($machineSlot);
if (!$machine) {
    setFlash('error', label('Machine not found.', 'Maschine nicht gefunden.'));
    redirect('index.php?page=dashboard');
}

// Handle delete
if (isPost() && post('action') === 'delete_report') {
    if (Auth::validateCsrf(post('csrf_token'))) {
        $delId = (int) post('report_id');
        try {
            $db = Database::machine($machineSlot);
            $delReport = Database::fetchOne($db, "SELECT event_id FROM reports WHERE id = ?", [$delId]);
            if ($delReport && archiveReport($machineSlot, $delId, $currentUser['username'])) {
                auditLog('report_deleted', "Deleted report {$delReport['event_id']} from machine slot $machineSlot");
                setFlash('success', label("Report {$delReport['event_id']} deleted.", "Bericht {$delReport['event_id']} gelöscht."));
            }
        } catch (Exception $e) {
            setFlash('error', label('Failed to delete report.', 'Bericht konnte nicht gelöscht werden.'));
        }
        redirect("index.php?page=reports_list&machine=$machineSlot");
    }
}

try {
    $db = Database::machine($machineSlot);
    $report = Database::fetchOne($db, "SELECT * FROM reports WHERE id = ?", [$reportId]);

    if (!$report) {
        setFlash('error', label('Report not found.', 'Bericht nicht gefunden.'));
        redirect("index.php?page=reports_list&machine=$machineSlot");
    }

    // Get report data
    $reportData = Database::fetchAll($db,
        "SELECT * FROM report_data WHERE report_id = ? ORDER BY id",
        [$reportId]
    );

    // Get field definitions for labels
    $formFields = getFormFields($machine['id']);
    $fieldMap = [];
    foreach ($formFields as $f) {
        $fieldMap[$f['id']] = $f;
    }

} catch (Exception $e) {
    setFlash('error', label('Error loading report.', 'Fehler beim Laden des Berichts.'));
    redirect("index.php?page=reports_list&machine=$machineSlot");
}
?>

<script>document.getElementById('pageTitle').textContent = '<?= e($report['event_id']) ?>';</script>

<div class="page-header flex-between">
    <div>
        <h2 class="page-title"><?= label('Report Details', 'Berichtdetails') ?></h2>
        <p class="page-subtitle"><?= e($machine['name']) ?></p>
    </div>
    <div class="flex gap-1">
        <a href="index.php?page=api_export_pdf&machine=<?= $machineSlot ?>&id=<?= $reportId ?>"
           class="btn btn-primary" target="_blank">
            &#8681; <?= label('Export PDF', 'PDF exportieren') ?>
        </a>
        <a href="index.php?page=reports_list&machine=<?= $machineSlot ?>" class="btn btn-secondary">
            &larr; <?= label('Back', 'Zurück') ?>
        </a>
        <form method="POST" style="display: inline;">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="delete_report">
            <input type="hidden" name="report_id" value="<?= $reportId ?>">
            <button type="button" class="btn btn-danger btn-delete-confirm"
                    data-confirm-text="<?= label('Confirm Delete?', 'Löschen bestätigen?') ?>"
                    data-original-text="<?= label('Delete', 'Löschen') ?>">
                <?= label('Delete', 'Löschen') ?>
            </button>
        </form>
    </div>
</div>

<!-- Report Meta -->
<div class="report-header">
    <div class="report-meta" style="width: 100%;">
        <div class="report-meta-item">
            <div class="report-meta-label"><?= label('Event ID', 'Ereignis-ID') ?></div>
            <div class="report-meta-value"><?= e($report['event_id']) ?></div>
        </div>
        <div class="report-meta-item">
            <div class="report-meta-label"><?= label('Incident Time', 'Vorfallzeit') ?></div>
            <div class="report-meta-value"><?= $report['incident_time'] ? formatDatetime($report['incident_time']) : '--' ?></div>
        </div>
        <div class="report-meta-item">
            <div class="report-meta-label"><?= label('Report Created', 'Bericht erstellt') ?></div>
            <div class="report-meta-value"><?= formatDatetime($report['created_at']) ?></div>
        </div>
        <div class="report-meta-item">
            <div class="report-meta-label"><?= label('Reaction Time', 'Reaktionszeit') ?></div>
            <div class="report-meta-value" style="color: var(--accent-primary); font-family: var(--font-mono);">
                <?php if ($report['reaction_time_seconds'] !== null): ?>
                    <?= round($report['reaction_time_seconds'] / 60, 1) ?> <?= label('min', 'Min') ?>
                <?php else: ?>
                    --
                <?php endif; ?>
            </div>
        </div>
        <div class="report-meta-item">
            <div class="report-meta-label"><?= label('Created By', 'Erstellt von') ?></div>
            <div class="report-meta-value"><?= e($report['username']) ?></div>
        </div>
        <div class="report-meta-item">
            <div class="report-meta-label"><?= label('Status', 'Status') ?></div>
            <div><span class="badge badge-<?= e($report['status']) ?>"><?= e(ucfirst($report['status'])) ?></span></div>
        </div>
    </div>
</div>

<!-- Report Fields -->
<div class="report-fields">
    <?php foreach ($reportData as $data):
        $fieldDef = $fieldMap[$data['field_id']] ?? null;
        $fieldLabelText = $fieldDef ? fieldLabel($fieldDef) : $data['field_name'];
        $isLong = $fieldDef && in_array($fieldDef['field_type'], ['textarea']);
    ?>
    <div class="report-field <?= $isLong ? 'full-width' : '' ?>">
        <div class="report-field-label"><?= e($fieldLabelText) ?></div>
        <div class="report-field-value"><?= nl2br(e($data['field_value'] ?: '--')) ?></div>
    </div>
    <?php endforeach; ?>

    <?php if ($report['notes']): ?>
    <div class="report-field full-width">
        <div class="report-field-label"><?= label('Additional Notes', 'Zusätzliche Anmerkungen') ?></div>
        <div class="report-field-value"><?= nl2br(e($report['notes'])) ?></div>
    </div>
    <?php endif; ?>
</div>

<?php if (empty($reportData) && !$report['notes']): ?>
<div class="empty-state">
    <div class="empty-state-icon">&#128196;</div>
    <h3 class="empty-state-title"><?= label('No field data', 'Keine Felddaten') ?></h3>
    <p class="empty-state-text">
        <?= label('This report has no form data attached.', 'Dieser Bericht hat keine angehängten Formulardaten.') ?>
    </p>
</div>
<?php endif; ?>
