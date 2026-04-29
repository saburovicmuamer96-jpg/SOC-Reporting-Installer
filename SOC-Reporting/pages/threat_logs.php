<?php
/**
 * SOC Reporting System - Threat Logs Dashboard
 * Manages CVE/threat notifications sent to departments
 */

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'delete_threat') {
    Auth::verifyCsrf();
    $threatId = (int) post('threat_id', 0);
    if ($threatId > 0) {
        try {
            $db = Database::system();
            Database::query($db, "DELETE FROM threat_logs WHERE id = ?", [$threatId]);
            auditLog('threat_deleted', "Deleted threat log ID $threatId");
            setFlash('success', label('Threat log deleted.', 'Bedrohungsprotokoll gelöscht.'));
        } catch (Exception $e) {
            setFlash('error', label('Failed to delete threat log.', 'Fehler beim Löschen.'));
        }
    }
    redirect('index.php?page=threat_logs');
}

// Get all threat logs
$db = Database::system();
$threats = Database::fetchAll($db, "
    SELECT * FROM threat_logs
    ORDER BY created_at DESC, severity DESC
");

// Count by severity
$severityCounts = [
    'critical' => 0,
    'high' => 0,
    'medium' => 0,
    'low' => 0,
    'info' => 0
];

foreach ($threats as $threat) {
    if (isset($severityCounts[$threat['severity']])) {
        $severityCounts[$threat['severity']]++;
    }
}

$statusCounts = [
    'new' => 0,
    'notified' => 0,
    'patched' => 0,
    'mitigated' => 0,
    'closed' => 0
];

foreach ($threats as $threat) {
    if (isset($statusCounts[$threat['status']])) {
        $statusCounts[$threat['status']]++;
    }
}
?>

<script>document.getElementById('pageTitle').textContent = '<?= label('Threat Intelligence', 'Bedrohungsinformationen') ?>';</script>

<!-- Stats Row -->
<div class="dashboard-grid" style="margin-bottom: 32px;">
    <div class="card">
        <div class="card-icon red">⚠</div>
        <div class="card-value"><?= $severityCounts['critical'] ?></div>
        <div class="card-label"><?= label('Critical', 'Kritisch') ?></div>
    </div>
    <div class="card">
        <div class="card-icon amber">⚡</div>
        <div class="card-value"><?= $severityCounts['high'] ?></div>
        <div class="card-label"><?= label('High Severity', 'Hoch') ?></div>
    </div>
    <div class="card">
        <div class="card-icon cyan">📋</div>
        <div class="card-value"><?= $statusCounts['notified'] ?></div>
        <div class="card-label"><?= label('Notified', 'Benachrichtigt') ?></div>
    </div>
    <div class="card">
        <div class="card-icon green">✓</div>
        <div class="card-value"><?= $statusCounts['patched'] + $statusCounts['mitigated'] ?></div>
        <div class="card-label"><?= label('Resolved', 'Gelöst') ?></div>
    </div>
</div>

<!-- Add New Threat Button -->
<div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
    <h2 style="margin: 0; font-size: 1.3rem; color: var(--text-primary);">
        <?= label('CVE / Threat Logs', 'CVE / Bedrohungsprotokolle') ?>
    </h2>
    <a href="index.php?page=threat_create" class="btn btn-primary">
        ➕ <?= label('Log New Threat', 'Neue Bedrohung protokollieren') ?>
    </a>
</div>

<!-- Threats Table -->
<div class="table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th><?= label('CVE ID', 'CVE ID') ?></th>
                <th><?= label('Title', 'Titel') ?></th>
                <th><?= label('Severity', 'Schweregrad') ?></th>
                <th><?= label('CVSS', 'CVSS') ?></th>
                <th><?= label('Status', 'Status') ?></th>
                <th><?= label('Departments', 'Abteilungen') ?></th>
                <th><?= label('Published', 'Veröffentlicht') ?></th>
                <th><?= label('Notified', 'Benachrichtigt') ?></th>
                <th><?= label('Actions', 'Aktionen') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($threats)): ?>
            <tr>
                <td colspan="9" style="text-align: center; padding: 40px; color: var(--text-muted);">
                    <?= label('No threat logs yet. Click "Log New Threat" to add one.', 'Noch keine Bedrohungsprotokolle. Klicken Sie auf "Neue Bedrohung protokollieren", um eines hinzuzufügen.') ?>
                </td>
            </tr>
            <?php else: ?>
                <?php foreach ($threats as $threat): ?>
                <tr>
                    <td>
                        <span class="event-id"><?= e($threat['cve_id']) ?></span>
                    </td>
                    <td>
                        <strong><?= e($threat['title']) ?></strong>
                    </td>
                    <td>
                        <?php
                        $severityColors = [
                            'critical' => 'red',
                            'high' => 'amber',
                            'medium' => 'cyan',
                            'low' => 'green',
                            'info' => 'default'
                        ];
                        $color = $severityColors[$threat['severity']] ?? 'default';
                        ?>
                        <span class="badge badge-<?= $color ?>" style="background: var(--accent-<?= $color === 'default' ? 'primary' : $color ?>-dim); color: var(--accent-<?= $color === 'default' ? 'primary' : $color ?>);">
                            <?= strtoupper($threat['severity']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($threat['cvss_score']): ?>
                            <strong style="color: var(--accent-<?= $threat['cvss_score'] >= 9.0 ? 'danger' : ($threat['cvss_score'] >= 7.0 ? 'warning' : 'primary') ?>);">
                                <?= number_format($threat['cvss_score'], 1) ?>
                            </strong>
                        <?php else: ?>
                            <span style="color: var(--text-muted);">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <select class="threat-status-select status-<?= e($threat['status']) ?>"
                                data-threat-id="<?= $threat['id'] ?>"
                                onchange="updateThreatStatus(this)">
                            <option value="new" <?= $threat['status'] === 'new' ? 'selected' : '' ?>><?= label('New', 'Neu') ?></option>
                            <option value="notified" <?= $threat['status'] === 'notified' ? 'selected' : '' ?>><?= label('Notified', 'Benachrichtigt') ?></option>
                            <option value="patched" <?= $threat['status'] === 'patched' ? 'selected' : '' ?>><?= label('Patched', 'Gepatcht') ?></option>
                            <option value="mitigated" <?= $threat['status'] === 'mitigated' ? 'selected' : '' ?>><?= label('Mitigated', 'Entschärft') ?></option>
                            <option value="closed" <?= $threat['status'] === 'closed' ? 'selected' : '' ?>><?= label('Closed', 'Geschlossen') ?></option>
                        </select>
                    </td>
                    <td>
                        <?= $threat['departments_notified'] ? e($threat['departments_notified']) : '<span style="color: var(--text-muted);">—</span>' ?>
                    </td>
                    <td>
                        <?= $threat['published_date'] ? date('Y-m-d', strtotime($threat['published_date'])) : '<span style="color: var(--text-muted);">—</span>' ?>
                    </td>
                    <td>
                        <?= $threat['notified_at'] ? date('Y-m-d H:i', strtotime($threat['notified_at'])) : '<span style="color: var(--text-muted);">—</span>' ?>
                    </td>
                    <td>
                        <div style="display: flex; gap: 8px;">
                            <a href="index.php?page=threat_view&id=<?= $threat['id'] ?>" class="btn btn-secondary btn-sm">
                                👁 <?= label('View', 'Ansehen') ?>
                            </a>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="action" value="delete_threat">
                                <input type="hidden" name="threat_id" value="<?= $threat['id'] ?>">
                                <button type="button" class="btn btn-danger btn-sm btn-delete-confirm"
                                        data-confirm-text="<?= label('Confirm?', 'Bestätigen?') ?>"
                                        data-original-text="<?= label('Delete', 'Löschen') ?>">
                                    <?= label('Delete', 'Löschen') ?>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
.badge-red { background: var(--accent-danger-dim); color: var(--accent-danger); }
.badge-amber { background: var(--accent-warning-dim); color: var(--accent-warning); }
.badge-green { background: var(--accent-success-dim); color: var(--accent-success); }
.badge-cyan { background: var(--accent-primary-dim); color: var(--accent-primary); }

.threat-status-select {
    background: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    padding: 5px 10px;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    transition: all 0.2s ease;
}
.threat-status-select:hover {
    border-color: var(--accent-primary);
}
.threat-status-select:focus {
    outline: none;
    border-color: var(--accent-primary);
    box-shadow: 0 0 0 2px rgba(0, 212, 255, 0.15);
}
.threat-status-select.status-new { border-color: var(--text-muted); color: var(--text-muted); }
.threat-status-select.status-notified { border-color: var(--accent-primary); color: var(--accent-primary); }
.threat-status-select.status-patched { border-color: var(--accent-success); color: var(--accent-success); }
.threat-status-select.status-mitigated { border-color: var(--accent-success); color: var(--accent-success); }
.threat-status-select.status-closed { border-color: var(--text-muted); color: var(--text-muted); }
</style>

<script>
function updateThreatStatus(select) {
    const threatId = select.dataset.threatId;
    const newStatus = select.value;

    // Remove old status class, add new
    select.className = 'threat-status-select status-' + newStatus;

    fetch('index.php?page=api_update_threat_status', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            threat_id: parseInt(threatId),
            status: newStatus
        })
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) {
            alert('Error: ' + (data.error || 'Unknown error'));
            location.reload();
        }
    })
    .catch(() => {
        alert('Network error');
        location.reload();
    });
}
</script>
