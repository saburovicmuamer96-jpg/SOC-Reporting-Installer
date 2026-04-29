<?php
/**
 * SOC Reporting System - Instructions Page
 * Displays user/admin instructions with language toggle.
 */

$viewLang = currentLang();
$viewType = 'user'; // Default to user instructions

// Get user instructions
$userInst = Database::fetchOne(Database::system(),
    "SELECT * FROM instructions WHERE type = 'user' AND language = ?",
    [$viewLang]
);

// Get admin instructions (only shown to admins)
$adminInst = null;
if (Session::isAdmin()) {
    $adminInst = Database::fetchOne(Database::system(),
        "SELECT * FROM instructions WHERE type = 'admin' AND language = ?",
        [$viewLang]
    );
}
?>

<script>document.getElementById('pageTitle').textContent = '<?= label('Instructions', 'Anleitungen') ?>';</script>

<div class="page-header flex-between">
    <div>
        <h2 class="page-title"><?= label('Instructions', 'Anleitungen') ?></h2>
        <p class="page-subtitle"><?= label('Guides and documentation', 'Anleitungen und Dokumentation') ?></p>
    </div>
    <div class="flex gap-1">
        <?php if (Session::isAdminAuthenticated()): ?>
        <a href="index.php?page=instructions_edit" class="btn btn-warning btn-sm">
            <?= label('Edit Instructions', 'Anleitungen bearbeiten') ?>
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Tab Buttons -->
<div class="flex gap-1 mb-3">
    <button class="btn btn-primary btn-sm" id="tabUser" onclick="showTab('user')">
        <?= label('User Guide', 'Benutzerhandbuch') ?>
    </button>
    <?php if (Session::isAdmin()): ?>
    <button class="btn btn-secondary btn-sm" id="tabAdmin" onclick="showTab('admin')">
        <?= label('Admin Guide', 'Administratorhandbuch') ?>
    </button>
    <?php endif; ?>
</div>

<!-- User Instructions -->
<div id="contentUser" class="instructions-content">
    <?php if ($userInst && $userInst['content_html']): ?>
        <?= $userInst['content_html'] ?>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon">&#9432;</div>
            <h3 class="empty-state-title"><?= label('No instructions available', 'Keine Anleitungen verfügbar') ?></h3>
            <p class="empty-state-text">
                <?= label('Instructions will be added by an administrator.', 'Anleitungen werden von einem Administrator hinzugefügt.') ?>
            </p>
        </div>
    <?php endif; ?>
</div>

<?php if (Session::isAdmin() && $adminInst): ?>
<!-- Admin Instructions -->
<div id="contentAdmin" class="instructions-content" style="display: none;">
    <?php if ($adminInst['content_html']): ?>
        <?= $adminInst['content_html'] ?>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon">&#9888;</div>
            <h3 class="empty-state-title"><?= label('No admin instructions', 'Keine Admin-Anleitungen') ?></h3>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Download Section -->
<div class="card mt-3">
    <h3 style="font-size: 0.9rem; font-weight: 600; margin-bottom: 16px;">
        <?= label('Download PDF Guides', 'PDF-Anleitungen herunterladen') ?>
    </h3>
    <div class="flex gap-1" style="flex-wrap: wrap;">
        <?php
        $docs = [
            ['file' => 'user_instructions_en.pdf', 'label' => 'User Guide (EN)', 'labelDe' => 'Benutzerhandbuch (EN)'],
            ['file' => 'user_instructions_de.pdf', 'label' => 'User Guide (DE)', 'labelDe' => 'Benutzerhandbuch (DE)'],
        ];
        if (Session::isAdmin()) {
            $docs[] = ['file' => 'admin_instructions_en.pdf', 'label' => 'Admin Guide (EN)', 'labelDe' => 'Administratorhandbuch (EN)'];
            $docs[] = ['file' => 'admin_instructions_de.pdf', 'label' => 'Admin Guide (DE)', 'labelDe' => 'Administratorhandbuch (DE)'];
        }
        foreach ($docs as $doc):
            $filePath = APP_ROOT . '/docs/' . $doc['file'];
            $exists = file_exists($filePath);
        ?>
        <a href="<?= $exists ? 'docs/' . e($doc['file']) : '#' ?>"
           class="btn <?= $exists ? 'btn-secondary' : 'btn-secondary' ?> btn-sm"
           <?= $exists ? 'download' : 'style="opacity:0.4; pointer-events:none;"' ?>>
            &#8681; <?= label($doc['label'], $doc['labelDe']) ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<script>
function showTab(type) {
    document.getElementById('contentUser').style.display = type === 'user' ? 'block' : 'none';
    document.getElementById('tabUser').className = type === 'user' ? 'btn btn-primary btn-sm' : 'btn btn-secondary btn-sm';

    const adminContent = document.getElementById('contentAdmin');
    const adminTab = document.getElementById('tabAdmin');
    if (adminContent) {
        adminContent.style.display = type === 'admin' ? 'block' : 'none';
    }
    if (adminTab) {
        adminTab.className = type === 'admin' ? 'btn btn-primary btn-sm' : 'btn btn-secondary btn-sm';
    }
}
</script>
