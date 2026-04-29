<?php
/**
 * SOC Reporting System - Deleted Reports Archive (Admin Only)
 * Shows all deleted reports across all machines with restore capability.
 */

$filterMachine = (int) get('machine', 0);

// Handle restore
if (isPost() && post('action') === 'restore_report') {
    if (Auth::validateCsrf(post('csrf_token'))) {
        $restoreId = (int) post('deleted_report_id');
        $restoreSlot = (int) post('machine_slot');
        try {
            if (restoreReport($restoreSlot, $restoreId)) {
                auditLog('report_restored', "Restored deleted report #$restoreId from machine slot $restoreSlot");
                setFlash('success', label('Report restored successfully.', 'Bericht erfolgreich wiederhergestellt.'));
            } else {
                setFlash('error', label('Report not found in archive.', 'Bericht nicht im Archiv gefunden.'));
            }
        } catch (Exception $e) {
            setFlash('error', label('Failed to restore report.', 'Bericht konnte nicht wiederhergestellt werden.'));
        }
        redirect("index.php?page=deleted_reports" . ($filterMachine ? "&machine=$filterMachine" : ""));
    }
}

// Handle permanent delete
if (isPost() && post('action') === 'permanent_delete') {
    if (Auth::validateCsrf(post('csrf_token'))) {
        $delId = (int) post('deleted_report_id');
        $delSlot = (int) post('machine_slot');
        try {
            $db = Database::machine($delSlot);
            $archived = Database::fetchOne($db, "SELECT event_id FROM deleted_reports WHERE id = ?", [$delId]);
            if ($archived) {
                Database::query($db, "DELETE FROM deleted_report_data WHERE deleted_report_id = ?", [$delId]);
                Database::query($db, "DELETE FROM deleted_reports WHERE id = ?", [$delId]);
                auditLog('report_permanently_deleted', "Permanently deleted report {$archived['event_id']} from machine slot $delSlot");
                setFlash('success', label('Report permanently deleted.', 'Bericht endgültig gelöscht.'));
            }
        } catch (Exception $e) {
            setFlash('error', label('Failed to permanently delete report.', 'Bericht konnte nicht endgültig gelöscht werden.'));
        }
        redirect("index.php?page=deleted_reports" . ($filterMachine ? "&machine=$filterMachine" : ""));
    }
}

// Gather deleted reports from all (or filtered) machines
$allMachines = Database::fetchAll(Database::system(), "SELECT * FROM machines ORDER BY slot_number");
$deletedReports = [];

foreach ($allMachines as $m) {
    $slot = $m['slot_number'];
    if ($filterMachine && $filterMachine !== $slot) continue;

    try {
        $db = Database::machine($slot);
        $reports = Database::fetchAll($db,
            "SELECT * FROM deleted_reports ORDER BY deleted_at DESC"
        );
        foreach ($reports as &$r) {
            $r['machine_name'] = $m['name'];
        }
        $deletedReports = array_merge($deletedReports, $reports);
    } catch (Exception $e) {
        // DB not available
    }
}

// Sort by deleted_at desc
usort($deletedReports, fn($a, $b) => strtotime($b['deleted_at']) - strtotime($a['deleted_at']));
?>

<script>document.getElementById('pageTitle').textContent = '<?= label('Deleted Reports', 'Gelöschte Berichte') ?>';</script>

<div class="page-header flex-between">
    <div>
        <h2 class="page-title"><?= label('Deleted Reports Archive', 'Archiv gelöschter Berichte') ?></h2>
        <p class="page-subtitle">
            <?= count($deletedReports) ?> <?= label('deleted reports', 'gelöschte Berichte') ?>
        </p>
    </div>
    <div class="flex gap-1">
        <select class="form-select" style="width: auto;" onchange="window.location='index.php?page=deleted_reports'+(this.value ? '&machine='+this.value : '')">
            <option value=""><?= label('All Machines', 'Alle Maschinen') ?></option>
            <?php foreach ($allMachines as $m): ?>
            <option value="<?= $m['slot_number'] ?>" <?= $filterMachine == $m['slot_number'] ? 'selected' : '' ?>>
                <?= e($m['name']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<div class="table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th><?= label('Event ID', 'Ereignis-ID') ?></th>
                <th><?= label('Machine', 'Maschine') ?></th>
                <th><?= label('Created', 'Erstellt') ?></th>
                <th><?= label('Deleted At', 'Gelöscht am') ?></th>
                <th><?= label('Deleted By', 'Gelöscht von') ?></th>
                <th><?= label('Original User', 'Urspr. Benutzer') ?></th>
                <th class="text-right"><?= label('Actions', 'Aktionen') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($deletedReports)): ?>
            <tr>
                <td colspan="7" class="text-center text-muted" style="padding: 40px;">
                    <?= label('No deleted reports found.', 'Keine gelöschten Berichte gefunden.') ?>
                </td>
            </tr>
            <?php else: ?>
            <?php foreach ($deletedReports as $dr): ?>
            <tr class="machine-row-<?= $dr['machine_slot'] ?>">
                <td><span class="event-id"><?= e($dr['event_id']) ?></span></td>
                <td><span class="machine-name"><span class="machine-color-dot machine-<?= $dr['machine_slot'] ?>"></span><?= e($dr['machine_name']) ?></span></td>
                <td><span class="timestamp"><?= formatDatetime($dr['created_at']) ?></span></td>
                <td><span class="timestamp"><?= formatDatetime($dr['deleted_at']) ?></span></td>
                <td><?= e($dr['deleted_by']) ?></td>
                <td><?= e($dr['username']) ?></td>
                <td class="text-right" style="white-space: nowrap;">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="action" value="restore_report">
                        <input type="hidden" name="deleted_report_id" value="<?= $dr['id'] ?>">
                        <input type="hidden" name="machine_slot" value="<?= $dr['machine_slot'] ?>">
                        <button type="button" class="btn btn-primary btn-sm btn-delete-confirm"
                                data-confirm-text="<?= label('Confirm?', 'Bestätigen?') ?>"
                                data-original-text="<?= label('Restore', 'Wiederherstellen') ?>">
                            <?= label('Restore', 'Wiederherstellen') ?>
                        </button>
                    </form>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="action" value="permanent_delete">
                        <input type="hidden" name="deleted_report_id" value="<?= $dr['id'] ?>">
                        <input type="hidden" name="machine_slot" value="<?= $dr['machine_slot'] ?>">
                        <button type="button" class="btn btn-danger btn-sm btn-delete-confirm"
                                data-confirm-text="<?= label('Confirm?', 'Bestätigen?') ?>"
                                data-original-text="<?= label('Permanent', 'Endgültig') ?>">
                            <?= label('Permanent', 'Endgültig') ?>
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
