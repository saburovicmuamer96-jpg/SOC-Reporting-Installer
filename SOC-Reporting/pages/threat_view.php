<?php
/**
 * SOC Reporting System - View Single Threat/CVE Log
 */

$threatId = (int) get('id', 0);

if ($threatId < 1) {
    setFlash('error', label('Invalid threat log.', 'Ungültiges Bedrohungsprotokoll.'));
    redirect('index.php?page=threat_logs');
}

// Handle delete
if (isPost() && post('action') === 'delete_threat') {
    if (Auth::validateCsrf(post('csrf_token'))) {
        $delId = (int) post('threat_id');
        try {
            $db = Database::system();
            $delThreat = Database::fetchOne($db, "SELECT cve_id FROM threat_logs WHERE id = ?", [$delId]);
            if ($delThreat) {
                Database::query($db, "DELETE FROM threat_logs WHERE id = ?", [$delId]);
                auditLog('threat_deleted', "Deleted threat log {$delThreat['cve_id']}");
                setFlash('success', label("Threat log {$delThreat['cve_id']} deleted.", "Bedrohungsprotokoll {$delThreat['cve_id']} gelöscht."));
            }
        } catch (Exception $e) {
            setFlash('error', label('Failed to delete threat log.', 'Bedrohungsprotokoll konnte nicht gelöscht werden.'));
        }
        redirect('index.php?page=threat_logs');
    }
}

try {
    $db = Database::system();
    $threat = Database::fetchOne($db, "SELECT * FROM threat_logs WHERE id = ?", [$threatId]);

    if (!$threat) {
        setFlash('error', label('Threat log not found.', 'Bedrohungsprotokoll nicht gefunden.'));
        redirect('index.php?page=threat_logs');
    }

} catch (Exception $e) {
    setFlash('error', label('Error loading threat log.', 'Fehler beim Laden des Bedrohungsprotokolls.'));
    redirect('index.php?page=threat_logs');
}

// Severity colors
$severityColors = [
    'critical' => 'red',
    'high' => 'amber',
    'medium' => 'cyan',
    'low' => 'green',
    'info' => 'default'
];
$severityColor = $severityColors[$threat['severity']] ?? 'default';

// Status mapping
$statusMap = [
    'new' => ['badge' => 'draft', 'label' => label('New', 'Neu')],
    'notified' => ['badge' => 'submitted', 'label' => label('Notified', 'Benachrichtigt')],
    'patched' => ['badge' => 'reviewed', 'label' => label('Patched', 'Gepatcht')],
    'mitigated' => ['badge' => 'reviewed', 'label' => label('Mitigated', 'Entschärft')],
    'closed' => ['badge' => 'closed', 'label' => label('Closed', 'Geschlossen')]
];
$statusInfo = $statusMap[$threat['status']] ?? $statusMap['new'];
?>

<script>document.getElementById('pageTitle').textContent = '<?= e($threat['cve_id']) ?>';</script>

<div class="page-header flex-between">
    <div>
        <h2 class="page-title"><?= label('Threat Log Details', 'Bedrohungsprotokoll-Details') ?></h2>
        <p class="page-subtitle"><?= e($threat['cve_id']) ?></p>
    </div>
    <div class="flex gap-1">
        <a href="index.php?page=threat_logs" class="btn btn-secondary">
            &larr; <?= label('Back', 'Zurück') ?>
        </a>
        <a href="index.php?page=api_export_threat_pdf&id=<?= $threatId ?>" target="_blank" class="btn btn-secondary">
            &#128196; <?= label('PDF', 'PDF') ?>
        </a>
        <form method="POST" style="display: inline;">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="delete_threat">
            <input type="hidden" name="threat_id" value="<?= $threatId ?>">
            <button type="button" class="btn btn-danger btn-delete-confirm"
                    data-confirm-text="<?= label('Confirm Delete?', 'Löschen bestätigen?') ?>"
                    data-original-text="<?= label('Delete', 'Löschen') ?>">
                <?= label('Delete', 'Löschen') ?>
            </button>
        </form>
    </div>
</div>

<!-- Threat Header with Key Info -->
<div class="card" style="margin-bottom: 24px;">
    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 20px;">
        <div style="flex: 1;">
            <h3 style="margin: 0 0 8px 0; font-size: 1.4rem; color: var(--text-primary);">
                <?= e($threat['title']) ?>
            </h3>
            <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                <span class="event-id" style="font-size: 1rem;"><?= e($threat['cve_id']) ?></span>
                <span class="badge badge-<?= $severityColor ?>" style="background: var(--accent-<?= $severityColor === 'default' ? 'primary' : $severityColor ?>-dim); color: var(--accent-<?= $severityColor === 'default' ? 'primary' : $severityColor ?>); font-size: 0.95rem; padding: 6px 12px;">
                    <?= strtoupper($threat['severity']) ?>
                </span>
                <span class="badge badge-<?= $statusInfo['badge'] ?>" style="font-size: 0.95rem; padding: 6px 12px;">
                    <?= $statusInfo['label'] ?>
                </span>
                <?php if ($threat['cvss_score']): ?>
                <span style="font-family: var(--font-mono); font-size: 1.1rem; font-weight: 600; color: var(--accent-<?= $threat['cvss_score'] >= 9.0 ? 'danger' : ($threat['cvss_score'] >= 7.0 ? 'warning' : 'primary') ?>);">
                    CVSS: <?= number_format($threat['cvss_score'], 1) ?>
                </span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Threat Meta Information -->
<div class="report-header">
    <div class="report-meta" style="width: 100%;">
        <div class="report-meta-item">
            <div class="report-meta-label"><?= label('CVE ID', 'CVE ID') ?></div>
            <div class="report-meta-value" style="font-family: var(--font-mono);"><?= e($threat['cve_id']) ?></div>
        </div>
        <div class="report-meta-item">
            <div class="report-meta-label"><?= label('Severity', 'Schweregrad') ?></div>
            <div class="report-meta-value">
                <span class="badge badge-<?= $severityColor ?>" style="background: var(--accent-<?= $severityColor === 'default' ? 'primary' : $severityColor ?>-dim); color: var(--accent-<?= $severityColor === 'default' ? 'primary' : $severityColor ?>);">
                    <?= strtoupper($threat['severity']) ?>
                </span>
            </div>
        </div>
        <div class="report-meta-item">
            <div class="report-meta-label"><?= label('CVSS Score', 'CVSS-Score') ?></div>
            <div class="report-meta-value" style="font-family: var(--font-mono); color: var(--accent-primary);">
                <?= $threat['cvss_score'] ? number_format($threat['cvss_score'], 1) : '--' ?>
            </div>
        </div>
        <div class="report-meta-item">
            <div class="report-meta-label"><?= label('Status', 'Status') ?></div>
            <div><span class="badge badge-<?= $statusInfo['badge'] ?>"><?= $statusInfo['label'] ?></span></div>
        </div>
        <div class="report-meta-item">
            <div class="report-meta-label"><?= label('Published Date', 'Veröffentlichungsdatum') ?></div>
            <div class="report-meta-value">
                <?= $threat['published_date'] ? date('Y-m-d', strtotime($threat['published_date'])) : '--' ?>
            </div>
        </div>
        <div class="report-meta-item">
            <div class="report-meta-label"><?= label('Notified At', 'Benachrichtigt am') ?></div>
            <div class="report-meta-value">
                <?= $threat['notified_at'] ? formatDatetime($threat['notified_at']) : '--' ?>
            </div>
        </div>
        <div class="report-meta-item">
            <div class="report-meta-label"><?= label('Patched At', 'Gepatcht am') ?></div>
            <div class="report-meta-value">
                <?= $threat['patched_at'] ? formatDatetime($threat['patched_at']) : '--' ?>
            </div>
        </div>
        <div class="report-meta-item">
            <div class="report-meta-label"><?= label('Mitigated At', 'Entschärft am') ?></div>
            <div class="report-meta-value">
                <?= $threat['mitigated_at'] ? formatDatetime($threat['mitigated_at']) : '--' ?>
            </div>
        </div>
        <div class="report-meta-item">
            <div class="report-meta-label"><?= label('Closed At', 'Geschlossen am') ?></div>
            <div class="report-meta-value">
                <?= $threat['closed_at'] ? formatDatetime($threat['closed_at']) : '--' ?>
            </div>
        </div>
        <div class="report-meta-item">
            <div class="report-meta-label"><?= label('Created By', 'Erstellt von') ?></div>
            <div class="report-meta-value"><?= e($threat['created_by']) ?></div>
        </div>
        <div class="report-meta-item">
            <div class="report-meta-label"><?= label('Created At', 'Erstellt am') ?></div>
            <div class="report-meta-value"><?= formatDatetime($threat['created_at']) ?></div>
        </div>
    </div>
</div>

<!-- Threat Details -->
<div class="report-fields">
    <!-- Description -->
    <div class="report-field full-width">
        <div class="report-field-label"><?= label('Description', 'Beschreibung') ?></div>
        <div class="report-field-value"><?= nl2br(e($threat['description'])) ?></div>
    </div>

    <!-- Affected Systems -->
    <?php if ($threat['affected_systems']): ?>
    <div class="report-field full-width">
        <div class="report-field-label"><?= label('Affected Systems', 'Betroffene Systeme') ?></div>
        <div class="report-field-value"><?= nl2br(e($threat['affected_systems'])) ?></div>
    </div>
    <?php endif; ?>

    <!-- Departments Notified -->
    <?php if ($threat['departments_notified']): ?>
    <div class="report-field">
        <div class="report-field-label"><?= label('Departments Notified', 'Benachrichtigte Abteilungen') ?></div>
        <div class="report-field-value"><?= e($threat['departments_notified']) ?></div>
    </div>
    <?php endif; ?>

    <!-- Mitigation -->
    <?php if ($threat['mitigation']): ?>
    <div class="report-field full-width">
        <div class="report-field-label"><?= label('Mitigation / Remediation', 'Abhilfe / Behebung') ?></div>
        <div class="report-field-value"><?= nl2br(e($threat['mitigation'])) ?></div>
    </div>
    <?php endif; ?>

    <!-- Updated At -->
    <div class="report-field">
        <div class="report-field-label"><?= label('Last Updated', 'Zuletzt aktualisiert') ?></div>
        <div class="report-field-value"><?= formatDatetime($threat['updated_at']) ?></div>
    </div>
</div>

<style>
.badge-red { background: var(--accent-danger-dim); color: var(--accent-danger); }
.badge-amber { background: var(--accent-warning-dim); color: var(--accent-warning); }
.badge-green { background: var(--accent-success-dim); color: var(--accent-success); }
.badge-cyan { background: var(--accent-primary-dim); color: var(--accent-primary); }
</style>
