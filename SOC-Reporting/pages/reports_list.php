<?php
/**
 * SOC Reporting System - Reports List with Filtering
 */

$machineSlot = (int) get('machine', 0);
if ($machineSlot < 1 || $machineSlot > MACHINE_SLOTS) {
    setFlash('error', label('Invalid machine.', 'Ungültige Maschine.'));
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

// Filter parameters
$filterEventId = get('event_id');
$filterDateFrom = get('date_from');
$filterDateTo = get('date_to');
$filterStatus = get('status');
$filterUser = get('user');
$currentPageNum = max(1, (int) get('p', 1));

// Build query
$where = [];
$params = [];

if ($filterEventId) {
    $where[] = "event_id LIKE ?";
    $params[] = "%$filterEventId%";
}
if ($filterDateFrom) {
    $where[] = "DATE(created_at) >= ?";
    $params[] = $filterDateFrom;
}
if ($filterDateTo) {
    $where[] = "DATE(created_at) <= ?";
    $params[] = $filterDateTo;
}
if ($filterStatus) {
    $where[] = "status = ?";
    $params[] = $filterStatus;
}
if ($filterUser) {
    $where[] = "username LIKE ?";
    $params[] = "%$filterUser%";
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

try {
    $db = Database::machine($machineSlot);

    // Count total
    $countResult = Database::fetchOne($db, "SELECT COUNT(*) as total FROM reports $whereClause", $params);
    $totalReports = $countResult['total'] ?? 0;
    $totalPages = max(1, ceil($totalReports / REPORTS_PER_PAGE));
    $offset = ($currentPageNum - 1) * REPORTS_PER_PAGE;

    // Fetch reports
    $reports = Database::fetchAll($db,
        "SELECT * FROM reports $whereClause ORDER BY created_at DESC LIMIT " . REPORTS_PER_PAGE . " OFFSET $offset",
        $params
    );
} catch (Exception $e) {
    $reports = [];
    $totalReports = 0;
    $totalPages = 1;
}
?>

<script>document.getElementById('pageTitle').textContent = '<?= e($machine['name']) ?> - <?= label('Reports', 'Berichte') ?>';</script>

<div class="page-header flex-between">
    <div>
        <h2 class="page-title"><?= e($machine['name']) ?></h2>
        <p class="page-subtitle">
            <?= $totalReports ?> <?= label('reports total', 'Berichte insgesamt') ?>
        </p>
    </div>
    <a href="index.php?page=report_create&machine=<?= $machineSlot ?>" class="btn btn-primary">
        + <?= label('New Report', 'Neuer Bericht') ?>
    </a>
</div>

<!-- Filters -->
<form method="GET" class="filters-bar" id="reportsFilterForm">
    <input type="hidden" name="page" value="reports_list">
    <input type="hidden" name="machine" value="<?= $machineSlot ?>">
    <input type="hidden" name="date_from" id="rlDateFrom" value="<?= e($filterDateFrom) ?>">
    <input type="hidden" name="date_to" id="rlDateTo" value="<?= e($filterDateTo) ?>">

    <input type="text" name="event_id" class="filter-input" placeholder="<?= label('Event ID', 'Ereignis-ID') ?>"
           value="<?= e($filterEventId) ?>" style="width: 150px;">

    <div id="reportsTimePicker" style="display: inline-block; vertical-align: middle;"></div>

    <select name="status" class="filter-input" style="width: 140px;">
        <option value=""><?= label('All Status', 'Alle Status') ?></option>
        <option value="submitted" <?= $filterStatus === 'submitted' ? 'selected' : '' ?>><?= label('Submitted', 'Eingereicht') ?></option>
        <option value="reviewed" <?= $filterStatus === 'reviewed' ? 'selected' : '' ?>><?= label('Reviewed', 'Überprüft') ?></option>
        <option value="draft" <?= $filterStatus === 'draft' ? 'selected' : '' ?>><?= label('Draft', 'Entwurf') ?></option>
        <option value="closed" <?= $filterStatus === 'closed' ? 'selected' : '' ?>><?= label('Closed', 'Geschlossen') ?></option>
    </select>

    <input type="text" name="user" class="filter-input" placeholder="<?= label('Username', 'Benutzername') ?>"
           value="<?= e($filterUser) ?>" style="width: 140px;">

    <button type="submit" class="btn btn-primary btn-sm"><?= label('Filter', 'Filtern') ?></button>
    <a href="index.php?page=reports_list&machine=<?= $machineSlot ?>" class="btn btn-secondary btn-sm"><?= label('Clear', 'Zurücksetzen') ?></a>
</form>

<script src="assets/js/timepicker.js"></script>
<script>
(function() {
    var isEn = <?= currentLang() === 'en' ? 'true' : 'false' ?>;
    var existingFrom = '<?= e($filterDateFrom) ?>';
    var existingTo = '<?= e($filterDateTo) ?>';

    // Determine initial label based on existing filter
    var initialLabel = isEn ? 'All Time' : 'Gesamte Zeit';
    var initialPreset = 'all';
    if (existingFrom && existingTo) {
        initialLabel = existingFrom + ' \u2192 ' + existingTo;
        initialPreset = 'custom';
    } else if (existingFrom) {
        initialLabel = existingFrom + ' \u2192 ' + (isEn ? 'now' : 'jetzt');
        initialPreset = 'custom';
    }

    createTimePicker(document.getElementById('reportsTimePicker'), {
        labelEn: isEn,
        alignLeft: true,
        initial: { preset: initialPreset, label: initialLabel },
        onChange: function(sel) {
            document.getElementById('rlDateFrom').value = sel.date_from || '';
            document.getElementById('rlDateTo').value = sel.date_to || '';
            document.getElementById('reportsFilterForm').submit();
        }
    });
})();
</script>

<!-- Reports Table -->
<div class="table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th style="white-space:nowrap"><?= label('Event ID', 'Ereignis-ID') ?></th>
                <th style="white-space:nowrap"><?= label('Timestamp', 'Zeitstempel') ?></th>
                <th><?= label('User', 'Benutzer') ?></th>
                <th><?= label('Status', 'Status') ?></th>
                <th style="white-space:nowrap"><?= label('Department', 'Abteilung') ?></th>
                <th style="white-space:nowrap"><?= label('Response Time', 'Reaktionszeit') ?></th>
                <th style="white-space:nowrap;text-align:right"><?= label('Actions', 'Aktionen') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($reports)): ?>
            <tr>
                <td colspan="7" class="text-center text-muted" style="padding: 40px;">
                    <?= label('No reports found.', 'Keine Berichte gefunden.') ?>
                </td>
            </tr>
            <?php else: ?>
            <?php foreach ($reports as $report):
                // Calculate response time if resolved
                $responseTime = '--';
                if ($report['department_status'] === 'resolved' && $report['resolution_datetime']) {
                    $createdTime = strtotime($report['created_at']);
                    $resolvedTime = strtotime($report['resolution_datetime']);
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
            ?>
            <tr>
                <td style="white-space:nowrap">
                    <a href="index.php?page=report_view&machine=<?= $machineSlot ?>&id=<?= $report['id'] ?>" class="event-id">
                        <?= e($report['event_id']) ?>
                    </a>
                </td>
                <td style="white-space:nowrap"><span class="timestamp"><?= formatDatetime($report['created_at']) ?></span></td>
                <td><?= e($report['username']) ?></td>
                <td><span class="badge badge-<?= e($report['status']) ?>"><?= e(ucfirst($report['status'])) ?></span></td>
                <td>
                    <select class="dept-status-select dept-status-<?= e($report['department_status']) ?>"
                            data-machine="<?= $machineSlot ?>"
                            data-report-id="<?= $report['id'] ?>"
                            onchange="updateDepartmentStatus(this, <?= $machineSlot ?>, <?= $report['id'] ?>)">
                        <option value="informed" <?= $report['department_status'] === 'informed' ? 'selected' : '' ?>><?= label('Informed', 'Informiert') ?></option>
                        <option value="resolved" <?= $report['department_status'] === 'resolved' ? 'selected' : '' ?>><?= label('Resolved', 'Gelöst') ?></option>
                    </select>
                </td>
                <td style="white-space:nowrap">
                    <span class="response-time" style="<?= $responseTime !== '--' ? 'color: var(--accent-success); font-weight: 600;' : 'color: var(--text-muted);' ?>">
                        <?= $responseTime ?>
                    </span>
                </td>
                <td class="text-right" style="white-space: nowrap;">
                    <a href="index.php?page=report_view&machine=<?= $machineSlot ?>&id=<?= $report['id'] ?>" class="btn btn-secondary btn-sm">
                        <?= label('View', 'Ansehen') ?>
                    </a>
                    <a href="index.php?page=api_export_pdf&machine=<?= $machineSlot ?>&id=<?= $report['id'] ?>" class="btn btn-secondary btn-sm" target="_blank">
                        PDF
                    </a>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="action" value="delete_report">
                        <input type="hidden" name="report_id" value="<?= $report['id'] ?>">
                        <button type="button" class="btn btn-danger btn-sm btn-delete-confirm"
                                data-confirm-text="<?= label('Confirm?', 'Bestätigen?') ?>"
                                data-original-text="<?= label('Delete', 'Löschen') ?>">
                            <?= label('Delete', 'Löschen') ?>
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="pagination">
    <?php
    $queryParams = $_GET;
    unset($queryParams['p']);
    $baseUrl = 'index.php?' . http_build_query($queryParams);
    ?>
    <a href="<?= $baseUrl ?>&p=<?= max(1, $currentPageNum - 1) ?>"
       class="page-link <?= $currentPageNum <= 1 ? 'disabled' : '' ?>">&laquo;</a>

    <?php
    $startPage = max(1, $currentPageNum - 2);
    $endPage = min($totalPages, $currentPageNum + 2);
    for ($p = $startPage; $p <= $endPage; $p++):
    ?>
    <a href="<?= $baseUrl ?>&p=<?= $p ?>"
       class="page-link <?= $p === $currentPageNum ? 'active' : '' ?>"><?= $p ?></a>
    <?php endfor; ?>

    <a href="<?= $baseUrl ?>&p=<?= min($totalPages, $currentPageNum + 1) ?>"
       class="page-link <?= $currentPageNum >= $totalPages ? 'disabled' : '' ?>">&raquo;</a>
</div>
<?php endif; ?>

<!-- Resolution Time Modal -->
<div id="resolutionModal" class="modal-overlay" style="display: none;">
    <div class="modal-content" style="max-width: 400px;">
        <h3><?= label('Resolution Time', 'Lösungszeit') ?></h3>
        <p class="text-muted" style="margin-bottom: 20px; font-size: 0.9rem;">
            <?= label('When did you receive the answer/resolution?', 'Wann haben Sie die Antwort/Lösung erhalten?') ?>
        </p>

        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 0.9rem;">
                <?= label('Date and Time', 'Datum und Uhrzeit') ?>
            </label>
            <input type="datetime-local" id="resolutionDateTime" style="width: 100%; padding: 8px; border: 1px solid var(--border-light); background: var(--bg-input); color: var(--text-primary); border-radius: 4px; font-size: 0.9rem;">
        </div>

        <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
            <button onclick="closeResolutionModal()" class="btn btn-secondary">
                <?= label('Cancel', 'Abbrechen') ?>
            </button>
            <button onclick="confirmResolution()" class="btn btn-primary">
                <?= label('Confirm', 'Bestätigen') ?>
            </button>
        </div>
    </div>
</div>

<style>
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
}

.modal-content {
    background: var(--bg-secondary);
    border: 1px solid var(--border-light);
    border-radius: 8px;
    padding: 24px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
}

.modal-content h3 {
    margin: 0 0 8px 0;
    font-size: 1.25rem;
    color: var(--text-primary);
}

.dept-status-select {
    padding: 4px 8px;
    border-radius: 4px;
    border: 1px solid;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.dept-status-informed {
    background-color: var(--accent-warning-dim);
    color: var(--accent-warning);
    border-color: var(--accent-warning);
}

.dept-status-resolved {
    background-color: var(--accent-success-dim);
    color: var(--accent-success);
    border-color: var(--accent-success);
}

.dept-status-select:hover {
    opacity: 0.8;
}
</style>

<script>
let pendingResolution = null;

function updateDepartmentStatus(selectElement, machineSlot, reportId) {
    const newStatus = selectElement.value;
    const oldStatus = selectElement.getAttribute('data-old-status') || selectElement.options[selectElement.selectedIndex === 0 ? 1 : 0].value;

    // If changing to resolved, show modal for resolution time
    if (newStatus === 'resolved') {
        // Store pending change info
        pendingResolution = {
            selectElement: selectElement,
            machineSlot: machineSlot,
            reportId: reportId,
            oldStatus: oldStatus
        };

        // Set current datetime as default
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const datetimeLocal = `${year}-${month}-${day}T${hours}:${minutes}`;
        document.getElementById('resolutionDateTime').value = datetimeLocal;

        // Show modal
        document.getElementById('resolutionModal').style.display = 'flex';
        return;
    }

    // For changing to informed, just confirm
    const statusLabels = {
        'informed': '<?= label("Informed", "Informiert") ?>',
        'resolved': '<?= label("Resolved", "Gelöst") ?>'
    };

    const confirmMsg = '<?= label("Are you sure you want to change the status to", "Möchten Sie den Status wirklich ändern auf") ?> "' + statusLabels[newStatus] + '"?';

    if (!confirm(confirmMsg)) {
        selectElement.value = oldStatus;
        return;
    }

    performStatusUpdate(selectElement, machineSlot, reportId, newStatus, oldStatus, null);
}

function closeResolutionModal() {
    if (pendingResolution) {
        // Revert selection
        pendingResolution.selectElement.value = pendingResolution.oldStatus;
        pendingResolution = null;
    }
    document.getElementById('resolutionModal').style.display = 'none';
}

function confirmResolution() {
    const resolutionDatetime = document.getElementById('resolutionDateTime').value;

    if (!resolutionDatetime) {
        alert('<?= label("Please select a date and time", "Bitte wählen Sie Datum und Uhrzeit") ?>');
        return;
    }

    if (pendingResolution) {
        performStatusUpdate(
            pendingResolution.selectElement,
            pendingResolution.machineSlot,
            pendingResolution.reportId,
            'resolved',
            pendingResolution.oldStatus,
            resolutionDatetime
        );
        pendingResolution = null;
    }

    document.getElementById('resolutionModal').style.display = 'none';
}

function performStatusUpdate(selectElement, machineSlot, reportId, newStatus, oldStatus, resolutionDatetime) {
    const payload = {
        machine: machineSlot,
        report_id: reportId,
        department_status: newStatus
    };

    if (resolutionDatetime) {
        payload.resolution_datetime = resolutionDatetime;
    }

    fetch('index.php?page=api_update_dept_status', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(payload)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update color class
            selectElement.className = 'dept-status-select dept-status-' + newStatus;
            selectElement.setAttribute('data-old-status', newStatus);

            // If resolved, update response time in the same row
            if (newStatus === 'resolved' && data.response_time) {
                const row = selectElement.closest('tr');
                const responseTimeCell = row.querySelector('.response-time');
                if (responseTimeCell) {
                    responseTimeCell.textContent = data.response_time;
                    responseTimeCell.style.color = 'var(--accent-success)';
                    responseTimeCell.style.fontWeight = '600';
                }
            }
        } else {
            alert('<?= label("Error updating status", "Fehler beim Aktualisieren des Status") ?>: ' + (data.error || '<?= label("Unknown error", "Unbekannter Fehler") ?>'));
            // Revert selection
            selectElement.value = oldStatus;
        }
    })
    .catch(error => {
        alert('<?= label("Error updating status", "Fehler beim Aktualisieren des Status") ?>: ' + error);
        // Revert selection
        selectElement.value = oldStatus;
    });
}

// Store initial status on page load
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.dept-status-select').forEach(function(select) {
        select.setAttribute('data-old-status', select.value);
    });
});

// Close modal on ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.getElementById('resolutionModal').style.display === 'flex') {
        closeResolutionModal();
    }
});
</script>
