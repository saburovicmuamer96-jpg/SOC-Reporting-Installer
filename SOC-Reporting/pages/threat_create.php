<?php
/**
 * SOC Reporting System - Create Threat/CVE Log
 */

// Handle form submission
if (isPost()) {
    $token = post('csrf_token');
    if (!Auth::validateCsrf($token)) {
        setFlash('error', label('Invalid session. Please try again.', 'Ungültige Sitzung. Bitte versuchen Sie es erneut.'));
        redirect('index.php?page=threat_create');
    }

    $errors = [];

    // Validate required fields
    $cveId = trim(post('cve_id'));
    $title = trim(post('title'));
    $description = trim(post('description'));
    $severity = post('severity');
    $status = post('status');

    if (empty($cveId)) {
        $errors[] = label('CVE ID is required.', 'CVE ID ist erforderlich.');
    }
    if (empty($title)) {
        $errors[] = label('Title is required.', 'Titel ist erforderlich.');
    }
    if (empty($description)) {
        $errors[] = label('Description is required.', 'Beschreibung ist erforderlich.');
    }
    if (empty($severity)) {
        $errors[] = label('Severity is required.', 'Schweregrad ist erforderlich.');
    }
    if (empty($status)) {
        $errors[] = label('Status is required.', 'Status ist erforderlich.');
    }

    // Validate CVSS score if provided
    $cvssScore = post('cvss_score');
    if (!empty($cvssScore)) {
        $cvssScore = (float) $cvssScore;
        if ($cvssScore < 0 || $cvssScore > 10) {
            $errors[] = label('CVSS Score must be between 0.0 and 10.0.', 'CVSS-Score muss zwischen 0,0 und 10,0 liegen.');
        }
    } else {
        $cvssScore = null;
    }

    if (empty($errors)) {
        try {
            $db = Database::system();

            // Parse dates
            $publishedDate = post('published_date') ?: null;
            $notifiedAt = post('notified_at') ? date('Y-m-d H:i:s', strtotime(post('notified_at'))) : null;

            // Insert threat log
            Database::insert($db, 'threat_logs', [
                'cve_id' => $cveId,
                'title' => $title,
                'description' => $description,
                'severity' => $severity,
                'cvss_score' => $cvssScore,
                'affected_systems' => post('affected_systems'),
                'departments_notified' => post('departments_notified'),
                'mitigation' => post('mitigation'),
                'status' => $status,
                'published_date' => $publishedDate,
                'notified_at' => $notifiedAt,
                'created_by' => $currentUser['username'],
                'created_at' => date(DATETIME_FORMAT)
            ]);

            auditLog('threat_created', "Threat log $cveId created");
            setFlash('success', label("Threat log $cveId created successfully.", "Bedrohungsprotokoll $cveId erfolgreich erstellt."));
            redirect('index.php?page=threat_logs');

        } catch (Exception $e) {
            setFlash('error', label('Failed to create threat log. ', 'Bedrohungsprotokoll konnte nicht erstellt werden. ') . $e->getMessage());
        }
    }

    // Regenerate CSRF
    $csrfToken = Auth::generateCsrf();
}
?>

<script>document.getElementById('pageTitle').textContent = '<?= label('Log New Threat', 'Neue Bedrohung protokollieren') ?>';</script>

<div class="page-header flex-between">
    <div>
        <h2 class="page-title"><?= label('Log New Threat', 'Neue Bedrohung protokollieren') ?></h2>
        <p class="page-subtitle"><?= label('CVE / Security Vulnerability Tracking', 'CVE / Sicherheitslücken-Tracking') ?></p>
    </div>
    <a href="index.php?page=threat_logs" class="btn btn-secondary">
        &larr; <?= label('Back to Threat Logs', 'Zurück zu Bedrohungsprotokollen') ?>
    </a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-error">
    <strong><?= label('Please fix the following:', 'Bitte beheben Sie Folgendes:') ?></strong><br>
    <?= implode('<br>', array_map('e', $errors)) ?>
</div>
<?php endif; ?>

<form method="POST" action="index.php?page=threat_create" class="card" style="max-width: 900px;">
    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

    <!-- CVE ID -->
    <div class="form-group">
        <label class="form-label" for="cve_id">
            <?= label('CVE ID', 'CVE ID') ?> <span class="required-mark">*</span>
        </label>
        <input type="text" id="cve_id" name="cve_id" class="form-input" required
               placeholder="<?= label('e.g., CVE-2024-1234', 'z.B. CVE-2024-1234') ?>"
               value="<?= e(post('cve_id')) ?>"
               style="font-family: var(--font-mono);">
        <span class="form-hint"><?= label('Official CVE identifier or internal threat ID', 'Offizielle CVE-Kennung oder interne Bedrohungs-ID') ?></span>
    </div>

    <!-- Title -->
    <div class="form-group">
        <label class="form-label" for="title">
            <?= label('Title', 'Titel') ?> <span class="required-mark">*</span>
        </label>
        <input type="text" id="title" name="title" class="form-input" required
               placeholder="<?= label('Brief description of the threat', 'Kurze Beschreibung der Bedrohung') ?>"
               value="<?= e(post('title')) ?>">
    </div>

    <!-- Description -->
    <div class="form-group">
        <label class="form-label" for="description">
            <?= label('Description', 'Beschreibung') ?> <span class="required-mark">*</span>
        </label>
        <textarea id="description" name="description" class="form-textarea" required rows="5"
                  placeholder="<?= label('Detailed description of the vulnerability and its impact...', 'Detaillierte Beschreibung der Schwachstelle und ihrer Auswirkungen...') ?>"><?= e(post('description')) ?></textarea>
    </div>

    <!-- Severity and CVSS Score Row -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <!-- Severity -->
        <div class="form-group">
            <label class="form-label" for="severity">
                <?= label('Severity', 'Schweregrad') ?> <span class="required-mark">*</span>
            </label>
            <select id="severity" name="severity" class="form-select" required>
                <option value=""><?= label('-- Select Severity --', '-- Schweregrad auswählen --') ?></option>
                <option value="critical" <?= post('severity') === 'critical' ? 'selected' : '' ?>><?= label('Critical', 'Kritisch') ?></option>
                <option value="high" <?= post('severity') === 'high' ? 'selected' : '' ?>><?= label('High', 'Hoch') ?></option>
                <option value="medium" <?= post('severity') === 'medium' ? 'selected' : '' ?>><?= label('Medium', 'Mittel') ?></option>
                <option value="low" <?= post('severity') === 'low' ? 'selected' : '' ?>><?= label('Low', 'Niedrig') ?></option>
                <option value="info" <?= post('info') === 'info' ? 'selected' : '' ?>><?= label('Info', 'Info') ?></option>
            </select>
        </div>

        <!-- CVSS Score -->
        <div class="form-group">
            <label class="form-label" for="cvss_score">
                <?= label('CVSS Score', 'CVSS-Score') ?>
            </label>
            <input type="number" id="cvss_score" name="cvss_score" class="form-input"
                   min="0" max="10" step="0.1"
                   placeholder="<?= label('0.0 - 10.0', '0,0 - 10,0') ?>"
                   value="<?= e(post('cvss_score')) ?>"
                   style="font-family: var(--font-mono);">
            <span class="form-hint"><?= label('Common Vulnerability Scoring System (0-10)', 'Common Vulnerability Scoring System (0-10)') ?></span>
        </div>
    </div>

    <!-- Affected Systems -->
    <div class="form-group">
        <label class="form-label" for="affected_systems">
            <?= label('Affected Systems', 'Betroffene Systeme') ?>
        </label>
        <textarea id="affected_systems" name="affected_systems" class="form-textarea" rows="3"
                  placeholder="<?= label('List affected systems, products, or versions...', 'Betroffene Systeme, Produkte oder Versionen auflisten...') ?>"><?= e(post('affected_systems')) ?></textarea>
    </div>

    <!-- Departments Notified -->
    <div class="form-group">
        <label class="form-label" for="departments_notified">
            <?= label('Departments Notified', 'Benachrichtigte Abteilungen') ?>
        </label>
        <input type="text" id="departments_notified" name="departments_notified" class="form-input"
               placeholder="<?= label('e.g., IT, Security, Infrastructure', 'z.B. IT, Sicherheit, Infrastruktur') ?>"
               value="<?= e(post('departments_notified')) ?>">
        <span class="form-hint"><?= label('Comma-separated list of notified departments', 'Kommagetrennte Liste der benachrichtigten Abteilungen') ?></span>
    </div>

    <!-- Mitigation -->
    <div class="form-group">
        <label class="form-label" for="mitigation">
            <?= label('Mitigation / Remediation', 'Abhilfe / Behebung') ?>
        </label>
        <textarea id="mitigation" name="mitigation" class="form-textarea" rows="4"
                  placeholder="<?= label('Steps taken or recommended to mitigate the threat...', 'Schritte zur Entschärfung der Bedrohung...') ?>"><?= e(post('mitigation')) ?></textarea>
    </div>

    <!-- Status -->
    <div class="form-group">
        <label class="form-label" for="status">
            <?= label('Status', 'Status') ?> <span class="required-mark">*</span>
        </label>
        <select id="status" name="status" class="form-select" required>
            <option value=""><?= label('-- Select Status --', '-- Status auswählen --') ?></option>
            <option value="new" <?= post('status') === 'new' ? 'selected' : '' ?>><?= label('New', 'Neu') ?></option>
            <option value="notified" <?= post('status') === 'notified' ? 'selected' : '' ?>><?= label('Notified', 'Benachrichtigt') ?></option>
            <option value="patched" <?= post('status') === 'patched' ? 'selected' : '' ?>><?= label('Patched', 'Gepatcht') ?></option>
            <option value="mitigated" <?= post('status') === 'mitigated' ? 'selected' : '' ?>><?= label('Mitigated', 'Entschärft') ?></option>
            <option value="closed" <?= post('status') === 'closed' ? 'selected' : '' ?>><?= label('Closed', 'Geschlossen') ?></option>
        </select>
    </div>

    <!-- Published Date and Notified At Row -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <!-- Published Date -->
        <div class="form-group">
            <label class="form-label" for="published_date">
                <?= label('Published Date', 'Veröffentlichungsdatum') ?>
            </label>
            <input type="date" id="published_date" name="published_date" class="form-input"
                   value="<?= e(post('published_date')) ?>"
                   style="font-family: var(--font-mono);">
            <span class="form-hint"><?= label('When was the CVE/threat published?', 'Wann wurde die CVE/Bedrohung veröffentlicht?') ?></span>
        </div>

        <!-- Notified At -->
        <div class="form-group">
            <label class="form-label" for="notified_at">
                <?= label('Notified At', 'Benachrichtigt am') ?>
            </label>
            <input type="datetime-local" id="notified_at" name="notified_at" class="form-input"
                   value="<?= e(post('notified_at')) ?>"
                   style="font-family: var(--font-mono);">
            <span class="form-hint"><?= label('When were departments notified?', 'Wann wurden Abteilungen benachrichtigt?') ?></span>
        </div>
    </div>

    <div class="flex gap-1" style="margin-top: 24px;">
        <button type="submit" class="btn btn-primary btn-lg">
            ➕ <?= label('Log Threat', 'Bedrohung protokollieren') ?>
        </button>
        <a href="index.php?page=threat_logs" class="btn btn-secondary btn-lg">
            <?= label('Cancel', 'Abbrechen') ?>
        </a>
    </div>
</form>
