<?php
/**
 * SOC Reporting System - Dashboard
 * Shows 4 machine cards, reaction times, and recent activity.
 */

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'delete_report') {
    Auth::verifyCsrf();
    $machineSlot = (int) post('machine', 0);
    $delId = (int) post('report_id', 0);
    if ($machineSlot > 0 && $delId > 0) {
        try {
            $db = Database::machine($machineSlot);
            $delReport = Database::fetchOne($db, "SELECT event_id FROM reports WHERE id = ?", [$delId]);
            if ($delReport && archiveReport($machineSlot, $delId, $currentUser['username'])) {
                auditLog('report_deleted', "Deleted report {$delReport['event_id']} from machine slot $machineSlot");
                setFlash('success', label("Report {$delReport['event_id']} deleted.", "Bericht {$delReport['event_id']} gelöscht."));
            }
        } catch (Exception $e) {
            setFlash('error', label('Failed to delete report.', 'Fehler beim Löschen des Berichts.'));
        }
    }
    redirect('index.php?page=dashboard');
}

// Get report counts and latest timestamps per machine
$machineStats = [];
foreach ($machines as $machine) {
    $slot = $machine['slot_number'];
    try {
        $db = Database::machine($slot);
        $stats = Database::fetchOne($db,
            "SELECT COUNT(*) as total, MAX(created_at) as latest, MIN(created_at) as earliest FROM reports"
        );

        // Today's count
        $todayCount = Database::fetchOne($db,
            "SELECT COUNT(*) as cnt FROM reports WHERE DATE(created_at) = CURDATE()"
        );

        // Average reaction time (incident_time → created_at)
        $reactionTime = null;
        $avgReaction = Database::fetchOne($db,
            "SELECT AVG(reaction_time_seconds) as avg_seconds FROM reports WHERE reaction_time_seconds IS NOT NULL"
        );
        if ($avgReaction && $avgReaction['avg_seconds'] !== null) {
            $reactionTime = round($avgReaction['avg_seconds']);
        }

        $machineStats[$slot] = [
            'total' => $stats['total'] ?? 0,
            'latest' => $stats['latest'],
            'today' => $todayCount['cnt'] ?? 0,
            'reaction_time' => $reactionTime
        ];
    } catch (Exception $e) {
        $machineStats[$slot] = [
            'total' => 0,
            'latest' => null,
            'today' => 0,
            'reaction_time' => null
        ];
    }
}

// Calculate overall MTTC average
$mttcOverallAvg = null;
$mttcTotalSeconds = 0;
$mttcTotalCount = 0;
foreach ($machines as $machine) {
    $slot = $machine['slot_number'];
    try {
        $db = Database::machine($slot);
        $result = Database::fetchOne($db,
            "SELECT SUM(reaction_time_seconds) as total_seconds, COUNT(*) as count
             FROM reports WHERE reaction_time_seconds IS NOT NULL"
        );
        if ($result && $result['total_seconds'] !== null) {
            $mttcTotalSeconds += (int)$result['total_seconds'];
            $mttcTotalCount += (int)$result['count'];
        }
    } catch (Exception $e) { continue; }
}
if ($mttcTotalCount > 0) {
    $avgSeconds = floor($mttcTotalSeconds / $mttcTotalCount);
    $hours = floor($avgSeconds / 3600);
    $minutes = floor(($avgSeconds % 3600) / 60);
    if ($hours > 0) {
        $mttcOverallAvg = $hours . 'h ' . $minutes . 'm';
    } else {
        $mttcOverallAvg = $minutes . 'm';
    }
}

// Calculate MTTR (Mean Time To Resolve) for each machine
$mttrStats = [];
$mttrTotalSeconds = 0;
$mttrTotalCount = 0;
foreach ($machines as $machine) {
    $slot = $machine['slot_number'];
    try {
        $db = Database::machine($slot);
        $mttr = Database::fetchOne($db,
            "SELECT AVG(TIMESTAMPDIFF(SECOND, created_at, resolution_datetime)) as avg_seconds,
                    COUNT(*) as count
             FROM reports
             WHERE department_status = 'resolved'
             AND resolution_datetime IS NOT NULL"
        );

        if ($mttr && $mttr['avg_seconds'] !== null) {
            $avgSeconds = (int)$mttr['avg_seconds'];
            $hours = floor($avgSeconds / 3600);
            $minutes = floor(($avgSeconds % 3600) / 60);

            if ($hours > 0) {
                $mttrStats[$slot] = $hours . 'h ' . $minutes . 'm';
            } else {
                $mttrStats[$slot] = $minutes . 'm';
            }

            // For overall average
            $mttrTotalSeconds += $avgSeconds * (int)$mttr['count'];
            $mttrTotalCount += (int)$mttr['count'];
        } else {
            $mttrStats[$slot] = null;
        }
    } catch (Exception $e) {
        $mttrStats[$slot] = null;
    }
}

// Calculate overall MTTR average
$mttrOverallAvg = null;
if ($mttrTotalCount > 0) {
    $avgSeconds = floor($mttrTotalSeconds / $mttrTotalCount);
    $hours = floor($avgSeconds / 3600);
    $minutes = floor(($avgSeconds % 3600) / 60);
    if ($hours > 0) {
        $mttrOverallAvg = $hours . 'h ' . $minutes . 'm';
    } else {
        $mttrOverallAvg = $minutes . 'm';
    }
}

$cardColors = ['cyan', 'green', 'amber', 'red'];
$cardIcons = ['&#9881;', '&#9881;', '&#9881;', '&#9881;'];
?>

<script>document.getElementById('pageTitle').textContent = '<?= label('Dashboard', 'Dashboard') ?>';</script>

<!-- MTTC Overview -->
<div class="reaction-time">
    <div class="flex-between">
        <div>
            <h3 style="font-size: 1rem; font-weight: 600;"><?= label('MTTC Overview', 'MTTC-Übersicht') ?></h3>
            <p class="text-muted" style="font-size: 0.8rem; margin-top: 2px;">
                <?= label('Mean Time To Contain - Average reaction time per machine (incident to report)', 'Durchschnittliche Eindämmungszeit pro Maschine (Vorfall bis Bericht)') ?>
            </p>
        </div>
    </div>
    <div class="reaction-time-grid">
        <?php foreach ($machines as $i => $machine):
            $slot = $machine['slot_number'];
            $rt = $machineStats[$slot]['reaction_time'];
            $display = '--';
            if ($rt !== null) {
                $minutes = round($rt / 60, 1);
                $display = $minutes . ' ' . label('min', 'Min');
            }
        ?>
        <div class="reaction-time-item">
            <div class="reaction-time-value"><?= $display ?></div>
            <div class="reaction-time-label"><?= e($machine['name']) ?></div>
        </div>
        <?php endforeach; ?>
        <!-- Overall Average -->
        <div class="reaction-time-item">
            <div class="reaction-time-value" style="color: var(--text-primary);"><?= $mttcOverallAvg ?? '--' ?></div>
            <div class="reaction-time-label"><?= label('Average All', 'Durchschnitt Alle') ?></div>
        </div>
    </div>
</div>

<!-- MTTR Overview -->
<div class="reaction-time">
    <div class="flex-between">
        <div>
            <h3 style="font-size: 1rem; font-weight: 600;"><?= label('MTTR Overview', 'MTTR-Übersicht') ?></h3>
            <p class="text-muted" style="font-size: 0.8rem; margin-top: 2px;">
                <?= label('Mean Time To Resolve - Average response time per machine (creation to resolution)', 'Durchschnittliche Lösungszeit pro Maschine (Erstellung bis Lösung)') ?>
            </p>
        </div>
    </div>
    <div class="reaction-time-grid">
        <?php foreach ($machines as $i => $machine):
            $slot = $machine['slot_number'];
            $mttr = $mttrStats[$slot];
            $display = $mttr ?? '--';
        ?>
        <div class="reaction-time-item">
            <div class="reaction-time-value" style="<?= $mttr ? 'color: var(--accent-success);' : '' ?>"><?= $display ?></div>
            <div class="reaction-time-label"><?= e($machine['name']) ?></div>
        </div>
        <?php endforeach; ?>
        <!-- Overall Average -->
        <div class="reaction-time-item">
            <div class="reaction-time-value" style="color: var(--text-primary);"><?= $mttrOverallAvg ?? '--' ?></div>
            <div class="reaction-time-label"><?= label('Average All', 'Durchschnitt Alle') ?></div>
        </div>
    </div>
</div>

<!-- Machine Cards -->
<div class="dashboard-grid">
    <?php foreach ($machines as $i => $machine):
        $slot = $machine['slot_number'];
        $stats = $machineStats[$slot];
        $color = $cardColors[$i % 4];
    ?>
    <div class="card machine-card" onclick="window.location='index.php?page=reports_list&machine=<?= $slot ?>'">
        <div class="card-header">
            <div class="card-icon <?= $color ?>"><?= $cardIcons[$i % 4] ?></div>
            <?php if ($stats['today'] > 0): ?>
            <span class="badge badge-submitted"><?= label('Today', 'Heute') ?>: <?= $stats['today'] ?></span>
            <?php endif; ?>
        </div>
        <div class="card-title"><?= e($machine['name']) ?></div>
        <div class="card-value"><?= number_format($stats['total']) ?></div>
        <div class="card-label"><?= label('Total Reports', 'Berichte Gesamt') ?></div>
        <div class="machine-card-actions">
            <a href="index.php?page=report_create&machine=<?= $slot ?>" class="btn btn-primary btn-sm"
               onclick="event.stopPropagation();">
                + <?= label('New Report', 'Neuer Bericht') ?>
            </a>
            <a href="index.php?page=reports_list&machine=<?= $slot ?>" class="btn btn-secondary btn-sm"
               onclick="event.stopPropagation();">
                <?= label('View All', 'Alle anzeigen') ?>
            </a>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Recent Reports -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><?= label('Recent Reports', 'Neueste Berichte') ?></h3>
    </div>
    <div class="table-container" style="border: none;">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="white-space:nowrap"><?= label('Event ID', 'Ereignis-ID') ?></th>
                    <th style="white-space:nowrap"><?= label('Machine', 'Maschine') ?></th>
                    <th><?= label('User', 'Benutzer') ?></th>
                    <th style="white-space:nowrap"><?= label('Timestamp', 'Zeitstempel') ?></th>
                    <th><?= label('Status', 'Status') ?></th>
                    <th style="white-space:nowrap"><?= label('Department', 'Abteilung') ?></th>
                    <th style="white-space:nowrap"><?= label('Response Time', 'Reaktionszeit') ?></th>
                    <th style="white-space:nowrap;text-align:right"><?= label('Actions', 'Aktionen') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $recentReports = [];
                foreach ($machines as $machine) {
                    $slot = $machine['slot_number'];
                    try {
                        $db = Database::machine($slot);
                        $reports = Database::fetchAll($db,
                            "SELECT *, $slot as machine_slot FROM reports ORDER BY created_at DESC LIMIT 5"
                        );
                        foreach ($reports as &$r) {
                            $r['machine_name'] = $machine['name'];
                            $r['machine_slot'] = $slot;
                        }
                        $recentReports = array_merge($recentReports, $reports);
                    } catch (Exception $e) {
                        // Machine DB not set up yet
                    }
                }
                // Sort by created_at desc and limit to 10
                usort($recentReports, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));
                $recentReports = array_slice($recentReports, 0, 10);
                ?>

                <?php if (empty($recentReports)): ?>
                <tr>
                    <td colspan="8" class="text-center text-muted" style="padding: 40px;">
                        <?= label('No reports yet. Create your first report!', 'Noch keine Berichte. Erstellen Sie Ihren ersten Bericht!') ?>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($recentReports as $report):
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
                <tr class="machine-row-<?= $report['machine_slot'] ?>">
                    <td onclick="window.location='index.php?page=report_view&machine=<?= $report['machine_slot'] ?>&id=<?= $report['id'] ?>'" style="cursor:pointer;white-space:nowrap"><span class="event-id"><?= e($report['event_id']) ?></span></td>
                    <td onclick="window.location='index.php?page=report_view&machine=<?= $report['machine_slot'] ?>&id=<?= $report['id'] ?>'" style="cursor:pointer;white-space:nowrap"><span class="machine-name"><span class="machine-color-dot machine-<?= $report['machine_slot'] ?>"></span><?= e($report['machine_name']) ?></span></td>
                    <td onclick="window.location='index.php?page=report_view&machine=<?= $report['machine_slot'] ?>&id=<?= $report['id'] ?>'" style="cursor:pointer"><?= e($report['username']) ?></td>
                    <td onclick="window.location='index.php?page=report_view&machine=<?= $report['machine_slot'] ?>&id=<?= $report['id'] ?>'" style="cursor:pointer;white-space:nowrap"><span class="timestamp"><?= formatDatetime($report['created_at']) ?></span></td>
                    <td onclick="window.location='index.php?page=report_view&machine=<?= $report['machine_slot'] ?>&id=<?= $report['id'] ?>'" style="cursor: pointer;"><span class="badge badge-<?= e($report['status']) ?>"><?= e(ucfirst($report['status'])) ?></span></td>
                    <td>
                        <select class="dept-status-select dept-status-<?= e($report['department_status']) ?>"
                                data-machine="<?= $report['machine_slot'] ?>"
                                data-report-id="<?= $report['id'] ?>"
                                onchange="updateDepartmentStatus(this, <?= $report['machine_slot'] ?>, <?= $report['id'] ?>)">
                            <option value="informed" <?= $report['department_status'] === 'informed' ? 'selected' : '' ?>><?= label('Informed', 'Informiert') ?></option>
                            <option value="resolved" <?= $report['department_status'] === 'resolved' ? 'selected' : '' ?>><?= label('Resolved', 'Gelöst') ?></option>
                        </select>
                    </td>
                    <td onclick="window.location='index.php?page=report_view&machine=<?= $report['machine_slot'] ?>&id=<?= $report['id'] ?>'" style="cursor:pointer;white-space:nowrap">
                        <span class="response-time" style="<?= $responseTime !== '--' ? 'color: var(--accent-success); font-weight: 600;' : 'color: var(--text-muted);' ?>">
                            <?= $responseTime ?>
                        </span>
                    </td>
                    <td class="text-right" style="white-space: nowrap;">
                        <a href="index.php?page=report_view&machine=<?= $report['machine_slot'] ?>&id=<?= $report['id'] ?>" class="btn btn-secondary btn-sm">
                            <?= label('View', 'Ansehen') ?>
                        </a>
                        <a href="index.php?page=api_export_pdf&machine=<?= $report['machine_slot'] ?>&id=<?= $report['id'] ?>" class="btn btn-secondary btn-sm" target="_blank">
                            PDF
                        </a>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                            <input type="hidden" name="action" value="delete_report">
                            <input type="hidden" name="machine" value="<?= $report['machine_slot'] ?>">
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
</div>

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
