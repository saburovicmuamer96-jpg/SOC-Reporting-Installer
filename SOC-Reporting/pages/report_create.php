<?php
/**
 * SOC Reporting System - Create Report
 */

$machineSlot = (int) get('machine', 0);
if ($machineSlot < 1 || $machineSlot > MACHINE_SLOTS) {
    setFlash('error', label('Invalid machine.', 'Ungültige Maschine.'));
    redirect('index.php?page=dashboard');
}

$machine = getMachineBySlot($machineSlot);
if (!$machine || !$machine['is_active']) {
    setFlash('error', label('Machine not found or inactive.', 'Maschine nicht gefunden oder inaktiv.'));
    redirect('index.php?page=dashboard');
}

$formFields = getFormFields($machine['id']);

// Handle form submission
if (isPost()) {
    $token = post('csrf_token');
    if (!Auth::validateCsrf($token)) {
        setFlash('error', label('Invalid session. Please try again.', 'Ungültige Sitzung. Bitte versuchen Sie es erneut.'));
        redirect("index.php?page=report_create&machine=$machineSlot");
    }

    $errors = [];

    // Validate incident time
    $incidentTime = post('incident_time');
    if (empty($incidentTime)) {
        $errors[] = label('Incident Time is required.', 'Vorfallzeit ist erforderlich.');
    }

    // Validate required fields
    foreach ($formFields as $field) {
        if ($field['is_required']) {
            $value = post('field_' . $field['id']);
            if ($field['field_type'] === 'checkbox') {
                // Checkbox: check if at least one checked
                if (empty($_POST['field_' . $field['id']])) {
                    $errors[] = fieldLabel($field) . label(' is required.', ' ist erforderlich.');
                }
            } elseif (empty($value)) {
                $errors[] = fieldLabel($field) . label(' is required.', ' ist erforderlich.');
            }
        }
    }

    if (empty($errors)) {
        try {
            $db = Database::machine($machineSlot);

            // Generate event ID
            $eventId = generateEventId($machineSlot);
            $timestamp = date(DATETIME_FORMAT);

            // Parse incident time and calculate reaction time
            $incidentDt = date('Y-m-d H:i:s', strtotime($incidentTime));
            $reactionSeconds = max(0, strtotime($timestamp) - strtotime($incidentDt));

            // Insert report
            $reportId = Database::insert($db, 'reports', [
                'event_id' => $eventId,
                'user_id' => $currentUser['id'],
                'username' => $currentUser['username'],
                'machine_slot' => $machineSlot,
                'status' => 'submitted',
                'notes' => post('notes'),
                'incident_time' => $incidentDt,
                'reaction_time_seconds' => $reactionSeconds,
                'created_at' => $timestamp
            ]);

            // Insert field data
            foreach ($formFields as $field) {
                $value = '';
                if ($field['field_type'] === 'checkbox') {
                    $checkValues = $_POST['field_' . $field['id']] ?? [];
                    $value = is_array($checkValues) ? implode(', ', $checkValues) : $checkValues;
                } else {
                    $value = post('field_' . $field['id']);
                }

                Database::insert($db, 'report_data', [
                    'report_id' => $reportId,
                    'field_id' => $field['id'],
                    'field_name' => $field['field_name'],
                    'field_value' => $value
                ]);
            }

            auditLog('report_created', "Report $eventId created for machine slot $machineSlot");
            setFlash('success', label("Report $eventId created successfully.", "Bericht $eventId erfolgreich erstellt."));
            redirect("index.php?page=report_view&machine=$machineSlot&id=$reportId");

        } catch (Exception $e) {
            setFlash('error', label('Failed to create report. ', 'Bericht konnte nicht erstellt werden. ') . $e->getMessage());
        }
    }

    // Regenerate CSRF
    $csrfToken = Auth::generateCsrf();
}
?>

<script>document.getElementById('pageTitle').textContent = '<?= label('Create Report', 'Bericht erstellen') ?>';</script>

<div class="page-header flex-between">
    <div>
        <h2 class="page-title"><?= label('New Report', 'Neuer Bericht') ?></h2>
        <p class="page-subtitle"><?= e($machine['name']) ?></p>
    </div>
    <a href="index.php?page=reports_list&machine=<?= $machineSlot ?>" class="btn btn-secondary">
        &larr; <?= label('Back to Reports', 'Zurück zu Berichten') ?>
    </a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-error">
    <strong><?= label('Please fix the following:', 'Bitte beheben Sie Folgendes:') ?></strong><br>
    <?= implode('<br>', array_map('e', $errors)) ?>
</div>
<?php endif; ?>

<?php if (empty($formFields)): ?>
<div class="empty-state">
    <div class="empty-state-icon">&#9881;</div>
    <h3 class="empty-state-title"><?= label('No Form Fields Configured', 'Keine Formularfelder konfiguriert') ?></h3>
    <p class="empty-state-text">
        <?= label('An administrator needs to configure form fields for this machine.', 'Ein Administrator muss Formularfelder für diese Maschine konfigurieren.') ?>
    </p>
</div>
<?php else: ?>

<form method="POST" action="index.php?page=report_create&machine=<?= $machineSlot ?>" class="card" style="max-width: 800px;">
    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

    <!-- Auto Timestamp -->
    <div class="form-group">
        <label class="form-label"><?= label('Report Created At', 'Bericht erstellt am') ?> <span class="required-mark">*</span></label>
        <input type="text" class="form-input" value="<?= date(DATETIME_FORMAT) ?>" readonly
               style="background: var(--bg-tertiary); font-family: var(--font-mono); color: var(--accent-primary);">
        <span class="form-hint"><?= label('Automatically set to report generation time', 'Automatisch auf die Berichtserstellungszeit gesetzt') ?></span>
    </div>

    <!-- Incident Time (mandatory for all machines) -->
    <div class="form-group">
        <label class="form-label" for="incident_time"><?= label('Incident Time', 'Vorfallzeit') ?> <span class="required-mark">*</span></label>
        <input type="datetime-local" id="incident_time" name="incident_time" class="form-input" required
               value="<?= e(post('incident_time')) ?>"
               style="font-family: var(--font-mono);">
        <span class="form-hint"><?= label('When did the incident occur? Used to calculate reaction time.', 'Wann ist der Vorfall aufgetreten? Wird zur Berechnung der Reaktionszeit verwendet.') ?></span>
    </div>

    <!-- Dynamic Form Fields -->
    <?php foreach ($formFields as $field): ?>
    <div class="form-group">
        <label class="form-label" for="field_<?= $field['id'] ?>">
            <?= e(fieldLabel($field)) ?>
            <?php if ($field['is_required']): ?><span class="required-mark">*</span><?php endif; ?>
        </label>

        <?php if ($field['field_type'] === 'text'): ?>
        <input type="text" id="field_<?= $field['id'] ?>" name="field_<?= $field['id'] ?>"
               class="form-input" placeholder="<?= e(fieldPlaceholder($field)) ?>"
               value="<?= e(post('field_' . $field['id'])) ?>"
               <?= $field['is_required'] ? 'required' : '' ?>>

        <?php elseif ($field['field_type'] === 'textarea'): ?>
        <textarea id="field_<?= $field['id'] ?>" name="field_<?= $field['id'] ?>"
                  class="form-textarea" placeholder="<?= e(fieldPlaceholder($field)) ?>"
                  <?= $field['is_required'] ? 'required' : '' ?>><?= e(post('field_' . $field['id'])) ?></textarea>

        <?php elseif ($field['field_type'] === 'number'): ?>
        <input type="number" id="field_<?= $field['id'] ?>" name="field_<?= $field['id'] ?>"
               class="form-input" placeholder="<?= e(fieldPlaceholder($field)) ?>"
               value="<?= e(post('field_' . $field['id'])) ?>"
               <?= $field['is_required'] ? 'required' : '' ?>>

        <?php elseif ($field['field_type'] === 'dropdown'): ?>
        <select id="field_<?= $field['id'] ?>" name="field_<?= $field['id'] ?>"
                class="form-select" <?= $field['is_required'] ? 'required' : '' ?>>
            <option value=""><?= label('-- Select --', '-- Auswählen --') ?></option>
            <?php foreach (getDropdownOptions($field['id']) as $opt): ?>
            <option value="<?= e($opt['value']) ?>"
                    <?= post('field_' . $field['id']) === $opt['value'] ? 'selected' : '' ?>>
                <?= e(optionLabel($opt)) ?>
            </option>
            <?php endforeach; ?>
        </select>

        <?php elseif ($field['field_type'] === 'checkbox'): ?>
        <div class="form-checkbox-group">
            <?php
            $options = json_decode($field['options_json'] ?? '[]', true) ?: [];
            $checked = $_POST['field_' . $field['id']] ?? [];
            foreach ($options as $idx => $opt):
                $optLabel = currentLang() === 'de' ? ($opt['label_de'] ?? $opt['label']) : ($opt['label_en'] ?? $opt['label']);
            ?>
            <label class="form-checkbox">
                <input type="checkbox" name="field_<?= $field['id'] ?>[]" value="<?= e($opt['value'] ?? $optLabel) ?>"
                       <?= in_array($opt['value'] ?? $optLabel, (array)$checked) ? 'checked' : '' ?>>
                <?= e($optLabel) ?>
            </label>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <!-- Notes -->
    <div class="form-group">
        <label class="form-label" for="notes"><?= label('Additional Notes', 'Zusätzliche Anmerkungen') ?></label>
        <textarea id="notes" name="notes" class="form-textarea"
                  placeholder="<?= label('Optional notes...', 'Optionale Anmerkungen...') ?>"><?= e(post('notes')) ?></textarea>
    </div>

    <div class="flex gap-1" style="margin-top: 24px;">
        <button type="submit" class="btn btn-primary btn-lg">
            <?= label('Submit Report', 'Bericht einreichen') ?>
        </button>
        <a href="index.php?page=reports_list&machine=<?= $machineSlot ?>" class="btn btn-secondary btn-lg">
            <?= label('Cancel', 'Abbrechen') ?>
        </a>
    </div>
</form>

<?php endif; ?>
