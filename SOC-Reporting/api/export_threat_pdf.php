<?php
/**
 * SOC Reporting System - Threat Log PDF Export
 * Generates a printable HTML view (print-to-PDF) for a threat log entry.
 * Uses TCPDF if available, otherwise falls back to HTML.
 */

Session::requireLogin();

$threatId = (int) get('id', 0);

if ($threatId < 1) {
    die('Invalid parameters');
}

try {
    $db = Database::system();
    $threat = Database::fetchOne($db, "SELECT * FROM threat_logs WHERE id = ?", [$threatId]);

    if (!$threat) {
        die('Threat log not found');
    }
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}

$severityLabels = [
    'critical' => 'CRITICAL',
    'high' => 'HIGH',
    'medium' => 'MEDIUM',
    'low' => 'LOW',
    'info' => 'INFO'
];

$statusLabels = [
    'new' => label('New', 'Neu'),
    'notified' => label('Notified', 'Benachrichtigt'),
    'patched' => label('Patched', 'Gepatcht'),
    'mitigated' => label('Mitigated', 'Entschärft'),
    'closed' => label('Closed', 'Geschlossen')
];

$severityColors = [
    'critical' => '#e74c3c',
    'high' => '#f39c12',
    'medium' => '#00b4dc',
    'low' => '#2ecc71',
    'info' => '#95a5a6'
];

$sevColor = $severityColors[$threat['severity']] ?? '#95a5a6';

// Check if TCPDF is available
$tcpdfPath = APP_ROOT . '/assets/vendor/tcpdf/tcpdf.php';
if (file_exists($tcpdfPath)) {
    require_once $tcpdfPath;

    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator(APP_NAME);
    $pdf->SetAuthor($threat['created_by']);
    $pdf->SetTitle('Threat Log ' . $threat['cve_id']);

    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(20, 20, 20);
    $pdf->AddPage();

    // Header
    $pdf->SetFont('helvetica', 'B', 20);
    $pdf->SetTextColor(0, 90, 120);
    $pdf->Cell(0, 12, APP_NAME, 0, 1, 'L');

    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(0, 6, label('Threat Intelligence Report', 'Bedrohungsbericht'), 0, 1, 'L');
    $pdf->Ln(6);

    // Divider
    $pdf->SetDrawColor(0, 180, 220);
    $pdf->SetLineWidth(0.5);
    $pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
    $pdf->Ln(8);

    // Meta info
    $metaFields = [
        label('CVE ID', 'CVE ID') => $threat['cve_id'],
        label('Title', 'Titel') => $threat['title'],
        label('Severity', 'Schweregrad') => strtoupper($threat['severity']),
        label('CVSS Score', 'CVSS-Score') => $threat['cvss_score'] ? number_format($threat['cvss_score'], 1) : '--',
        label('Status', 'Status') => $statusLabels[$threat['status']] ?? $threat['status'],
        label('Published Date', 'Veröffentlichungsdatum') => $threat['published_date'] ? date('Y-m-d', strtotime($threat['published_date'])) : '--',
        label('Notified At', 'Benachrichtigt am') => $threat['notified_at'] ? formatDatetime($threat['notified_at']) : '--',
        label('Patched At', 'Gepatcht am') => $threat['patched_at'] ? formatDatetime($threat['patched_at']) : '--',
        label('Mitigated At', 'Entschärft am') => $threat['mitigated_at'] ? formatDatetime($threat['mitigated_at']) : '--',
        label('Closed At', 'Geschlossen am') => $threat['closed_at'] ? formatDatetime($threat['closed_at']) : '--',
        label('Created By', 'Erstellt von') => $threat['created_by'],
        label('Created At', 'Erstellt am') => formatDatetime($threat['created_at']),
    ];

    foreach ($metaFields as $metaLabel => $metaValue) {
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->Cell(45, 7, $metaLabel . ':', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(30, 30, 30);
        $pdf->Cell(0, 7, $metaValue, 0, 1, 'L');
    }

    $pdf->Ln(6);
    $pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
    $pdf->Ln(8);

    // Description
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->Cell(0, 6, strtoupper(label('Description', 'Beschreibung')), 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColor(30, 30, 30);
    $pdf->MultiCell(0, 6, $threat['description'] ?: '--', 0, 'L');
    $pdf->Ln(4);

    // Affected Systems
    if ($threat['affected_systems']) {
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->Cell(0, 6, strtoupper(label('Affected Systems', 'Betroffene Systeme')), 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor(30, 30, 30);
        $pdf->MultiCell(0, 6, $threat['affected_systems'], 0, 'L');
        $pdf->Ln(4);
    }

    // Departments
    if ($threat['departments_notified']) {
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->Cell(0, 6, strtoupper(label('Departments Notified', 'Benachrichtigte Abteilungen')), 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor(30, 30, 30);
        $pdf->MultiCell(0, 6, $threat['departments_notified'], 0, 'L');
        $pdf->Ln(4);
    }

    // Mitigation
    if ($threat['mitigation']) {
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->Cell(0, 6, strtoupper(label('Mitigation / Remediation', 'Abhilfe / Behebung')), 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor(30, 30, 30);
        $pdf->MultiCell(0, 6, $threat['mitigation'], 0, 'L');
        $pdf->Ln(4);
    }

    // Footer
    $pdf->Ln(12);
    $pdf->SetFont('helvetica', '', 7);
    $pdf->SetTextColor(150, 150, 150);
    $pdf->Cell(0, 5, 'Generated by ' . APP_NAME . ' on ' . date(DATETIME_FORMAT), 0, 1, 'C');

    auditLog('threat_pdf_export', "Exported PDF for threat log " . $threat['cve_id']);

    $pdf->Output('Threat_' . $threat['cve_id'] . '.pdf', 'D');
    exit;

} else {
    // Fallback: HTML printable view
    auditLog('threat_pdf_export_fallback', "HTML export for threat log " . $threat['cve_id']);

    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title><?= e($threat['cve_id']) ?> - <?= label('Threat Log', 'Bedrohungsprotokoll') ?></title>
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body { font-family: Arial, sans-serif; color: #1a1a2e; padding: 40px; max-width: 800px; margin: 0 auto; line-height: 1.5; }
            h1 { color: #005a78; font-size: 22px; margin-bottom: 4px; }
            .subtitle { color: #666; font-size: 13px; margin-bottom: 20px; }
            .divider { border-top: 2px solid #00b4dc; margin: 16px 0; }
            .header-badges { display: flex; gap: 10px; align-items: center; margin-bottom: 16px; flex-wrap: wrap; }
            .badge { display: inline-block; padding: 4px 12px; border-radius: 4px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
            .badge-severity { background: <?= $sevColor ?>20; color: <?= $sevColor ?>; border: 1px solid <?= $sevColor ?>40; }
            .badge-status { background: #00b4dc20; color: #00b4dc; border: 1px solid #00b4dc40; }
            .badge-cvss { font-family: 'Courier New', monospace; font-size: 14px; font-weight: 700; color: <?= $sevColor ?>; }
            .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0; margin-bottom: 8px; }
            .meta-row { display: flex; padding: 6px 0; font-size: 13px; border-bottom: 1px solid #f0f0f0; }
            .meta-label { width: 150px; font-weight: bold; color: #555; flex-shrink: 0; }
            .meta-value { color: #1a1a2e; }
            .section-title { font-size: 14px; font-weight: bold; color: #005a78; margin: 24px 0 8px; text-transform: uppercase; letter-spacing: 1px; }
            .field-value { font-size: 14px; color: #1a1a2e; white-space: pre-wrap; margin-bottom: 16px; line-height: 1.6; }
            .footer { margin-top: 40px; font-size: 10px; color: #aaa; text-align: center; border-top: 1px solid #eee; padding-top: 12px; }
            .title-block { margin-bottom: 12px; }
            .title-block h2 { font-size: 18px; color: #1a1a2e; margin-bottom: 4px; }
            @media print {
                body { padding: 20px; }
                .no-print { display: none !important; }
            }
        </style>
    </head>
    <body>
        <div class="no-print" style="margin-bottom: 20px; display: flex; gap: 8px;">
            <button onclick="window.print()" style="padding: 10px 20px; background: #005a78; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px;">
                <?= label('Print / Save as PDF', 'Drucken / Als PDF speichern') ?>
            </button>
            <button onclick="window.close()" style="padding: 10px 20px; background: #eee; color: #333; border: none; border-radius: 4px; cursor: pointer; font-size: 14px;">
                <?= label('Close', 'Schließen') ?>
            </button>
        </div>

        <h1><?= e(APP_NAME) ?></h1>
        <div class="subtitle"><?= label('Threat Intelligence Report', 'Bedrohungsbericht') ?></div>
        <div class="divider"></div>

        <div class="title-block">
            <h2><?= e($threat['title']) ?></h2>
        </div>

        <div class="header-badges">
            <span class="badge badge-severity"><?= strtoupper($threat['severity']) ?></span>
            <span class="badge badge-status"><?= e($statusLabels[$threat['status']] ?? $threat['status']) ?></span>
            <?php if ($threat['cvss_score']): ?>
            <span class="badge-cvss">CVSS: <?= number_format($threat['cvss_score'], 1) ?></span>
            <?php endif; ?>
        </div>

        <div class="meta-row"><div class="meta-label"><?= label('CVE ID', 'CVE ID') ?>:</div><div class="meta-value" style="font-family: 'Courier New', monospace;"><?= e($threat['cve_id']) ?></div></div>
        <div class="meta-row"><div class="meta-label"><?= label('Published Date', 'Veröffentlicht') ?>:</div><div class="meta-value"><?= $threat['published_date'] ? date('Y-m-d', strtotime($threat['published_date'])) : '--' ?></div></div>
        <div class="meta-row"><div class="meta-label"><?= label('Notified At', 'Benachrichtigt am') ?>:</div><div class="meta-value"><?= $threat['notified_at'] ? formatDatetime($threat['notified_at']) : '--' ?></div></div>
        <div class="meta-row"><div class="meta-label"><?= label('Patched At', 'Gepatcht am') ?>:</div><div class="meta-value"><?= $threat['patched_at'] ? formatDatetime($threat['patched_at']) : '--' ?></div></div>
        <div class="meta-row"><div class="meta-label"><?= label('Mitigated At', 'Entschärft am') ?>:</div><div class="meta-value"><?= $threat['mitigated_at'] ? formatDatetime($threat['mitigated_at']) : '--' ?></div></div>
        <div class="meta-row"><div class="meta-label"><?= label('Closed At', 'Geschlossen am') ?>:</div><div class="meta-value"><?= $threat['closed_at'] ? formatDatetime($threat['closed_at']) : '--' ?></div></div>
        <div class="meta-row"><div class="meta-label"><?= label('Created By', 'Erstellt von') ?>:</div><div class="meta-value"><?= e($threat['created_by']) ?></div></div>
        <div class="meta-row"><div class="meta-label"><?= label('Created At', 'Erstellt am') ?>:</div><div class="meta-value"><?= formatDatetime($threat['created_at']) ?></div></div>

        <?php if ($threat['departments_notified']): ?>
        <div class="meta-row"><div class="meta-label"><?= label('Departments', 'Abteilungen') ?>:</div><div class="meta-value"><?= e($threat['departments_notified']) ?></div></div>
        <?php endif; ?>

        <div class="divider"></div>

        <div class="section-title"><?= label('Description', 'Beschreibung') ?></div>
        <div class="field-value"><?= e($threat['description']) ?></div>

        <?php if ($threat['affected_systems']): ?>
        <div class="section-title"><?= label('Affected Systems', 'Betroffene Systeme') ?></div>
        <div class="field-value"><?= e($threat['affected_systems']) ?></div>
        <?php endif; ?>

        <?php if ($threat['mitigation']): ?>
        <div class="section-title"><?= label('Mitigation / Remediation', 'Abhilfe / Behebung') ?></div>
        <div class="field-value"><?= e($threat['mitigation']) ?></div>
        <?php endif; ?>

        <div class="footer"><?= label('Generated by', 'Erstellt von') ?> <?= e(APP_NAME) ?> — <?= date(DATETIME_FORMAT) ?></div>
    </body>
    </html>
    <?php
    exit;
}
