<?php
/**
 * SOC Reporting System - Form Builder (Admin)
 * Visual interface to configure form fields per machine.
 */

$machineSlot = (int) get('machine', 1);
$machine = getMachineBySlot($machineSlot);

if (!$machine) {
    setFlash('error', label('Machine not found.', 'Maschine nicht gefunden.'));
    redirect('index.php?page=machines');
}

// Handle add field
if (isPost() && post('action') === 'add_field') {
    $token = post('csrf_token');
    if (Auth::validateCsrf($token)) {
        // Get max order
        $maxOrder = Database::fetchOne(Database::system(),
            "SELECT MAX(field_order) as max_order FROM form_fields WHERE machine_id = ?",
            [$machine['id']]
        );
        $nextOrder = ($maxOrder['max_order'] ?? 0) + 1;

        $fieldName = preg_replace('/[^a-z0-9_]/', '_', strtolower(post('field_name')));
        $optionsJson = null;

        if (post('field_type') === 'checkbox') {
            $checkboxLabels = post('checkbox_labels');
            if ($checkboxLabels) {
                $options = [];
                foreach (explode("\n", $checkboxLabels) as $line) {
                    $line = trim($line);
                    if ($line) {
                        $parts = explode('|', $line);
                        $options[] = [
                            'value' => $parts[0],
                            'label_en' => $parts[0],
                            'label_de' => $parts[1] ?? $parts[0],
                            'label' => $parts[0]
                        ];
                    }
                }
                $optionsJson = json_encode($options);
            }
        }

        Database::insert(Database::system(), 'form_fields', [
            'machine_id' => $machine['id'],
            'field_name' => $fieldName,
            'field_label_en' => sanitize(post('field_label_en')),
            'field_label_de' => sanitize(post('field_label_de')),
            'field_type' => post('field_type'),
            'field_order' => $nextOrder,
            'is_required' => (int) post('is_required', 0),
            'placeholder_en' => sanitize(post('placeholder_en')),
            'placeholder_de' => sanitize(post('placeholder_de')),
            'options_json' => $optionsJson
        ]);

        auditLog('field_added', "Added field '$fieldName' to machine {$machine['name']}");
        setFlash('success', label('Field added successfully.', 'Feld erfolgreich hinzugefügt.'));
    }
    $csrfToken = Auth::generateCsrf();
    redirect("index.php?page=form_builder&machine=$machineSlot");
}

// Handle delete field
if (isPost() && post('action') === 'delete_field') {
    $token = post('csrf_token');
    if (Auth::validateCsrf($token)) {
        $fieldId = (int) post('field_id');
        Database::update(Database::system(), 'form_fields',
            ['is_active' => 0], 'id = ?', [$fieldId]);
        auditLog('field_deleted', "Deactivated field $fieldId from machine {$machine['name']}");
        setFlash('success', label('Field removed.', 'Feld entfernt.'));
    }
    $csrfToken = Auth::generateCsrf();
    redirect("index.php?page=form_builder&machine=$machineSlot");
}

// Handle reorder
if (isPost() && post('action') === 'reorder') {
    $token = post('csrf_token');
    if (Auth::validateCsrf($token)) {
        $order = json_decode(post('field_order'), true);
        if (is_array($order)) {
            foreach ($order as $index => $fieldId) {
                Database::update(Database::system(), 'form_fields',
                    ['field_order' => $index], 'id = ?', [(int) $fieldId]);
            }
            auditLog('fields_reordered', "Reordered fields for machine {$machine['name']}");
        }
    }
    $csrfToken = Auth::generateCsrf();
    redirect("index.php?page=form_builder&machine=$machineSlot");
}

// Handle update field
if (isPost() && post('action') === 'update_field') {
    $token = post('csrf_token');
    if (Auth::validateCsrf($token)) {
        $fieldId = (int) post('field_id');
        $updateData = [
            'field_label_en' => sanitize(post('field_label_en')),
            'field_label_de' => sanitize(post('field_label_de')),
            'is_required' => (int) post('is_required', 0),
            'placeholder_en' => sanitize(post('placeholder_en')),
            'placeholder_de' => sanitize(post('placeholder_de'))
        ];

        // Update checkbox options if applicable
        $field = Database::fetchOne(Database::system(), "SELECT * FROM form_fields WHERE id = ?", [$fieldId]);
        if ($field && $field['field_type'] === 'checkbox') {
            $checkboxLabels = post('checkbox_labels');
            if ($checkboxLabels) {
                $options = [];
                foreach (explode("\n", $checkboxLabels) as $line) {
                    $line = trim($line);
                    if ($line) {
                        $parts = explode('|', $line);
                        $options[] = [
                            'value' => $parts[0],
                            'label_en' => $parts[0],
                            'label_de' => $parts[1] ?? $parts[0],
                            'label' => $parts[0]
                        ];
                    }
                }
                $updateData['options_json'] = json_encode($options);
            }
        }

        Database::update(Database::system(), 'form_fields', $updateData, 'id = ?', [$fieldId]);
        auditLog('field_updated', "Updated field $fieldId");
        setFlash('success', label('Field updated.', 'Feld aktualisiert.'));
    }
    $csrfToken = Auth::generateCsrf();
    redirect("index.php?page=form_builder&machine=$machineSlot");
}

$fields = getFormFields($machine['id']);
$allMachines = Database::fetchAll(Database::system(), "SELECT * FROM machines ORDER BY slot_number");
?>

<script>document.getElementById('pageTitle').textContent = '<?= label('Form Builder', 'Formular-Builder') ?> - <?= e($machine['name']) ?>';</script>

<div class="page-header flex-between">
    <div>
        <h2 class="page-title"><?= label('Form Builder', 'Formular-Builder') ?></h2>
        <p class="page-subtitle"><?= e($machine['name']) ?> - <?= count($fields) ?> <?= label('fields', 'Felder') ?></p>
    </div>
    <div class="flex gap-1">
        <!-- Machine Selector -->
        <select class="form-select" style="width: auto;" onchange="window.location='index.php?page=form_builder&machine='+this.value">
            <?php foreach ($allMachines as $m): ?>
            <option value="<?= $m['slot_number'] ?>" <?= $m['slot_number'] == $machineSlot ? 'selected' : '' ?>>
                <?= e($m['name']) ?>
            </option>
            <?php endforeach; ?>
        </select>
        <a href="index.php?page=dropdown_data&machine=<?= $machineSlot ?>" class="btn btn-secondary">
            <?= label('Dropdown Data', 'Dropdown-Daten') ?>
        </a>
    </div>
</div>

<div class="builder-container">
    <!-- Toolbox (Add Field) -->
    <div class="builder-toolbox">
        <h3 style="font-size: 0.9rem; font-weight: 600; margin-bottom: 16px;">
            <?= label('Add Field', 'Feld hinzufügen') ?>
        </h3>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="add_field">

            <div class="form-group">
                <label class="form-label"><?= label('Field Type', 'Feldtyp') ?></label>
                <select name="field_type" id="newFieldType" class="form-select" onchange="toggleCheckboxOptions()" required>
                    <option value="text"><?= label('Text Input', 'Texteingabe') ?></option>
                    <option value="textarea"><?= label('Text Area', 'Textbereich') ?></option>
                    <option value="number"><?= label('Number', 'Nummer') ?></option>
                    <option value="dropdown"><?= label('Dropdown', 'Dropdown') ?></option>
                    <option value="checkbox"><?= label('Checkbox', 'Checkbox') ?></option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label"><?= label('Field Name (ID)', 'Feldname (ID)') ?></label>
                <input type="text" name="field_name" class="form-input" required
                       pattern="[a-zA-Z0-9_ ]+" placeholder="e.g. incident_type">
            </div>

            <div class="form-group">
                <label class="form-label"><?= label('Label (English)', 'Bezeichnung (Englisch)') ?></label>
                <input type="text" name="field_label_en" class="form-input" required placeholder="e.g. Incident Type">
            </div>

            <div class="form-group">
                <label class="form-label"><?= label('Label (German)', 'Bezeichnung (Deutsch)') ?></label>
                <input type="text" name="field_label_de" class="form-input" required placeholder="z.B. Vorfallstyp">
            </div>

            <div class="form-group">
                <label class="form-label"><?= label('Placeholder EN', 'Platzhalter EN') ?></label>
                <input type="text" name="placeholder_en" class="form-input">
            </div>

            <div class="form-group">
                <label class="form-label"><?= label('Placeholder DE', 'Platzhalter DE') ?></label>
                <input type="text" name="placeholder_de" class="form-input">
            </div>

            <div class="form-group" id="checkboxOptionsGroup" style="display: none;">
                <label class="form-label"><?= label('Checkbox Options', 'Checkbox-Optionen') ?></label>
                <textarea name="checkbox_labels" class="form-textarea" rows="4"
                          placeholder="<?= label('One per line: EN|DE', 'Eine pro Zeile: EN|DE') ?>"></textarea>
                <span class="form-hint"><?= label('Format: English Label|German Label', 'Format: Englische Bezeichnung|Deutsche Bezeichnung') ?></span>
            </div>

            <div class="form-group">
                <label class="form-checkbox">
                    <input type="checkbox" name="is_required" value="1">
                    <?= label('Required field', 'Pflichtfeld') ?>
                </label>
            </div>

            <button type="submit" class="btn btn-primary btn-block">
                + <?= label('Add Field', 'Feld hinzufügen') ?>
            </button>
        </form>

        <?php if (count($fields) > 1): ?>
        <div style="margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--border-color);">
            <h4 style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 8px;">
                <?= label('Reorder Fields', 'Felder neu ordnen') ?>
            </h4>
            <p class="form-hint"><?= label('Drag fields in the canvas to reorder, then save.', 'Ziehen Sie Felder auf der Leinwand, um sie neu zu ordnen, und speichern Sie dann.') ?></p>
            <form method="POST" id="reorderForm">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action" value="reorder">
                <input type="hidden" name="field_order" id="fieldOrderInput">
                <button type="submit" class="btn btn-secondary btn-block btn-sm mt-1" onclick="saveOrder()">
                    <?= label('Save Order', 'Reihenfolge speichern') ?>
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <!-- Canvas (Current Fields) -->
    <div class="builder-canvas" id="builderCanvas">
        <?php if (empty($fields)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">&#9998;</div>
            <h3 class="empty-state-title"><?= label('No Fields Yet', 'Noch keine Felder') ?></h3>
            <p class="empty-state-text">
                <?= label('Use the toolbox on the left to add form fields.', 'Verwenden Sie die Toolbox links, um Formularfelder hinzuzufügen.') ?>
            </p>
        </div>
        <?php else: ?>
        <?php foreach ($fields as $field): ?>
        <div class="builder-field" draggable="true" data-field-id="<?= $field['id'] ?>">
            <div class="builder-field-header">
                <div class="flex gap-1" style="align-items: center;">
                    <span class="builder-field-type"><?= e($field['field_type']) ?></span>
                    <strong style="font-size: 0.85rem;"><?= e(fieldLabel($field)) ?></strong>
                    <?php if ($field['is_required']): ?>
                    <span class="required-mark">*</span>
                    <?php endif; ?>
                </div>
                <div class="builder-field-actions">
                    <button class="builder-field-btn" title="<?= label('Edit', 'Bearbeiten') ?>"
                            onclick="editField(<?= htmlspecialchars(json_encode($field), ENT_QUOTES) ?>)">&#9998;</button>
                    <form method="POST" style="display: inline;" onsubmit="return confirm('<?= label('Remove this field?', 'Dieses Feld entfernen?') ?>')">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="action" value="delete_field">
                        <input type="hidden" name="field_id" value="<?= $field['id'] ?>">
                        <button type="submit" class="builder-field-btn delete" title="<?= label('Remove', 'Entfernen') ?>">&times;</button>
                    </form>
                </div>
            </div>
            <div style="font-size: 0.78rem; color: var(--text-muted);">
                <?= e($field['field_name']) ?>
                <?php if ($field['field_type'] === 'dropdown'): ?>
                    &middot; <a href="index.php?page=dropdown_data&machine=<?= $machineSlot ?>&field=<?= $field['id'] ?>"
                       style="font-size: 0.78rem;"><?= label('Edit Options', 'Optionen bearbeiten') ?></a>
                <?php endif; ?>
            </div>

            <!-- Preview -->
            <div style="margin-top: 10px; padding: 10px; background: var(--bg-input); border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                <?php if ($field['field_type'] === 'text' || $field['field_type'] === 'number'): ?>
                <input type="<?= $field['field_type'] ?>" class="form-input" disabled
                       placeholder="<?= e(fieldPlaceholder($field) ?: fieldLabel($field)) ?>"
                       style="opacity: 0.5; pointer-events: none;">
                <?php elseif ($field['field_type'] === 'textarea'): ?>
                <textarea class="form-textarea" disabled style="opacity: 0.5; pointer-events: none; min-height: 50px;"
                          placeholder="<?= e(fieldPlaceholder($field) ?: fieldLabel($field)) ?>"></textarea>
                <?php elseif ($field['field_type'] === 'dropdown'): ?>
                <select class="form-select" disabled style="opacity: 0.5; pointer-events: none;">
                    <option><?= label('-- Select --', '-- Auswählen --') ?></option>
                </select>
                <?php elseif ($field['field_type'] === 'checkbox'): ?>
                <div style="opacity: 0.5; display: flex; gap: 12px; flex-wrap: wrap;">
                    <?php
                    $opts = json_decode($field['options_json'] ?? '[]', true) ?: [];
                    foreach ($opts as $opt):
                    ?>
                    <label style="font-size: 0.82rem; display: flex; align-items: center; gap: 4px;">
                        <input type="checkbox" disabled> <?= e($opt['label'] ?? $opt['label_en'] ?? '') ?>
                    </label>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Field Modal -->
<div class="modal-overlay" id="editFieldModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title"><?= label('Edit Field', 'Feld bearbeiten') ?></h3>
            <button class="modal-close" onclick="this.closest('.modal-overlay').classList.remove('active')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="update_field">
            <input type="hidden" name="field_id" id="editFieldId">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label"><?= label('Label (English)', 'Bezeichnung (Englisch)') ?></label>
                    <input type="text" name="field_label_en" id="editLabelEn" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= label('Label (German)', 'Bezeichnung (Deutsch)') ?></label>
                    <input type="text" name="field_label_de" id="editLabelDe" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= label('Placeholder EN', 'Platzhalter EN') ?></label>
                    <input type="text" name="placeholder_en" id="editPlaceholderEn" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= label('Placeholder DE', 'Platzhalter DE') ?></label>
                    <input type="text" name="placeholder_de" id="editPlaceholderDe" class="form-input">
                </div>
                <div class="form-group" id="editCheckboxGroup" style="display: none;">
                    <label class="form-label"><?= label('Checkbox Options', 'Checkbox-Optionen') ?></label>
                    <textarea name="checkbox_labels" id="editCheckboxLabels" class="form-textarea" rows="4"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-checkbox">
                        <input type="checkbox" name="is_required" id="editRequired" value="1">
                        <?= label('Required field', 'Pflichtfeld') ?>
                    </label>
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
function toggleCheckboxOptions() {
    const type = document.getElementById('newFieldType').value;
    document.getElementById('checkboxOptionsGroup').style.display = type === 'checkbox' ? 'block' : 'none';
}

function editField(field) {
    document.getElementById('editFieldId').value = field.id;
    document.getElementById('editLabelEn').value = field.field_label_en;
    document.getElementById('editLabelDe').value = field.field_label_de;
    document.getElementById('editPlaceholderEn').value = field.placeholder_en || '';
    document.getElementById('editPlaceholderDe').value = field.placeholder_de || '';
    document.getElementById('editRequired').checked = field.is_required == 1;

    const checkGroup = document.getElementById('editCheckboxGroup');
    if (field.field_type === 'checkbox') {
        checkGroup.style.display = 'block';
        const opts = field.options_json ? JSON.parse(field.options_json) : [];
        document.getElementById('editCheckboxLabels').value = opts.map(o => o.label_en + '|' + o.label_de).join('\n');
    } else {
        checkGroup.style.display = 'none';
    }

    document.getElementById('editFieldModal').classList.add('active');
}

// Drag and drop reordering
const canvas = document.getElementById('builderCanvas');
if (canvas) {
    let draggedEl = null;

    canvas.addEventListener('dragstart', function(e) {
        if (e.target.classList.contains('builder-field')) {
            draggedEl = e.target;
            e.target.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        }
    });

    canvas.addEventListener('dragend', function(e) {
        if (draggedEl) {
            draggedEl.classList.remove('dragging');
            draggedEl = null;
        }
    });

    canvas.addEventListener('dragover', function(e) {
        e.preventDefault();
        const afterElement = getDragAfterElement(canvas, e.clientY);
        if (afterElement) {
            canvas.insertBefore(draggedEl, afterElement);
        } else {
            canvas.appendChild(draggedEl);
        }
    });

    function getDragAfterElement(container, y) {
        const elements = [...container.querySelectorAll('.builder-field:not(.dragging)')];
        return elements.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;
            if (offset < 0 && offset > (closest.offset || -Infinity)) {
                return { offset: offset, element: child };
            }
            return closest;
        }, {}).element;
    }
}

function saveOrder() {
    const fields = document.querySelectorAll('.builder-field');
    const order = [];
    fields.forEach(f => order.push(f.dataset.fieldId));
    document.getElementById('fieldOrderInput').value = JSON.stringify(order);
}
</script>
