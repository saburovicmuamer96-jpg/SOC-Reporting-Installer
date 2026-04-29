/**
 * SOC Reporting System - Splunk-style Charts
 * Uses Chart.js with dark SOC theme matching Splunk's visual style.
 * Includes chartjs-plugin-datalabels for value display and jsPDF export.
 */

// Register datalabels plugin globally
Chart.register(ChartDataLabels);

// Splunk-inspired color palette
const splunkColors = {
    blue:    '#1e93c6',
    green:   '#65a637',
    orange:  '#f2b827',
    red:     '#d93f3c',
    purple:  '#6a5c9e',
    teal:    '#009ccc',
    pink:    '#e8548c',
    lime:    '#a2cc3e',
    // Dim variants
    blueDim:    'rgba(30, 147, 198, 0.2)',
    greenDim:   'rgba(101, 166, 55, 0.2)',
    orangeDim:  'rgba(242, 184, 39, 0.2)',
    redDim:     'rgba(217, 63, 60, 0.2)',
    purpleDim:  'rgba(106, 92, 158, 0.2)',
    tealDim:    'rgba(0, 156, 204, 0.2)',
    pinkDim:    'rgba(232, 84, 140, 0.2)',
    limeDim:    'rgba(162, 204, 62, 0.2)',
};

// Machine colors (each machine gets a unique color)
const machineColorSet = [
    { border: splunkColors.blue,   bg: splunkColors.blueDim },
    { border: splunkColors.green,  bg: splunkColors.greenDim },
    { border: splunkColors.orange, bg: splunkColors.orangeDim },
    { border: splunkColors.red,    bg: splunkColors.redDim }
];

const statusColorMap = {
    'submitted': splunkColors.blue,
    'reviewed':  splunkColors.green,
    'draft':     splunkColors.orange,
    'closed':    '#5a6578'
};

const allColors = [
    splunkColors.blue, splunkColors.green, splunkColors.orange, splunkColors.red,
    splunkColors.purple, splunkColors.teal, splunkColors.pink, splunkColors.lime
];

// Splunk dark theme defaults
const splunkTheme = {
    bg: '#1a1c21',
    panelBg: '#171820',
    gridColor: 'rgba(255,255,255,0.06)',
    textColor: '#a8b0bd',
    textMuted: '#5a6578',
    tooltipBg: '#1a1c21',
    tooltipBorder: '#333640',
    fontFamily: "'Inter', -apple-system, sans-serif",
    monoFont: "'JetBrains Mono', 'Consolas', monospace"
};

// Default datalabels config for bar/column charts
const barDataLabels = {
    display: function(ctx) {
        return ctx.dataset.data[ctx.dataIndex] > 0;
    },
    color: '#ffffff',
    anchor: 'end',
    align: 'end',
    offset: 5,
    font: { family: "'JetBrains Mono', monospace", size: 14, weight: 700 },
    formatter: function(value) {
        return value;
    },
    clamp: true,
    backgroundColor: 'rgba(0, 0, 0, 0.75)',
    borderRadius: 4,
    padding: { top: 3, bottom: 3, left: 6, right: 6 }
};

// Datalabels for horizontal bar charts
const hBarDataLabels = {
    display: function(ctx) {
        return ctx.dataset.data[ctx.dataIndex] > 0;
    },
    color: '#ffffff',
    anchor: 'end',
    align: 'end',
    offset: 8,
    font: { family: "'JetBrains Mono', monospace", size: 14, weight: 700 },
    clamp: true,
    backgroundColor: 'rgba(0, 0, 0, 0.75)',
    borderRadius: 4,
    padding: { top: 3, bottom: 3, left: 6, right: 6 }
};

// Datalabels for line charts (show only at points)
const lineDataLabels = {
    display: function(ctx) {
        var data = ctx.dataset.data;
        // Show label at first, last, and peak points to avoid clutter
        if (data.length <= 10) return data[ctx.dataIndex] > 0;
        if (ctx.dataIndex === 0 || ctx.dataIndex === data.length - 1) return data[ctx.dataIndex] > 0;
        // Show at local maxima
        var i = ctx.dataIndex;
        if (i > 0 && i < data.length - 1 && data[i] >= data[i-1] && data[i] >= data[i+1] && data[i] > 0) return true;
        return false;
    },
    color: '#ffffff',
    anchor: 'end',
    align: 'top',
    offset: 8,
    font: { family: "'JetBrains Mono', monospace", size: 13, weight: 700 },
    backgroundColor: 'rgba(0, 0, 0, 0.8)',
    borderRadius: 4,
    padding: { top: 3, bottom: 3, left: 6, right: 6 },
    clamp: true
};

function baseOptions(hideX) {
    return {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 600, easing: 'easeOutQuart' },
        layout: {
            padding: {
                top: 20,
                right: 20,
                bottom: 5,
                left: 10
            }
        },
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                backgroundColor: splunkTheme.tooltipBg,
                titleColor: '#e2e8f0',
                bodyColor: '#a8b0bd',
                borderColor: splunkTheme.tooltipBorder,
                borderWidth: 1,
                padding: 12,
                cornerRadius: 5,
                titleFont: { family: splunkTheme.fontFamily, size: 14, weight: 600 },
                bodyFont: { family: splunkTheme.monoFont, size: 13 },
                displayColors: true,
                boxPadding: 6
            },
            datalabels: {
                display: false // disabled by default, enabled per chart
            }
        },
        scales: {
            x: {
                display: hideX !== true,
                ticks: {
                    color: splunkTheme.textMuted,
                    font: { size: 12, family: splunkTheme.fontFamily, weight: 500 },
                    maxRotation: 0,
                    padding: 6
                },
                grid: { color: splunkTheme.gridColor, drawBorder: false }
            },
            y: {
                ticks: {
                    color: splunkTheme.textMuted,
                    font: { size: 12, family: splunkTheme.monoFont, weight: 500 },
                    precision: 0,
                    padding: 10
                },
                grid: { color: splunkTheme.gridColor, drawBorder: false },
                beginAtZero: true,
                grace: '5%'
            }
        }
    };
}

async function fetchData(chartType, machine, params) {
    var url = `index.php?page=api_charts&chart=${chartType}&machine=${machine}`;
    if (params && params.date_from) {
        url += `&date_from=${params.date_from}&date_to=${params.date_to || ''}`;
    } else {
        url += `&days=${params && params.days ? params.days : 30}`;
    }
    const resp = await fetch(url);
    return await resp.json();
}

function destroyIfExists(canvasId) {
    const existing = Chart.getChart(canvasId);
    if (existing) existing.destroy();
}

async function loadSplunkDashboard(machine, daysOrParams) {
    // Accept either a number (days) or an object {date_from, date_to}
    var params = typeof daysOrParams === 'object' ? daysOrParams : { days: daysOrParams };

    // Load all data in parallel
    const [kpi, timeline, status, users, hourly, weekday, reactionTrend, reactionMachine, machineData, topValues] =
        await Promise.all([
            fetchData('kpi_totals', machine, params),
            fetchData('timeline_by_machine', machine, params),
            fetchData('reports_by_status', machine, params),
            fetchData('reports_by_user', machine, params),
            fetchData('reports_by_hour', machine, params),
            fetchData('reports_by_weekday', machine, params),
            fetchData('reaction_time_trend', machine, params),
            fetchData('reaction_by_machine', machine, params),
            fetchData('reports_by_machine', machine, params),
            fetchData('top_field_values', machine, params)
        ]);

    // === KPI Panels ===
    document.getElementById('kpiTotalValue').textContent = kpi.total != null ? kpi.total.toLocaleString() : '0';
    document.getElementById('kpiTodayValue').textContent = kpi.today != null ? kpi.today.toLocaleString() : '0';
    document.getElementById('kpiReactionValue').textContent = kpi.avg_reaction_min != null ? kpi.avg_reaction_min : '--';
    document.getElementById('kpiUsersValue').textContent = kpi.active_users != null ? kpi.active_users : '0';

    // === Timeline by Machine (Stacked Area) ===
    if (timeline.datasets && timeline.datasets.length > 0) {
        destroyIfExists('timelineMachineChart');
        const datasets = timeline.datasets.map((ds, i) => ({
            label: ds.name,
            data: ds.values,
            borderColor: machineColorSet[i % machineColorSet.length].border,
            backgroundColor: machineColorSet[i % machineColorSet.length].bg,
            fill: true,
            tension: 0.35,
            borderWidth: 2,
            pointRadius: 2,
            pointHoverRadius: 5,
            pointBackgroundColor: machineColorSet[i % machineColorSet.length].border,
            datalabels: { display: false }
        }));
        new Chart('timelineMachineChart', {
            type: 'line',
            data: { labels: timeline.labels, datasets: datasets },
            options: {
                ...baseOptions(),
                plugins: {
                    ...baseOptions().plugins,
                    legend: {
                        display: true,
                        position: 'top',
                        align: 'end',
                        labels: {
                            color: splunkTheme.textColor,
                            font: { family: splunkTheme.fontFamily, size: 13, weight: 600 },
                            padding: 18,
                            usePointStyle: true,
                            pointStyleWidth: 10
                        }
                    },
                    datalabels: { display: false }
                },
                interaction: { mode: 'index', intersect: false }
            }
        });
    }

    // === Status Pie Chart ===
    if (status.labels && status.labels.length > 0) {
        destroyIfExists('statusPieChart');
        const bgColors = status.labels.map(s => statusColorMap[s] || splunkColors.purple);
        const statusTotal = status.values.reduce((a, b) => a + b, 0);
        new Chart('statusPieChart', {
            type: 'doughnut',
            data: {
                labels: status.labels.map(s => s.charAt(0).toUpperCase() + s.slice(1)),
                datasets: [{
                    data: status.values,
                    backgroundColor: bgColors,
                    borderColor: splunkTheme.panelBg,
                    borderWidth: 3,
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '55%',
                layout: {
                    padding: {
                        top: 20,
                        right: 20,
                        bottom: 20,
                        left: 20
                    }
                },
                plugins: {
                    ...baseOptions().plugins,
                    legend: {
                        display: true,
                        position: 'right',
                        labels: {
                            color: splunkTheme.textColor,
                            font: { family: splunkTheme.fontFamily, size: 13, weight: 600 },
                            padding: 14,
                            usePointStyle: true,
                            pointStyleWidth: 12
                        }
                    },
                    datalabels: {
                        display: function(ctx) {
                            var pct = ctx.dataset.data[ctx.dataIndex] / statusTotal * 100;
                            return pct > 5; // Only show if segment is > 5%
                        },
                        color: '#ffffff',
                        font: { family: "'JetBrains Mono', monospace", size: 14, weight: 700 },
                        formatter: function(value) {
                            var pct = Math.round(value / statusTotal * 100);
                            return value + '\n' + pct + '%';
                        },
                        textAlign: 'center',
                        anchor: 'center',
                        align: 'center',
                        offset: 0
                    }
                }
            }
        });
    }

    // === User Bar Chart (horizontal) ===
    if (users.labels && users.labels.length > 0) {
        destroyIfExists('userBarChart');
        new Chart('userBarChart', {
            type: 'bar',
            data: {
                labels: users.labels,
                datasets: [{
                    data: users.values,
                    backgroundColor: users.labels.map((_, i) => allColors[i % allColors.length] + '44'),
                    borderColor: users.labels.map((_, i) => allColors[i % allColors.length]),
                    borderWidth: 1,
                    borderRadius: 3
                }]
            },
            options: {
                ...baseOptions(),
                indexAxis: 'y',
                plugins: {
                    ...baseOptions().plugins,
                    datalabels: hBarDataLabels
                }
            }
        });
    }

    // === Hourly Column Chart ===
    if (hourly.labels) {
        destroyIfExists('hourlyChart');
        const maxHourly = Math.max(...hourly.values, 1);
        const hourlyBg = hourly.values.map(v => {
            const intensity = v / maxHourly;
            if (intensity > 0.75) return splunkColors.red + 'aa';
            if (intensity > 0.5) return splunkColors.orange + 'aa';
            if (intensity > 0.25) return splunkColors.green + 'aa';
            return splunkColors.blue + '66';
        });
        new Chart('hourlyChart', {
            type: 'bar',
            data: {
                labels: hourly.labels,
                datasets: [{
                    data: hourly.values,
                    backgroundColor: hourlyBg,
                    borderColor: 'transparent',
                    borderRadius: 2,
                    barPercentage: 0.85
                }]
            },
            options: {
                ...baseOptions(),
                plugins: {
                    ...baseOptions().plugins,
                    datalabels: {
                        display: function(ctx) {
                            return ctx.dataset.data[ctx.dataIndex] > 0;
                        },
                        color: '#e2e8f0',
                        anchor: 'end',
                        align: 'end',
                        offset: 1,
                        font: { family: "'JetBrains Mono', monospace", size: 8, weight: 600 }
                    }
                }
            }
        });
    }

    // === Weekday Column Chart ===
    if (weekday.labels) {
        destroyIfExists('weekdayChart');
        new Chart('weekdayChart', {
            type: 'bar',
            data: {
                labels: weekday.labels,
                datasets: [{
                    data: weekday.values,
                    backgroundColor: weekday.labels.map((_, i) => allColors[i % allColors.length] + '66'),
                    borderColor: weekday.labels.map((_, i) => allColors[i % allColors.length]),
                    borderWidth: 1,
                    borderRadius: 3,
                    barPercentage: 0.7
                }]
            },
            options: {
                ...baseOptions(),
                plugins: {
                    ...baseOptions().plugins,
                    datalabels: barDataLabels
                }
            }
        });
    }

    // === Reaction Time Trend (Line) ===
    if (reactionTrend.labels && reactionTrend.labels.length > 0) {
        destroyIfExists('reactionTrendChart');
        new Chart('reactionTrendChart', {
            type: 'line',
            data: {
                labels: reactionTrend.labels,
                datasets: [{
                    label: 'Avg Reaction (min)',
                    data: reactionTrend.values,
                    borderColor: splunkColors.orange,
                    backgroundColor: splunkColors.orangeDim,
                    fill: true,
                    tension: 0.3,
                    borderWidth: 2,
                    pointRadius: 3,
                    pointBackgroundColor: splunkColors.orange,
                    pointHoverRadius: 6
                }]
            },
            options: {
                ...baseOptions(),
                plugins: {
                    ...baseOptions().plugins,
                    datalabels: lineDataLabels
                },
                scales: {
                    ...baseOptions().scales,
                    y: {
                        ...baseOptions().scales.y,
                        title: {
                            display: true,
                            text: 'Minutes',
                            color: splunkTheme.textMuted,
                            font: { size: 12, weight: 600 }
                        }
                    }
                }
            }
        });
    }

    // === Reaction by Machine (Bar) ===
    if (reactionMachine.labels) {
        destroyIfExists('reactionMachineChart');
        new Chart('reactionMachineChart', {
            type: 'bar',
            data: {
                labels: reactionMachine.labels,
                datasets: [{
                    data: reactionMachine.values,
                    backgroundColor: reactionMachine.labels.map((_, i) => machineColorSet[i % machineColorSet.length].bg),
                    borderColor: reactionMachine.labels.map((_, i) => machineColorSet[i % machineColorSet.length].border),
                    borderWidth: 2,
                    borderRadius: 4,
                    barPercentage: 0.6
                }]
            },
            options: {
                ...baseOptions(),
                plugins: {
                    ...baseOptions().plugins,
                    datalabels: barDataLabels
                },
                scales: {
                    ...baseOptions().scales,
                    y: {
                        ...baseOptions().scales.y,
                        title: {
                            display: true,
                            text: 'Minutes',
                            color: splunkTheme.textMuted,
                            font: { size: 12, weight: 600 }
                        }
                    }
                }
            }
        });
    }

    // === Events by Machine (Column) ===
    if (machineData.labels) {
        destroyIfExists('machineBarChart');
        new Chart('machineBarChart', {
            type: 'bar',
            data: {
                labels: machineData.labels,
                datasets: [{
                    data: machineData.values,
                    backgroundColor: machineColorSet.map(c => c.border + 'bb'),
                    borderColor: machineColorSet.map(c => c.border),
                    borderWidth: 2,
                    borderRadius: 4,
                    barPercentage: 0.6
                }]
            },
            options: {
                ...baseOptions(),
                plugins: {
                    ...baseOptions().plugins,
                    datalabels: barDataLabels
                }
            }
        });
    }

    // === Top Field Values (Horizontal Bar) ===
    if (topValues.labels && topValues.labels.length > 0) {
        destroyIfExists('topValuesChart');
        new Chart('topValuesChart', {
            type: 'bar',
            data: {
                labels: topValues.labels.map(l => l.length > 30 ? l.substring(0, 28) + '...' : l),
                datasets: [{
                    data: topValues.values,
                    backgroundColor: topValues.labels.map((_, i) => allColors[i % allColors.length] + '55'),
                    borderColor: topValues.labels.map((_, i) => allColors[i % allColors.length]),
                    borderWidth: 1,
                    borderRadius: 2
                }]
            },
            options: {
                ...baseOptions(),
                indexAxis: 'y',
                plugins: {
                    ...baseOptions().plugins,
                    datalabels: hBarDataLabels
                },
                scales: {
                    ...baseOptions().scales,
                    y: {
                        ...baseOptions().scales.y,
                        ticks: {
                            color: splunkTheme.textColor,
                            font: { size: 10, family: splunkTheme.monoFont }
                        }
                    }
                }
            }
        });
    }
}

// === PDF Export Function ===
function exportDashboardPDF() {
    var btn = document.getElementById('exportPdfBtn');
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Generating...';
    }

    // Chart panels to export: [canvasId, title]
    var panels = [
        ['timelineMachineChart', 'Events Over Time by Machine'],
        ['statusPieChart', 'Events by Status'],
        ['userBarChart', 'Events by Analyst'],
        ['hourlyChart', 'Events by Hour of Day'],
        ['weekdayChart', 'Events by Day of Week'],
        ['reactionTrendChart', 'Reaction Time Trend'],
        ['reactionMachineChart', 'Avg Reaction Time by Machine'],
        ['machineBarChart', 'Events by Machine'],
        ['topValuesChart', 'Top Field Values']
    ];

    try {
        var doc = new jspdf.jsPDF('p', 'mm', 'a4');
        var pageWidth = 210;
        var pageHeight = 297;
        var margin = 15;
        var contentWidth = pageWidth - margin * 2;
        var yPos = margin;

        // === Page 1: Header + KPI ===
        // Dark header bar
        doc.setFillColor(10, 14, 23);
        doc.rect(0, 0, pageWidth, 45, 'F');

        doc.setFont('helvetica', 'bold');
        doc.setFontSize(36);
        doc.setTextColor(0, 212, 255);
        doc.text('SOC Reporting System', margin, 20);

        doc.setFontSize(18);
        doc.setTextColor(160, 174, 192);
        doc.text('Security Dashboard - Analytics Report', margin, 30);

        doc.setFontSize(12);
        doc.setTextColor(90, 101, 120);
        doc.text('Generated: ' + new Date().toLocaleString(), margin, 40);

        yPos = 55;

        // KPI Row
        var kpiData = [
            { label: 'Total Events', value: document.getElementById('kpiTotalValue').textContent, color: [226, 232, 240] },
            { label: 'Events Today', value: document.getElementById('kpiTodayValue').textContent, color: [0, 255, 136] },
            { label: 'Avg Reaction Time', value: document.getElementById('kpiReactionValue').textContent + ' min', color: [255, 170, 0] },
            { label: 'Active Analysts', value: document.getElementById('kpiUsersValue').textContent, color: [30, 147, 198] }
        ];

        var kpiWidth = (contentWidth - 9) / 4;
        kpiData.forEach(function(k, i) {
            var x = margin + i * (kpiWidth + 3);

            // KPI box background
            doc.setFillColor(19, 25, 38);
            doc.roundedRect(x, yPos, kpiWidth, 32, 3, 3, 'F');

            // Value
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(28);
            doc.setTextColor(k.color[0], k.color[1], k.color[2]);
            doc.text(k.value, x + kpiWidth / 2, yPos + 15, { align: 'center' });

            // Label
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(9);
            doc.setTextColor(136, 146, 164);
            doc.text(k.label, x + kpiWidth / 2, yPos + 26, { align: 'center' });
        });

        yPos += 42;

        // === Machine Color Legend ===
        var timelineChart = Chart.getChart('timelineMachineChart');
        if (timelineChart && timelineChart.data.datasets && timelineChart.data.datasets.length > 0) {
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(14);
            doc.setTextColor(60, 70, 90);
            doc.text('Machine Colors:', margin, yPos);

            var legendX = margin + 42;
            timelineChart.data.datasets.forEach(function(ds, i) {
                if (i >= 4) return; // Max 4 machines

                // Color box
                var rgb = machineColorSet[i % machineColorSet.length].border;
                var r = parseInt(rgb.slice(1, 3), 16);
                var g = parseInt(rgb.slice(3, 5), 16);
                var b = parseInt(rgb.slice(5, 7), 16);
                doc.setFillColor(r, g, b);
                doc.roundedRect(legendX, yPos - 3, 6, 6, 1, 1, 'F');

                // Label
                doc.setFont('helvetica', 'normal');
                doc.setFontSize(12);
                doc.setTextColor(80, 90, 110);
                doc.text(ds.label, legendX + 9, yPos);

                legendX += 45;
            });

            yPos += 16;
        }

        // === Charts ===
        var chartWidth = contentWidth * 0.75; // 75% of page width
        panels.forEach(function(panel) {
            var canvasId = panel[0];
            var title = panel[1];
            var canvas = document.getElementById(canvasId);

            if (!canvas) return;

            var chart = Chart.getChart(canvasId);
            if (!chart) return;

            // Check if we need a new page
            var chartHeight = 85;
            if (canvasId === 'timelineMachineChart') chartHeight = 95;

            if (yPos + chartHeight + 50 > pageHeight - margin) {
                doc.addPage();
                yPos = margin;
            }

            // Chart title
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(11);
            doc.setTextColor(30, 42, 58);
            doc.text(title, margin, yPos + 4);

            // Separator line
            doc.setDrawColor(200, 210, 220);
            doc.setLineWidth(0.3);
            doc.line(margin, yPos + 7, margin + contentWidth, yPos + 7);

            yPos += 10;

            // Render chart as image with higher resolution
            var imgData = canvas.toDataURL('image/png', 1.0);
            var ratio = canvas.width / canvas.height;
            var imgWidth = chartWidth;
            var imgHeight = imgWidth / ratio;

            // Cap height and maintain aspect ratio
            if (imgHeight > chartHeight) {
                imgHeight = chartHeight;
                imgWidth = imgHeight * ratio;
            }

            // Center the chart
            var imgX = margin + (contentWidth - imgWidth) / 2;
            doc.addImage(imgData, 'PNG', imgX, yPos, imgWidth, imgHeight);

            yPos += imgHeight + 10;
        });

        // Footer on last page
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(7);
        doc.setTextColor(150, 150, 150);
        doc.text('SOC Reporting System - Confidential', pageWidth / 2, pageHeight - 8, { align: 'center' });

        doc.save('SOC_Dashboard_' + new Date().toISOString().slice(0, 10) + '.pdf');
    } catch (e) {
        alert('PDF export error: ' + e.message);
    }

    if (btn) {
        btn.disabled = false;
        btn.textContent = btn.getAttribute('data-label') || 'Export PDF';
    }
}
