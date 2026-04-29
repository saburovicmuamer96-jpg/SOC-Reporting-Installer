<?php
/**
 * SOC Reporting System - Threat Intelligence Dashboard
 * Aggregates cybersecurity RSS feeds and matches against owned assets.
 */

// Get distinct vendors and all asset vendor+model pairs for relevance matching
$ownedVendors = Database::fetchAll(Database::system(),
    "SELECT DISTINCT LOWER(vendor) as vendor_lower, vendor FROM assets ORDER BY vendor"
);
$ownedAssets = Database::fetchAll(Database::system(),
    "SELECT vendor, model FROM assets ORDER BY vendor, model"
);

// Build search terms for JS (vendor names + model names for matching)
$searchTerms = [];
foreach ($ownedAssets as $asset) {
    $searchTerms[] = [
        'vendor' => $asset['vendor'],
        'model' => $asset['model'],
        'terms' => array_filter(array_unique(array_map('strtolower', [
            $asset['vendor'],
            $asset['model'],
            // Also add first word of model (e.g., "Meraki" from "Meraki MX84")
            explode(' ', $asset['model'])[0]
        ])))
    ];
}
?>

<script>document.getElementById('pageTitle').textContent = '<?= label('Threat Intelligence', 'Bedrohungsanalyse') ?>';</script>

<!-- Stats Bar -->
<div class="ti-stats-bar">
    <div class="ti-stat">
        <div class="ti-stat-value" id="statTotal">—</div>
        <div class="ti-stat-label"><?= label('Total Items', 'Gesamt') ?></div>
    </div>
    <div class="ti-stat">
        <div class="ti-stat-value ti-color-mitre" id="statMitre">—</div>
        <div class="ti-stat-label">MITRE ATT&CK</div>
    </div>
    <div class="ti-stat">
        <div class="ti-stat-value ti-color-cisa" id="statCisa">—</div>
        <div class="ti-stat-label">CISA Alerts</div>
    </div>
    <div class="ti-stat">
        <div class="ti-stat-value ti-color-news" id="statNews">—</div>
        <div class="ti-stat-label"><?= label('Cyber News', 'Cyber-Nachrichten') ?></div>
    </div>
    <div class="ti-stat">
        <div class="ti-stat-value ti-color-relevant" id="statRelevant">—</div>
        <div class="ti-stat-label"><?= label('Relevant to Assets', 'Relevant für Geräte') ?></div>
    </div>
</div>

<!-- Filters -->
<div class="ti-filters">
    <div class="ti-filter-row">
        <div class="ti-search-wrap">
            <input type="text" id="tiSearch" class="form-input" placeholder="<?= label('Search threats...', 'Bedrohungen suchen...') ?>">
        </div>
        <div class="ti-category-filters">
            <button class="ti-pill active" data-category="all"><?= label('All', 'Alle') ?></button>
            <button class="ti-pill ti-pill-mitre" data-category="mitre">MITRE ATT&CK</button>
            <button class="ti-pill ti-pill-cisa" data-category="cisa">CISA Alerts</button>
            <button class="ti-pill ti-pill-news" data-category="news"><?= label('Cyber News', 'Cyber-Nachrichten') ?></button>
        </div>
    </div>
    <?php if (!empty($ownedVendors)): ?>
    <div class="ti-filter-row">
        <div class="ti-vendor-filters">
            <span class="ti-filter-label"><?= label('Device Filters:', 'Gerätefilter:') ?></span>
            <?php foreach ($ownedVendors as $v): ?>
            <button class="ti-pill ti-pill-vendor" data-vendor="<?= e(strtolower($v['vendor'])) ?>">
                <?= e($v['vendor']) ?>
            </button>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Main Content: Feed + Sidebar -->
<div class="ti-layout">
    <!-- Feed Cards -->
    <div class="ti-feed" id="tiFeed">
        <div class="ti-loading" id="tiLoading">
            <div class="ti-spinner"></div>
            <p><?= label('Loading threat feeds...', 'Lade Bedrohungs-Feeds...') ?></p>
        </div>
    </div>

    <!-- Relevant Alerts Sidebar -->
    <div class="ti-sidebar">
        <div class="ti-sidebar-header">
            <div class="ti-sidebar-dot"></div>
            <h3><?= label('Relevant to Your Assets', 'Relevant für Ihre Geräte') ?></h3>
        </div>
        <p class="ti-sidebar-desc">
            <?= label(
                'Threats matching your registered devices are highlighted here.',
                'Bedrohungen, die zu Ihren registrierten Geräten passen, werden hier angezeigt.'
            ) ?>
        </p>
        <div id="tiRelevant">
            <div class="ti-sidebar-empty" id="tiRelevantEmpty">
                <?= label('Scanning feeds...', 'Feeds werden durchsucht...') ?>
            </div>
        </div>
    </div>
</div>

<!-- Pass asset data to JavaScript -->
<script>
window.TI_OWNED_ASSETS = <?= json_encode($searchTerms, JSON_UNESCAPED_UNICODE) ?>;
</script>
