<?php
/**
 * SOC Reporting System - Machine Configuration (Admin)
 */

// Handle machine update
if (isPost() && post('action') === 'update_machine') {
    $token = post('csrf_token');
    if (Auth::validateCsrf($token)) {
        $machineId = (int) post('machine_id');
        Database::update(Database::system(), 'machines', [
            'name' => sanitize(post('name')),
            'description' => sanitize(post('description')),
            'is_active' => (int) post('is_active', 1)
        ], 'id = ?', [$machineId]);

        auditLog('machine_updated', "Machine $machineId updated");
        setFlash('success', label('Machine updated successfully.', 'Maschine erfolgreich aktualisiert.'));
    }
    $csrfToken = Auth::generateCsrf();
    redirect('index.php?page=machines');
}

$allMachines = Database::fetchAll(Database::system(),
    "SELECT * FROM machines ORDER BY slot_number"
);
?>

<script>document.getElementById('pageTitle').textContent = '<?= label('Machine Configuration', 'Maschinenkonfiguration') ?>';</script>

<div class="page-header flex-between">
    <div>
        <h2 class="page-title"><?= label('Machine Configuration', 'Maschinenkonfiguration') ?></h2>
        <p class="page-subtitle">
            <?= label('Edit machine names, descriptions, and status', 'Maschinennamen, Beschreibungen und Status bearbeiten') ?>
        </p>
    </div>
    <div class="flex gap-1">
        <a href="index.php?page=users" class="btn btn-secondary"><?= label('Users', 'Benutzer') ?></a>
        <a href="index.php?page=form_builder" class="btn btn-secondary"><?= label('Form Builder', 'Formular-Builder') ?></a>
    </div>
</div>

<div class="dashboard-grid" style="grid-template-columns: repeat(2, 1fr);">
    <?php foreach ($allMachines as $machine): ?>
    <div class="card">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="update_machine">
            <input type="hidden" name="machine_id" value="<?= $machine['id'] ?>">

            <div class="card-header">
                <div class="flex gap-1" style="align-items: center;">
                    <div class="card-icon cyan">&#9881;</div>
                    <span class="text-muted" style="font-size: 0.75rem; font-family: var(--font-mono);">
                        SLOT <?= $machine['slot_number'] ?>
                    </span>
                </div>
                <span class="badge <?= $machine['is_active'] ? 'badge-reviewed' : 'badge-closed' ?>">
                    <?= $machine['is_active'] ? label('Active', 'Aktiv') : label('Inactive', 'Inaktiv') ?>
                </span>
            </div>

            <div class="form-group">
                <label class="form-label"><?= label('Machine Name', 'Maschinenname') ?></label>
                <input type="text" name="name" class="form-input" value="<?= e($machine['name']) ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label"><?= label('Description', 'Beschreibung') ?></label>
                <textarea name="description" class="form-textarea" rows="3"><?= e($machine['description'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label"><?= label('Status', 'Status') ?></label>
                <select name="is_active" class="form-select">
                    <option value="1" <?= $machine['is_active'] ? 'selected' : '' ?>><?= label('Active', 'Aktiv') ?></option>
                    <option value="0" <?= !$machine['is_active'] ? 'selected' : '' ?>><?= label('Inactive', 'Inaktiv') ?></option>
                </select>
            </div>

            <div class="flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm"><?= label('Save', 'Speichern') ?></button>
                <a href="index.php?page=form_builder&machine=<?= $machine['slot_number'] ?>" class="btn btn-secondary btn-sm">
                    <?= label('Edit Fields', 'Felder bearbeiten') ?>
                </a>
            </div>
        </form>
    </div>
    <?php endforeach; ?>
</div>
