<?php
/**
 * SOC Reporting System - Main Entry Point & Router
 */

ob_start();

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

// Start session
Session::start();

// Handle language switch
if (isset($_GET['lang']) && in_array($_GET['lang'], unserialize(LANGUAGES))) {
    $_SESSION['language'] = $_GET['lang'];
    if (Session::isLoggedIn()) {
        Database::update(Database::system(), 'users',
            ['language' => $_GET['lang']],
            'id = ?', [$_SESSION['user_id']]
        );
    }
    // Redirect back without lang param
    $redirect = preg_replace('/[?&]lang=[^&]+/', '', $_SERVER['REQUEST_URI']);
    redirect($redirect ?: 'index.php');
}

// Route handling
$page = get('page', 'dashboard');

// Handle logout
if ($page === 'logout') {
    Auth::logout();
    redirect('index.php?page=login');
}

// Public pages (no auth required)
$publicPages = ['login'];

// Check auth for non-public pages
if (!in_array($page, $publicPages)) {
    Session::requireLogin();
}

// Admin pages require admin auth gate
$adminPages = ['users', 'machines', 'form_builder', 'dropdown_data', 'instructions_edit', 'deleted_reports', 'assets'];
if (in_array($page, $adminPages)) {
    Session::requireAdminAuth();
}

// Map pages to files
$pageMap = [
    'login'             => 'pages/login.php',
    'dashboard'         => 'pages/dashboard.php',
    'report_create'     => 'pages/report_create.php',
    'report_view'       => 'pages/report_view.php',
    'reports_list'      => 'pages/reports_list.php',
    'charts'            => 'pages/charts.php',
    'instructions'      => 'pages/instructions.php',
    'admin_login'       => 'pages/admin/admin_login.php',
    'users'             => 'pages/admin/users.php',
    'machines'          => 'pages/admin/machines.php',
    'form_builder'      => 'pages/admin/form_builder.php',
    'dropdown_data'     => 'pages/admin/dropdown_data.php',
    'instructions_edit' => 'pages/admin/instructions_edit.php',
    'deleted_reports'   => 'pages/admin/deleted_reports.php',
    'assets'            => 'pages/admin/assets.php',
    'threat_intel'      => 'pages/threat_intel.php',
    'threat_logs'       => 'pages/threat_logs.php',
    'threat_create'     => 'pages/threat_create.php',
    'threat_view'       => 'pages/threat_view.php',
];

// API routes
$apiMap = [
    'api_reports'           => 'api/reports.php',
    'api_charts'            => 'api/charts_data.php',
    'api_export_pdf'        => 'api/export_pdf.php',
    'api_admin'             => 'api/admin_api.php',
    'api_form_fields'       => 'api/form_fields.php',
    'api_rss_proxy'         => 'api/rss_proxy.php',
    'api_update_dept_status'=> 'api/update_dept_status.php',
    'api_update_threat_status'=> 'api/update_threat_status.php',
    'api_export_threat_pdf'   => 'api/export_threat_pdf.php',
];

// Handle API requests
if (isset($apiMap[$page])) {
    $apiFile = __DIR__ . '/' . $apiMap[$page];
    if (file_exists($apiFile)) {
        require $apiFile;
        exit;
    }
}

// Check if page exists
if (!isset($pageMap[$page])) {
    $page = 'dashboard';
}

$pageFile = __DIR__ . '/' . $pageMap[$page];

// Login page has its own layout
if ($page === 'login') {
    require $pageFile;
    exit;
}

// Get data for layout
$currentUser = Session::user();
$machines = getActiveMachines();
$currentPage = $page;
$flash = getFlash();
$csrfToken = Auth::generateCsrf();

// Render layout with page content
?>
<!DOCTYPE html>
<html lang="<?= e(currentLang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=2.4">
    <script>
        // Apply saved theme before paint to prevent flash
        (function() {
            var t = localStorage.getItem('soc-theme');
            if (t === 'light') document.documentElement.setAttribute('data-theme', 'light');
        })();
    </script>
    <style>
        /* Globe logo styles */
        .sidebar-header {
            padding: 24px !important;
        }
        .sidebar-logo {
            flex-direction: column !important;
            text-align: center !important;
            gap: 16px !important;
        }
        .globe-logo {
            width: 156px;
            height: 156px;
            position: relative;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            margin: 0 auto;
        }
        .globe-logo svg { position: absolute; inset: 0; width: 100%; height: 100%; display: block; }
        .globe-sphere { fill: var(--bg-secondary); stroke: var(--accent-primary); stroke-width: 1.5; }
        .globe-graticule { fill: none; stroke: var(--accent-primary); stroke-width: 0.5; opacity: 0.4; }
        .globe-country { fill: var(--accent-primary); fill-rule: evenodd; }
        .globe-sphere-edge { fill: none; stroke: var(--accent-primary); stroke-width: 1.5; }
        .globe-whirl-arc {
            fill: none;
            stroke: var(--accent-primary);
            stroke-linecap: round;
            transform-origin: 78px 78px;
        }
        .globe-w1 { animation: globeWhirl 2.6s cubic-bezier(.4,.05,.2,1) infinite; }
        .globe-w2 { animation: globeWhirl 2.6s cubic-bezier(.4,.05,.2,1) -0.35s infinite; }
        .globe-w3 { animation: globeWhirl 2.6s cubic-bezier(.4,.05,.2,1) -0.70s infinite; }
        .globe-w4 { animation: globeWhirl 2.6s cubic-bezier(.4,.05,.2,1) -1.05s infinite; }
        .globe-w5 { animation: globeWhirl 2.6s cubic-bezier(.4,.05,.2,1) -1.40s infinite; }
        @keyframes globeWhirl {
            0%   { transform: rotate(0deg)   scale(0.98); opacity: 0; }
            12%  { opacity: .95; }
            70%  { opacity: .35; }
            100% { transform: rotate(220deg) scale(1.32); opacity: 0; }
        }
        .globe-ring {
            fill: none; stroke: var(--accent-primary); stroke-width: 0.6; opacity: 0.18;
            stroke-dasharray: 2 6;
            transform-origin: 78px 78px;
            animation: globeRingspin 22s linear infinite;
        }
        @keyframes globeRingspin { to { transform: rotate(360deg); } }

        /* Theme toggle button */
        .theme-toggle {
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: 50%;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            color: var(--text-primary);
            font-size: 18px;
            padding: 0;
        }
        .theme-toggle:hover {
            border-color: var(--accent-primary);
            background: var(--bg-hover);
        }
        /* Smooth theme transition */
        body, body * {
            transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
        }

        /* Train orbit */
        .globe-train-svg { position: absolute; inset: 0; width: 100%; height: 100%; display: block; z-index: 2; pointer-events: none; }
        .train-orbit {
            transform-origin: 78px 78px;
            animation: trainOrbit 7s linear infinite;
        }
        @keyframes trainOrbit {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .train-body { fill: var(--accent-primary); }
        .train-wheel { fill: var(--bg-primary); stroke: var(--accent-primary); stroke-width: 0.7; }
        .train-smoke {
            fill: var(--accent-primary);
        }
        .train-smoke-1 { animation: puff 1s ease-out infinite; }
        .train-smoke-2 { animation: puff 1s ease-out 0.3s infinite; }
        .train-smoke-3 { animation: puff 1s ease-out 0.6s infinite; }
        @keyframes puff {
            0%   { opacity: 0.6; transform: translate(0, 0) scale(1); }
            100% { opacity: 0; transform: translate(-3px, -6px) scale(2); }
        }
    </style>
</head>
<body>
    <div class="app-layout">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <div class="globe-logo">
                        <!-- Whirl layer -->
                        <svg viewBox="0 0 156 156" aria-hidden="true">
                            <circle class="globe-ring" cx="78" cy="78" r="65.52" />
                            <path class="globe-whirl-arc globe-w1" d="M78 23.4 A54.6 54.6 0 0 1 132.6 78" stroke-width="1.3"/>
                            <path class="globe-whirl-arc globe-w2" d="M78 23.4 A54.6 54.6 0 0 1 132.6 78" stroke-width="1.05"/>
                            <path class="globe-whirl-arc globe-w3" d="M78 23.4 A54.6 54.6 0 0 1 132.6 78" stroke-width="0.9"/>
                            <path class="globe-whirl-arc globe-w4" d="M78 23.4 A54.6 54.6 0 0 1 132.6 78" stroke-width="0.75"/>
                            <path class="globe-whirl-arc globe-w5" d="M78 23.4 A54.6 54.6 0 0 1 132.6 78" stroke-width="0.6"/>
                        </svg>
                        <!-- Globe layer -->
                        <svg viewBox="0 0 156 156" id="globeLogoSvg" aria-hidden="true">
                            <defs>
                                <clipPath id="globeSphereClip"><circle cx="78" cy="78" r="48.36"/></clipPath>
                            </defs>
                            <circle class="globe-sphere" cx="78" cy="78" r="48.36"/>
                            <g clip-path="url(#globeSphereClip)">
                                <path class="globe-graticule" id="globeGraticulePath"/>
                                <path class="globe-country" id="globeCountriesPath"/>
                            </g>
                            <circle class="globe-sphere-edge" cx="78" cy="78" r="48.36"/>
                        </svg>
                        <!-- Train orbit layer -->
                        <svg viewBox="0 0 156 156" aria-hidden="true" class="globe-train-svg">
                            <g class="train-orbit">
                                <g transform="translate(78, 19)">
                                    <!-- smoke puffs -->
                                    <circle class="train-smoke train-smoke-1" cx="-5" cy="-4" r="1.2"/>
                                    <circle class="train-smoke train-smoke-2" cx="-6" cy="-5.5" r="1"/>
                                    <circle class="train-smoke train-smoke-3" cx="-5.5" cy="-7" r="0.8"/>
                                    <!-- locomotive body -->
                                    <rect class="train-body" x="-7" y="-3" width="14" height="5" rx="1.2"/>
                                    <!-- cabin -->
                                    <rect class="train-body" x="2.5" y="-6.5" width="4.5" height="4" rx="0.8"/>
                                    <!-- chimney -->
                                    <rect class="train-body" x="-5.5" y="-5.5" width="2.5" height="2.5" rx="0.5"/>
                                    <!-- wheels -->
                                    <circle class="train-wheel" cx="-3.5" cy="3" r="1.6"/>
                                    <circle class="train-wheel" cx="3.5" cy="3" r="1.6"/>
                                </g>
                            </g>
                        </svg>
                    </div>
                    <div>
                        <div class="sidebar-logo-text">SOC</div>
                        <div class="sidebar-logo-sub">Reporting System</div>
                    </div>
                </div>
            </div>

            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title"><?= label('Overview', 'Übersicht') ?></div>
                    <a href="index.php?page=dashboard" class="nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                        <span class="nav-item-icon">&#9707;</span>
                        <?= label('Dashboard', 'Dashboard') ?>
                    </a>
                    <a href="index.php?page=threat_logs" class="nav-item <?= $currentPage === 'threat_logs' ? 'active' : '' ?>">
                        <span class="nav-item-icon">&#128737;</span>
                        <?= label('Threat Logs', 'CVE-Protokolle') ?>
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title"><?= label('Machines', 'Maschinen') ?></div>
                    <?php foreach ($machines as $machine): ?>
                    <a href="index.php?page=reports_list&machine=<?= $machine['slot_number'] ?>"
                       class="nav-item <?= ($currentPage === 'reports_list' && get('machine') == $machine['slot_number']) ? 'active' : '' ?>">
                        <span class="nav-item-icon">&#9881;</span>
                        <?= e($machine['name']) ?>
                    </a>
                    <?php endforeach; ?>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title"><?= label('Analytics', 'Analytik') ?></div>
                    <a href="index.php?page=charts" class="nav-item <?= $currentPage === 'charts' ? 'active' : '' ?>">
                        <span class="nav-item-icon">&#9636;</span>
                        <?= label('Charts', 'Diagramme') ?>
                    </a>
                    <a href="index.php?page=threat_intel" class="nav-item <?= $currentPage === 'threat_intel' ? 'active' : '' ?>">
                        <span class="nav-item-icon">&#9888;</span>
                        <?= label('Threat Intel', 'Bedrohungen') ?>
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title"><?= label('Help', 'Hilfe') ?></div>
                    <a href="index.php?page=instructions" class="nav-item <?= $currentPage === 'instructions' ? 'active' : '' ?>">
                        <span class="nav-item-icon">&#9432;</span>
                        <?= label('Instructions', 'Anleitung') ?>
                    </a>
                </div>
            </nav>

            <div class="sidebar-footer">
                <?php if (Session::isAdmin()): ?>
                <a href="index.php?page=assets" class="nav-item admin-nav <?= $currentPage === 'assets' ? 'active' : '' ?>">
                    <span class="nav-item-icon">&#9881;</span>
                    <?= label('Assets', 'Geräte') ?>
                </a>
                <a href="index.php?page=deleted_reports" class="nav-item admin-nav">
                    <span class="nav-item-icon">&#128465;</span>
                    <?= label('Deleted Reports', 'Gelöschte Berichte') ?>
                </a>
                <a href="index.php?page=admin_login" class="nav-item admin-nav">
                    <span class="nav-item-icon">&#9888;</span>
                    <?= label('Admin Panel', 'Admin-Bereich') ?>
                </a>
                <?php endif; ?>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="header">
                <div class="flex gap-1" style="align-items: center;">
                    <button class="mobile-menu-btn" onclick="document.getElementById('sidebar').classList.toggle('open')">&#9776;</button>
                    <h1 class="header-title" id="pageTitle"></h1>
                </div>

                <div class="header-actions">
                    <!-- Theme Toggle -->
                    <button class="theme-toggle" id="themeToggle" onclick="toggleTheme()" title="Toggle light/dark mode">
                        <span class="theme-toggle-icon" id="themeIcon">&#9790;</span>
                    </button>

                    <div class="lang-toggle">
                        <a href="?<?= http_build_query(array_merge($_GET, ['lang' => 'en'])) ?>"
                           class="lang-btn <?= currentLang() === 'en' ? 'active' : '' ?>">EN</a>
                        <a href="?<?= http_build_query(array_merge($_GET, ['lang' => 'de'])) ?>"
                           class="lang-btn <?= currentLang() === 'de' ? 'active' : '' ?>">DE</a>
                    </div>

                    <div class="user-info">
                        <div class="user-avatar"><?= strtoupper(substr($currentUser['display_name'], 0, 1)) ?></div>
                        <span><?= e($currentUser['display_name']) ?></span>
                    </div>

                    <a href="index.php?page=logout" class="btn-logout"><?= label('Logout', 'Abmelden') ?></a>
                </div>
            </header>

            <!-- Page Content -->
            <div class="page-content">
                <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?>">
                    <?= e($flash['message']) ?>
                </div>
                <?php endif; ?>

                <?php require $pageFile; ?>
            </div>
        </main>
    </div>

    <script src="assets/js/app.js?v=2.2"></script>
    <script>
    // Theme toggle
    function toggleTheme() {
        var html = document.documentElement;
        var current = html.getAttribute('data-theme');
        var next = current === 'light' ? 'dark' : 'light';
        if (next === 'dark') {
            html.removeAttribute('data-theme');
        } else {
            html.setAttribute('data-theme', 'light');
        }
        localStorage.setItem('soc-theme', next);
        updateThemeIcon(next);
    }
    function updateThemeIcon(theme) {
        var icon = document.getElementById('themeIcon');
        if (icon) {
            // Moon for dark, sun for light
            icon.innerHTML = theme === 'light' ? '&#9788;' : '&#9790;';
        }
    }
    // Set icon on load
    updateThemeIcon(localStorage.getItem('soc-theme') || 'dark');
    </script>
    <?php if ($currentPage === 'threat_intel'): ?>
    <script src="assets/js/threat_intel.js"></script>
    <?php endif; ?>

    <!-- Globe animation -->
    <script src="https://unpkg.com/d3@7/dist/d3.min.js"></script>
    <script src="https://unpkg.com/topojson-client@3/dist/topojson-client.min.js"></script>
    <script>
    (async function () {
        const CX = 78, CY = 78, R = 48.36;

        const projection = d3.geoOrthographic()
            .translate([CX, CY])
            .scale(R)
            .clipAngle(90)
            .rotate([0, -18, 0]);

        const path = d3.geoPath(projection);
        const graticule = d3.geoGraticule().step([20, 15]);

        const gPath = document.getElementById("globeGraticulePath");
        const cPath = document.getElementById("globeCountriesPath");

        if (!gPath || !cPath) return;

        let countries = null;
        try {
            const res = await fetch("https://cdn.jsdelivr.net/npm/world-atlas@2/countries-110m.json");
            const world = await res.json();
            countries = topojson.feature(world, world.objects.countries);
        } catch (e) {
            console.error("Failed to load world atlas", e);
            return;
        }

        const SECONDS_PER_REV = 8;
        let start = performance.now();

        function frame(now) {
            const t = (now - start) / 1000;
            const lambda = (t / SECONDS_PER_REV) * 360;
            projection.rotate([lambda % 360, -18, 0]);

            gPath.setAttribute("d", path(graticule()) || "");
            cPath.setAttribute("d", path(countries)  || "");

            requestAnimationFrame(frame);
        }
        requestAnimationFrame(frame);
    })();
    </script>
</body>
</html>
