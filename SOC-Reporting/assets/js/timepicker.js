/**
 * SOC Reporting System - Splunk-style Time Picker Component
 * Reusable dropdown with presets and custom date/time range.
 */

function createTimePicker(container, options) {
    var opts = options || {};
    var onChange = opts.onChange || function() {};
    var alignLeft = opts.alignLeft || false;
    var currentSelection = opts.initial || { preset: '30d', label: 'Last 30 days' };
    var labelEn = opts.labelEn !== false;

    // Preset definitions with date calculation
    var presets = [
        { key: '7d',   label: labelEn ? 'Last 7 days'     : 'Letzte 7 Tage',       days: 7 },
        { key: '30d',  label: labelEn ? 'Last 30 days'    : 'Letzte 30 Tage',      days: 30 },
        { key: '90d',  label: labelEn ? 'Last 90 days'    : 'Letzte 90 Tage',      days: 90 },
        { key: '1y',   label: labelEn ? 'Last 1 year'     : 'Letztes Jahr',        days: 365 },
        { key: 'prev_month', label: labelEn ? 'Previous Month' : 'Vorheriger Monat' },
        { key: 'prev_year',  label: labelEn ? 'Previous Year'  : 'Vorheriges Jahr' },
        { key: 'ytd',  label: labelEn ? 'Year to Date'    : 'Jahr bis heute' },
        { key: 'all',  label: labelEn ? 'All Time'         : 'Gesamte Zeit',       days: 3650 }
    ];

    function calcPresetDates(key) {
        var now = new Date();
        var from, to;
        to = new Date(now.getFullYear(), now.getMonth(), now.getDate(), 23, 59, 59);

        switch (key) {
            case '7d':   from = new Date(now.getTime() - 7 * 86400000); break;
            case '30d':  from = new Date(now.getTime() - 30 * 86400000); break;
            case '90d':  from = new Date(now.getTime() - 90 * 86400000); break;
            case '1y':   from = new Date(now.getTime() - 365 * 86400000); break;
            case 'prev_month':
                from = new Date(now.getFullYear(), now.getMonth() - 1, 1);
                to = new Date(now.getFullYear(), now.getMonth(), 0, 23, 59, 59);
                break;
            case 'prev_year':
                from = new Date(now.getFullYear() - 1, 0, 1);
                to = new Date(now.getFullYear() - 1, 11, 31, 23, 59, 59);
                break;
            case 'ytd':
                from = new Date(now.getFullYear(), 0, 1);
                break;
            case 'all':
                from = new Date(2000, 0, 1);
                break;
            default:
                from = new Date(now.getTime() - 30 * 86400000);
        }
        return {
            from: formatDate(from),
            to: formatDate(to)
        };
    }

    function formatDate(d) {
        var y = d.getFullYear();
        var m = String(d.getMonth() + 1).padStart(2, '0');
        var day = String(d.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + day;
    }

    function formatDatetime(d) {
        return formatDate(d) + 'T' + String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
    }

    // Build HTML
    var wrapper = document.createElement('div');
    wrapper.className = 'time-picker-wrapper';

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'time-picker-btn';
    btn.innerHTML = '<span class="tp-icon">&#128339;</span> <span class="tp-label">' + escapeHtml(currentSelection.label) + '</span> <span class="tp-arrow">&#9660;</span>';

    var dropdown = document.createElement('div');
    dropdown.className = 'time-picker-dropdown' + (alignLeft ? ' align-left' : '');

    var presetsLabel = labelEn ? 'PRESETS' : 'VOREINSTELLUNGEN';
    var customLabel = labelEn ? 'CUSTOM RANGE' : 'BENUTZERDEFINIERT';
    var fromLabel = labelEn ? 'From' : 'Von';
    var toLabel = labelEn ? 'To' : 'Bis';
    var applyLabel = labelEn ? 'Apply' : 'Anwenden';
    var cancelLabel = labelEn ? 'Cancel' : 'Abbrechen';

    dropdown.innerHTML =
        '<div class="tp-header">' +
            '<div class="tp-tab active" data-tab="presets">' + presetsLabel + '</div>' +
            '<div class="tp-tab" data-tab="custom">' + customLabel + '</div>' +
        '</div>' +
        '<div class="tp-body active" data-tab="presets">' +
            '<div class="tp-presets">' +
                presets.map(function(p) {
                    return '<button type="button" class="tp-preset' + (currentSelection.preset === p.key ? ' active' : '') + '" data-key="' + p.key + '">' + escapeHtml(p.label) + '</button>';
                }).join('') +
            '</div>' +
        '</div>' +
        '<div class="tp-body" data-tab="custom">' +
            '<div class="tp-custom-range">' +
                '<div class="tp-range-row">' +
                    '<span class="tp-range-label">' + fromLabel + '</span>' +
                    '<input type="datetime-local" class="tp-range-input" id="tpFrom">' +
                '</div>' +
                '<div class="tp-range-row">' +
                    '<span class="tp-range-label">' + toLabel + '</span>' +
                    '<input type="datetime-local" class="tp-range-input" id="tpTo">' +
                '</div>' +
                '<div class="tp-apply-row">' +
                    '<button type="button" class="tp-apply-btn secondary tp-cancel">' + cancelLabel + '</button>' +
                    '<button type="button" class="tp-apply-btn primary tp-apply">' + applyLabel + '</button>' +
                '</div>' +
            '</div>' +
        '</div>';

    wrapper.appendChild(btn);
    wrapper.appendChild(dropdown);
    container.appendChild(wrapper);

    // Tab switching
    var tabs = dropdown.querySelectorAll('.tp-tab');
    var bodies = dropdown.querySelectorAll('.tp-body');
    tabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            var target = tab.getAttribute('data-tab');
            tabs.forEach(function(t) { t.classList.remove('active'); });
            bodies.forEach(function(b) { b.classList.remove('active'); });
            tab.classList.add('active');
            dropdown.querySelector('.tp-body[data-tab="' + target + '"]').classList.add('active');
        });
    });

    // Preset clicks
    dropdown.querySelectorAll('.tp-preset').forEach(function(presetBtn) {
        presetBtn.addEventListener('click', function() {
            var key = presetBtn.getAttribute('data-key');
            var preset = presets.find(function(p) { return p.key === key; });
            if (!preset) return;

            // Update active state
            dropdown.querySelectorAll('.tp-preset').forEach(function(p) { p.classList.remove('active'); });
            presetBtn.classList.add('active');

            // Update button label
            currentSelection = { preset: key, label: preset.label };
            btn.querySelector('.tp-label').textContent = preset.label;

            // Calculate dates and fire callback
            var dates = calcPresetDates(key);
            closeDropdown();
            onChange({
                preset: key,
                label: preset.label,
                days: preset.days || null,
                date_from: dates.from,
                date_to: dates.to
            });
        });
    });

    // Custom range apply
    var applyBtn = dropdown.querySelector('.tp-apply');
    applyBtn.addEventListener('click', function() {
        var fromInput = dropdown.querySelector('#tpFrom');
        var toInput = dropdown.querySelector('#tpTo');
        var from = fromInput.value;
        var to = toInput.value;

        if (!from || !to) return;

        var fromDate = from.split('T')[0];
        var toDate = to.split('T')[0];
        var rangeLabel = fromDate + ' \u2192 ' + toDate;

        currentSelection = { preset: 'custom', label: rangeLabel };
        btn.querySelector('.tp-label').textContent = rangeLabel;

        // Remove active from presets
        dropdown.querySelectorAll('.tp-preset').forEach(function(p) { p.classList.remove('active'); });

        closeDropdown();
        onChange({
            preset: 'custom',
            label: rangeLabel,
            days: null,
            date_from: fromDate,
            date_to: toDate
        });
    });

    // Cancel button
    dropdown.querySelector('.tp-cancel').addEventListener('click', function() {
        closeDropdown();
    });

    // Toggle dropdown
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        var isOpen = dropdown.classList.contains('open');
        closeAllDropdowns();
        if (!isOpen) {
            dropdown.classList.add('open');

            // Set default custom range values
            var now = new Date();
            var fromInput = dropdown.querySelector('#tpFrom');
            var toInput = dropdown.querySelector('#tpTo');
            if (!fromInput.value) {
                var weekAgo = new Date(now.getTime() - 7 * 86400000);
                fromInput.value = formatDatetime(weekAgo);
            }
            if (!toInput.value) {
                toInput.value = formatDatetime(now);
            }
        }
    });

    function closeDropdown() {
        dropdown.classList.remove('open');
    }

    function closeAllDropdowns() {
        document.querySelectorAll('.time-picker-dropdown.open').forEach(function(d) {
            d.classList.remove('open');
        });
    }

    // Close on outside click
    document.addEventListener('click', function(e) {
        if (!wrapper.contains(e.target)) {
            closeDropdown();
        }
    });

    // Close on Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeDropdown();
    });

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // Return control API
    return {
        getSelection: function() { return currentSelection; },
        setPreset: function(key) {
            var presetBtn = dropdown.querySelector('.tp-preset[data-key="' + key + '"]');
            if (presetBtn) presetBtn.click();
        }
    };
}
