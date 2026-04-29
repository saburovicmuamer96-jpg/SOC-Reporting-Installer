<?php
/**
 * SOC Reporting System - Charts & Analytics (Splunk-style Dashboard)
 */

$selectedMachine = (int) get('machine', 0);
?>

<script>document.getElementById('pageTitle').textContent = '<?= label('Security Dashboard', 'Sicherheits-Dashboard') ?>';</script>

<!-- Splunk-style Filter Bar -->
<div class="splunk-toolbar">
    <div class="splunk-toolbar-left">
        <div class="splunk-toolbar-title"><?= label('Security Dashboard', 'Sicherheits-Dashboard') ?></div>
        <div class="splunk-toolbar-subtitle"><?= label('SOC Reporting Analytics', 'SOC-Berichtsanalytik') ?></div>
    </div>
    <div class="splunk-toolbar-right">
        <div class="splunk-filter-group">
            <span class="splunk-filter-label"><?= label('Source:', 'Quelle:') ?></span>
            <a href="index.php?page=charts&machine=0"
               class="splunk-pill <?= $selectedMachine === 0 ? 'active' : '' ?>">
                <?= label('All Machines', 'Alle Maschinen') ?>
            </a>
            <?php foreach ($machines as $m): ?>
            <a href="index.php?page=charts&machine=<?= $m['slot_number'] ?>"
               class="splunk-pill <?= $selectedMachine === $m['slot_number'] ? 'active' : '' ?>">
                <?= e($m['name']) ?>
            </a>
            <?php endforeach; ?>
        </div>
        <div class="splunk-filter-group">
            <span class="splunk-filter-label"><?= label('Time:', 'Zeit:') ?></span>
            <div id="chartTimePicker"></div>
        </div>
        <button id="exportPdfBtn" class="splunk-pill" onclick="exportDashboardPDF()"
                data-label="<?= label('Export PDF', 'PDF Export') ?>"
                style="cursor: pointer; background: rgba(0, 212, 255, 0.12); color: #00d4ff; border-color: rgba(0, 212, 255, 0.3);">
            &#128196; <?= label('Export PDF', 'PDF Export') ?>
        </button>
    </div>
</div>

<!-- KPI Row -->
<div class="splunk-kpi-row" id="kpiRow">
    <div class="splunk-kpi" id="kpiTotal">
        <div class="splunk-kpi-value" id="kpiTotalValue">--</div>
        <div class="splunk-kpi-label"><?= label('Total Events', 'Gesamt-Ereignisse') ?></div>
        <div class="splunk-kpi-trend" id="kpiTotalTrend"></div>
    </div>
    <div class="splunk-kpi" id="kpiToday">
        <div class="splunk-kpi-value splunk-kpi-green" id="kpiTodayValue">--</div>
        <div class="splunk-kpi-label"><?= label('Events Today', 'Ereignisse Heute') ?></div>
    </div>
    <div class="splunk-kpi" id="kpiReaction">
        <div class="splunk-kpi-value splunk-kpi-orange" id="kpiReactionValue">--</div>
        <div class="splunk-kpi-label"><?= label('Avg Reaction Time', 'Durchschn. Reaktionszeit') ?></div>
        <div class="splunk-kpi-unit"><?= label('minutes', 'Minuten') ?></div>
    </div>
    <div class="splunk-kpi" id="kpiUsers">
        <div class="splunk-kpi-value splunk-kpi-blue" id="kpiUsersValue">--</div>
        <div class="splunk-kpi-label"><?= label('Active Analysts', 'Aktive Analysten') ?></div>
    </div>
</div>

<!-- Row 1: Full-width multi-machine timeline -->
<div class="splunk-panel splunk-panel-full">
    <div class="splunk-panel-header">
        <div class="splunk-panel-title"><?= label('Events Over Time by Machine', 'Ereignisse im Zeitverlauf nach Maschine') ?></div>
        <div class="splunk-panel-type">Area Chart</div>
    </div>
    <div class="splunk-panel-body" style="height: 300px;">
        <canvas id="timelineMachineChart"></canvas>
    </div>
</div>

<!-- Row 2: Two panels -->
<div class="splunk-row">
    <div class="splunk-panel">
        <div class="splunk-panel-header">
            <div class="splunk-panel-title"><?= label('Events by Status', 'Ereignisse nach Status') ?></div>
            <div class="splunk-panel-type">Pie Chart</div>
        </div>
        <div class="splunk-panel-body" style="height: 280px;">
            <canvas id="statusPieChart"></canvas>
        </div>
    </div>
    <div class="splunk-panel">
        <div class="splunk-panel-header">
            <div class="splunk-panel-title"><?= label('Events by Analyst', 'Ereignisse nach Analyst') ?></div>
            <div class="splunk-panel-type">Bar Chart</div>
        </div>
        <div class="splunk-panel-body" style="height: 280px;">
            <canvas id="userBarChart"></canvas>
        </div>
    </div>
</div>

<!-- Row 3: Two panels -->
<div class="splunk-row">
    <div class="splunk-panel">
        <div class="splunk-panel-header">
            <div class="splunk-panel-title"><?= label('Events by Hour of Day', 'Ereignisse nach Tageszeit') ?></div>
            <div class="splunk-panel-type">Column Chart</div>
        </div>
        <div class="splunk-panel-body" style="height: 260px;">
            <canvas id="hourlyChart"></canvas>
        </div>
    </div>
    <div class="splunk-panel">
        <div class="splunk-panel-header">
            <div class="splunk-panel-title"><?= label('Events by Day of Week', 'Ereignisse nach Wochentag') ?></div>
            <div class="splunk-panel-type">Column Chart</div>
        </div>
        <div class="splunk-panel-body" style="height: 260px;">
            <canvas id="weekdayChart"></canvas>
        </div>
    </div>
</div>

<!-- Row 4: Two panels -->
<div class="splunk-row">
    <div class="splunk-panel">
        <div class="splunk-panel-header">
            <div class="splunk-panel-title"><?= label('Reaction Time Trend', 'Reaktionszeit-Trend') ?></div>
            <div class="splunk-panel-type">Line Chart</div>
        </div>
        <div class="splunk-panel-body" style="height: 260px;">
            <canvas id="reactionTrendChart"></canvas>
        </div>
    </div>
    <div class="splunk-panel">
        <div class="splunk-panel-header">
            <div class="splunk-panel-title"><?= label('Avg Reaction Time by Machine', 'Durchschn. Reaktionszeit nach Maschine') ?></div>
            <div class="splunk-panel-type">Bar Chart</div>
        </div>
        <div class="splunk-panel-body" style="height: 260px;">
            <canvas id="reactionMachineChart"></canvas>
        </div>
    </div>
</div>

<!-- Row 5: Two panels -->
<div class="splunk-row">
    <div class="splunk-panel">
        <div class="splunk-panel-header">
            <div class="splunk-panel-title"><?= label('Events by Machine', 'Ereignisse nach Maschine') ?></div>
            <div class="splunk-panel-type">Column Chart</div>
        </div>
        <div class="splunk-panel-body" style="height: 260px;">
            <canvas id="machineBarChart"></canvas>
        </div>
    </div>
    <div class="splunk-panel">
        <div class="splunk-panel-header">
            <div class="splunk-panel-title"><?= label('Top Field Values', 'Häufigste Feldwerte') ?></div>
            <div class="splunk-panel-type">Bar Chart</div>
        </div>
        <div class="splunk-panel-body" style="height: 260px;">
            <canvas id="topValuesChart"></canvas>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
<script src="assets/js/timepicker.js"></script>
<script src="assets/js/charts.js"></script>
<script>
    var chartMachine = <?= $selectedMachine ?>;
    var isEnglish = <?= currentLang() === 'en' ? 'true' : 'false' ?>;

    createTimePicker(document.getElementById('chartTimePicker'), {
        labelEn: isEnglish,
        initial: { preset: '30d', label: isEnglish ? 'Last 30 days' : 'Letzte 30 Tage' },
        onChange: function(sel) {
            loadSplunkDashboard(chartMachine, {
                date_from: sel.date_from,
                date_to: sel.date_to
            });
        }
    });

    // Initial load with 30 days
    loadSplunkDashboard(chartMachine, { days: 30 });
</script>
