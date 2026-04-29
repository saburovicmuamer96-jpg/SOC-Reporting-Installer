<?php
/**
 * SOC Reporting System - Instructions Editor (Admin)
 * Rich text editor for admin and user instructions in EN/DE.
 */

$editType = get('type', 'user');
$editLang = get('lang', currentLang());

if (!in_array($editType, ['admin', 'user'])) $editType = 'user';
if (!in_array($editLang, ['en', 'de'])) $editLang = 'en';

// Handle save
if (isPost() && post('action') === 'save_instructions') {
    $token = post('csrf_token');
    if (Auth::validateCsrf($token)) {
        $type = post('type');
        $lang = post('lang');
        $title = sanitize(post('title'));
        $content = $_POST['content_html'] ?? ''; // Allow HTML

        // Check if record exists
        $existing = Database::fetchOne(Database::system(),
            "SELECT id FROM instructions WHERE type = ? AND language = ?",
            [$type, $lang]
        );

        if ($existing) {
            Database::update(Database::system(), 'instructions', [
                'title' => $title,
                'content_html' => $content
            ], 'id = ?', [$existing['id']]);
        } else {
            Database::insert(Database::system(), 'instructions', [
                'type' => $type,
                'language' => $lang,
                'title' => $title,
                'content_html' => $content
            ]);
        }

        auditLog('instructions_updated', "Updated $type instructions ($lang)");
        setFlash('success', label('Instructions saved successfully.', 'Anleitung erfolgreich gespeichert.'));
    }
    $csrfToken = Auth::generateCsrf();
    redirect("index.php?page=instructions_edit&type=$editType&lang=$editLang");
}

// Get current content
$instruction = Database::fetchOne(Database::system(),
    "SELECT * FROM instructions WHERE type = ? AND language = ?",
    [$editType, $editLang]
);
?>

<script>document.getElementById('pageTitle').textContent = '<?= label('Edit Instructions', 'Anleitungen bearbeiten') ?>';</script>

<div class="page-header flex-between">
    <div>
        <h2 class="page-title"><?= label('Edit Instructions', 'Anleitungen bearbeiten') ?></h2>
        <p class="page-subtitle">
            <?= ucfirst($editType) ?> - <?= strtoupper($editLang) ?>
        </p>
    </div>
    <a href="index.php?page=instructions" class="btn btn-secondary">
        <?= label('View Instructions', 'Anleitungen anzeigen') ?>
    </a>
</div>

<!-- Type/Language Selector -->
<div class="card mb-3">
    <div class="flex gap-1" style="align-items: center; flex-wrap: wrap;">
        <span class="text-muted" style="font-size: 0.82rem; font-weight: 600; margin-right: 8px;">
            <?= label('Type:', 'Typ:') ?>
        </span>
        <a href="index.php?page=instructions_edit&type=user&lang=<?= $editLang ?>"
           class="btn <?= $editType === 'user' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
            <?= label('User Guide', 'Benutzerhandbuch') ?>
        </a>
        <a href="index.php?page=instructions_edit&type=admin&lang=<?= $editLang ?>"
           class="btn <?= $editType === 'admin' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
            <?= label('Admin Guide', 'Administratorhandbuch') ?>
        </a>

        <span class="text-muted" style="font-size: 0.82rem; font-weight: 600; margin: 0 8px 0 16px;">
            <?= label('Language:', 'Sprache:') ?>
        </span>
        <a href="index.php?page=instructions_edit&type=<?= $editType ?>&lang=en"
           class="btn <?= $editLang === 'en' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">EN</a>
        <a href="index.php?page=instructions_edit&type=<?= $editType ?>&lang=de"
           class="btn <?= $editLang === 'de' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">DE</a>
    </div>
</div>

<!-- Editor -->
<form method="POST">
    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
    <input type="hidden" name="action" value="save_instructions">
    <input type="hidden" name="type" value="<?= e($editType) ?>">
    <input type="hidden" name="lang" value="<?= e($editLang) ?>">

    <div class="form-group">
        <label class="form-label"><?= label('Title', 'Titel') ?></label>
        <input type="text" name="title" class="form-input" value="<?= e($instruction['title'] ?? '') ?>">
    </div>

    <!-- Rich Text Toolbar -->
    <div class="editor-toolbar">
        <button type="button" class="editor-btn" onclick="execCmd('bold')" title="Bold"><b>B</b></button>
        <button type="button" class="editor-btn" onclick="execCmd('italic')" title="Italic"><i>I</i></button>
        <button type="button" class="editor-btn" onclick="execCmd('underline')" title="Underline"><u>U</u></button>
        <button type="button" class="editor-btn" onclick="execCmd('formatBlock', 'h2')" title="Heading 2">H2</button>
        <button type="button" class="editor-btn" onclick="execCmd('formatBlock', 'h3')" title="Heading 3">H3</button>
        <button type="button" class="editor-btn" onclick="execCmd('formatBlock', 'p')" title="Paragraph">P</button>
        <button type="button" class="editor-btn" onclick="execCmd('insertUnorderedList')" title="Bullet List">&#8226;</button>
        <button type="button" class="editor-btn" onclick="execCmd('insertOrderedList')" title="Numbered List">1.</button>
        <button type="button" class="editor-btn" onclick="execCmd('formatBlock', 'blockquote')" title="Quote">&ldquo;</button>
        <button type="button" class="editor-btn" onclick="insertCode()" title="Code">&lt;/&gt;</button>
    </div>

    <div class="editor-content" id="editor" contenteditable="true"><?= $instruction['content_html'] ?? '' ?></div>
    <textarea name="content_html" id="contentHtml" class="hidden"></textarea>

    <div class="flex gap-1 mt-2">
        <button type="submit" class="btn btn-primary" onclick="document.getElementById('contentHtml').value = document.getElementById('editor').innerHTML;">
            <?= label('Save Instructions', 'Anleitungen speichern') ?>
        </button>
    </div>
</form>

<script>
function execCmd(cmd, value) {
    if (cmd === 'formatBlock') {
        document.execCommand(cmd, false, '<' + value + '>');
    } else {
        document.execCommand(cmd, false, value || null);
    }
    document.getElementById('editor').focus();
}

function insertCode() {
    const sel = window.getSelection();
    if (sel.rangeCount) {
        const range = sel.getRangeAt(0);
        const code = document.createElement('code');
        range.surroundContents(code);
    }
}
</script>
