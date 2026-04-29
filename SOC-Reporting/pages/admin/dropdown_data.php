<?php
/**
 * SOC Reporting System - Dropdown Data Editor (Admin)
 */

$machineSlot = (int) get('machine', 1);
$machine = getMachineBySlot($machineSlot);

if (!$machine) {
    setFlash('error', label('Machine not found.', 'Maschine nicht gefunden.'));
    redirect('index.php?page=machines');
}

$selectedFieldId = (int) get('field', 0);

// Get all dropdown fields for this machine
$dropdownFields = Database::fetchAll(Database::system(),
    "SELECT * FROM form_fields WHERE machine_id = ? AND field_type = 'dropdown' AND is_active = 1 ORDER BY field_order",
    [$machine['id']]
);

// Handle add option
if (isPost() && post('action') === 'add_option') {
    $token = post('csrf_token');
    if (Auth::validateCsrf($token)) {
        $fieldId = (int) post('field_id');
        $maxSort = Database::fetchOne(Database::system(),
            "SELECT MAX(sort_order) as max_sort FROM dropdown_options WHERE field_id = ?",
            [$fieldId]
        );

        Database::insert(Database::system(), 'dropdown_options', [
            'field_id' => $fieldId,
            'value' => sanitize(post('value')),
            'label_en' => sanitize(post('label_en')),
            'label_de' => sanitize(post('label_de')),
            'sort_order' => ($maxSort['max_sort'] ?? 0) + 1
        ]);

        auditLog('dropdown_option_added', "Added option to field $fieldId");
        setFlash('success', label('Option added.', 'Option hinzugefügt.'));
    }
    $csrfToken = Auth::generateCsrf();
    redirect("index.php?page=dropdown_data&machine=$machineSlot&field=" . post('field_id'));
}

// Handle update option
if (isPost() && post('action') === 'update_option') {
    $token = post('csrf_token');
    if (Auth::validateCsrf($token)) {
        $optId = (int) post('option_id');
        Database::update(Database::system(), 'dropdown_options', [
            'value' => sanitize(post('value')),
            'label_en' => sanitize(post('label_en')),
            'label_de' => sanitize(post('label_de')),
            'sort_order' => (int) post('sort_order')
        ], 'id = ?', [$optId]);

        auditLog('dropdown_option_updated', "Updated option $optId");
        setFlash('success', label('Option updated.', 'Option aktualisiert.'));
    }
    $csrfToken = Auth::generateCsrf();
    redirect("index.php?page=dropdown_data&machine=$machineSlot&field=$selectedFieldId");
}

// Handle delete option
if (isPost() && post('action') === 'delete_option') {
    $token = post('csrf_token');
    if (Auth::validateCsrf($token)) {
        $optId = (int) post('option_id');
        Database::update(Database::system(), 'dropdown_options',
            ['is_active' => 0], 'id = ?', [$optId]);

        auditLog('dropdown_option_deleted', "Deactivated option $optId");
        setFlash('success', label('Option removed.', 'Option entfernt.'));
    }
    $csrfToken = Auth::generateCsrf();
    redirect("index.php?page=dropdown_data&machine=$machineSlot&field=$selectedFieldId");
}

// Get options for selected field
$options = [];
$selectedField = null;
if ($selectedFieldId) {
    $selectedField = Database::fetchOne(Database::system(),
        "SELECT * FROM form_fields WHERE id = ? AND machine_id = ?",
        [$selectedFieldId, $machine['id']]
    );
    if ($selectedField) {
        $options = getDropdownOptions($selectedFieldId);
    }
}
?>

<script>document.getElementById('pageTitle').textContent = '<?= label('Dropdown Data Editor', 'Dropdown-Dateneditor') ?>';</script>

<div class="page-header flex-between">
    <div>
        <h2 class="page-title"><?= label('Dropdown Data Editor', 'Dropdown-Dateneditor') ?></h2>
        <p class="page-subtitle"><?= e($machine['name']) ?></p>
    </div>
    <a href="index.php?page=form_builder&machine=<?= $machineSlot ?>" class="btn btn-secondary">
        &larr; <?= label('Back to Form Builder', 'Zurück zum Formular-Builder') ?>
    </a>
</div>

<?php if (empty($dropdownFields)): ?>
<div class="empty-state">
    <div class="empty-state-icon">&#9776;</div>
    <h3 class="empty-state-title"><?= label('No Dropdown Fields', 'Keine Dropdown-Felder') ?></h3>
    <p class="empty-state-text">
        <?= label('Add a dropdown field in the Form Builder first.', 'Fügen Sie zuerst ein Dropdown-Feld im Formular-Builder hinzu.') ?>
    </p>
</div>
<?php else: ?>

<!-- Field Selector -->
<div class="card mb-3">
    <div class="flex gap-1" style="align-items: center; flex-wrap: wrap;">
        <span class="text-muted" style="font-size: 0.82rem; font-weight: 600;">
            <?= label('Select Field:', 'Feld auswählen:') ?>
        </span>
        <?php foreach ($dropdownFields as $df): ?>
        <a href="index.php?page=dropdown_data&machine=<?= $machineSlot ?>&field=<?= $df['id'] ?>"
           class="btn <?= $selectedFieldId == $df['id'] ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
            <?= e(fieldLabel($df)) ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<?php if ($selectedField): ?>

<!-- Add New Option -->
<div class="card mb-3">
    <h3 style="font-size: 0.9rem; font-weight: 600; margin-bottom: 16px;">
        <?= label('Add Option', 'Option hinzufügen') ?>
    </h3>
    <form method="POST" class="flex gap-1" style="flex-wrap: wrap; align-items: flex-end;">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="action" value="add_option">
        <input type="hidden" name="field_id" value="<?= $selectedFieldId ?>">

        <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 150px;">
            <label class="form-label"><?= label('Value', 'Wert') ?></label>
            <input type="text" name="value" class="form-input" required>
        </div>
        <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 150px;">
            <label class="form-label"><?= label('Label EN', 'Bezeichnung EN') ?></label>
            <input type="text" name="label_en" class="form-input" required>
        </div>
        <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 150px;">
            <label class="form-label"><?= label('Label DE', 'Bezeichnung DE') ?></label>
            <input type="text" name="label_de" class="form-input" required>
        </div>
        <button type="submit" class="btn btn-primary">+ <?= label('Add', 'Hinzufügen') ?></button>
    </form>
</div>

<!-- Options List -->
<div class="table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th><?= label('Order', 'Reihenfolge') ?></th>
                <th><?= label('Value', 'Wert') ?></th>
                <th><?= label('Label (EN)', 'Bezeichnung (EN)') ?></th>
                <th><?= label('Label (DE)', 'Bezeichnung (DE)') ?></th>
                <th class="text-right"><?= label('Actions', 'Aktionen') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($options)): ?>
            <tr>
                <td colspan="5" class="text-center text-muted" style="padding: 30px;">
                    <?= label('No options yet. Add one above.', 'Noch keine Optionen. Fügen Sie oben eine hinzu.') ?>
                </td>
            </tr>
            <?php else: ?>
            <?php foreach ($options as $opt): ?>
            <tr>
                <td>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="action" value="update_option">
                        <input type="hidden" name="option_id" value="<?= $opt['id'] ?>">
                        <input type="number" name="sort_order" value="<?= $opt['sort_order'] ?>" style="width: 60px;" class="filter-input"
                               onchange="this.form.submit()">
                        <input type="hidden" name="value" value="<?= e($opt['value']) ?>">
                        <input type="hidden" name="label_en" value="<?= e($opt['label_en']) ?>">
                        <input type="hidden" name="label_de" value="<?= e($opt['label_de']) ?>">
                    </form>
                </td>
                <td><code style="color: var(--accent-primary);"><?= e($opt['value']) ?></code></td>
                <td><?= e($opt['label_en']) ?></td>
                <td><?= e($opt['label_de']) ?></td>
                <td class="text-right">
                    <button class="btn btn-secondary btn-sm" onclick="editOption(<?= htmlspecialchars(json_encode($opt), ENT_QUOTES) ?>)">
                        <?= label('Edit', 'Bearbeiten') ?>
                    </button>
                    <form method="POST" style="display: inline;" onsubmit="return confirm('<?= label('Remove this option?', 'Diese Option entfernen?') ?>')">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="action" value="delete_option">
                        <input type="hidden" name="option_id" value="<?= $opt['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm"><?= label('Remove', 'Entfernen') ?></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Edit Option Modal -->
<div class="modal-overlay" id="editOptionModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title"><?= label('Edit Option', 'Option bearbeiten') ?></h3>
            <button class="modal-close" onclick="this.closest('.modal-overlay').classList.remove('active')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="update_option">
            <input type="hidden" name="option_id" id="editOptId">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label"><?= label('Value', 'Wert') ?></label>
                    <input type="text" name="value" id="editOptValue" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= label('Label (English)', 'Bezeichnung (Englisch)') ?></label>
                    <input type="text" name="label_en" id="editOptLabelEn" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= label('Label (German)', 'Bezeichnung (Deutsch)') ?></label>
                    <input type="text" name="label_de" id="editOptLabelDe" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= label('Sort Order', 'Sortierreihenfolge') ?></label>
                    <input type="number" name="sort_order" id="editOptSort" class="form-input">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="this.closest('.modal-overlay').classList.remove('active')">
                    <?= label('Cancel', 'Abbrechen') ?>
                </button>
                <button type="submit" class="btn btn-primary"><?= label('Save', 'Speichern') ?></button>
            </div>
        </form>
    </div>
</div>

<script>
function editOption(opt) {
    document.getElementById('editOptId').value = opt.id;
    document.getElementById('editOptValue').value = opt.value;
    document.getElementById('editOptLabelEn').value = opt.label_en;
    document.getElementById('editOptLabelDe').value = opt.label_de;
    document.getElementById('editOptSort').value = opt.sort_order;
    document.getElementById('editOptionModal').classList.add('active');
}
</script>

<?php endif; // selectedField ?>
<?php endif; // dropdownFields ?>
