/**
 * SOC Reporting System - Reports List JS
 * Handles dynamic filtering and interactions.
 */

// Date range presets
function setDateRange(preset) {
    const now = new Date();
    let from = new Date();

    switch (preset) {
        case 'today':
            break;
        case 'week':
            from.setDate(from.getDate() - 7);
            break;
        case 'month':
            from.setMonth(from.getMonth() - 1);
            break;
        case 'quarter':
            from.setMonth(from.getMonth() - 3);
            break;
        case 'year':
            from.setFullYear(from.getFullYear() - 1);
            break;
    }

    const dateFrom = document.querySelector('input[name="date_from"]');
    const dateTo = document.querySelector('input[name="date_to"]');

    if (dateFrom) dateFrom.value = formatDate(from);
    if (dateTo) dateTo.value = formatDate(now);
}

function formatDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}
