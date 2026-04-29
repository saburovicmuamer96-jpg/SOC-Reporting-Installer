/**
 * SOC Reporting System - Form Builder JS
 * Additional logic for the admin form builder.
 */

// Field type icons
const fieldTypeIcons = {
    'text': 'Aa',
    'textarea': '&#9998;',
    'number': '#',
    'dropdown': '&#9660;',
    'checkbox': '&#9744;'
};

// Preview form field
function previewField(type, label, placeholder) {
    let html = '';

    switch (type) {
        case 'text':
            html = `<input type="text" class="form-input" placeholder="${placeholder || label}" disabled style="opacity: 0.6;">`;
            break;
        case 'textarea':
            html = `<textarea class="form-textarea" placeholder="${placeholder || label}" disabled style="opacity: 0.6; min-height: 60px;"></textarea>`;
            break;
        case 'number':
            html = `<input type="number" class="form-input" placeholder="${placeholder || '0'}" disabled style="opacity: 0.6;">`;
            break;
        case 'dropdown':
            html = `<select class="form-select" disabled style="opacity: 0.6;"><option>-- Select --</option></select>`;
            break;
        case 'checkbox':
            html = `<div style="opacity: 0.6; display: flex; gap: 12px;"><label><input type="checkbox" disabled> Option 1</label><label><input type="checkbox" disabled> Option 2</label></div>`;
            break;
    }

    return html;
}
