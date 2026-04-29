/**
 * SOC Reporting System - Core Application JS
 */

// Close modals on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.active').forEach(function(modal) {
            modal.classList.remove('active');
        });
    }
});

// Close modal on backdrop click
document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) {
            overlay.classList.remove('active');
        }
    });
});

// Auto-hide flash messages after 5 seconds
document.querySelectorAll('.alert').forEach(function(alert) {
    setTimeout(function() {
        alert.style.transition = 'opacity 0.3s ease';
        alert.style.opacity = '0';
        setTimeout(function() { alert.remove(); }, 300);
    }, 5000);
});

// Close sidebar on mobile when clicking outside
document.addEventListener('click', function(e) {
    const sidebar = document.getElementById('sidebar');
    const menuBtn = document.querySelector('.mobile-menu-btn');
    if (sidebar && sidebar.classList.contains('open') &&
        !sidebar.contains(e.target) && !menuBtn.contains(e.target)) {
        sidebar.classList.remove('open');
    }
});

// Confirm before leaving page with unsaved form data
let formChanged = false;
document.querySelectorAll('form').forEach(function(form) {
    form.addEventListener('change', function() { formChanged = true; });
    form.addEventListener('submit', function() { formChanged = false; });
});

window.addEventListener('beforeunload', function(e) {
    if (formChanged) {
        e.preventDefault();
        e.returnValue = '';
    }
});

// Double-confirm delete buttons
document.addEventListener('click', function(e) {
    var btn = e.target.closest('.btn-delete-confirm');
    if (!btn) return;

    e.preventDefault();
    e.stopPropagation();

    if (btn.dataset.confirmed === 'true') {
        // Second click - submit the form
        var form = btn.closest('form');
        if (form) {
            formChanged = false;
            form.submit();
        } else {
            console.error('Delete button not inside a form');
        }
    } else {
        // First click - change to confirm state
        btn.dataset.confirmed = 'true';
        var confirmText = btn.dataset.confirmText || btn.getAttribute('data-confirm-text');
        var originalText = btn.dataset.originalText || btn.getAttribute('data-original-text') || btn.textContent;

        // Store original text if not already stored
        if (!btn.dataset.originalText) {
            btn.dataset.originalText = originalText;
        }

        btn.textContent = confirmText;
        btn.classList.add('btn-delete-active');

        // Reset after 3 seconds if not clicked again
        setTimeout(function() {
            if (btn.dataset.confirmed === 'true') {
                btn.dataset.confirmed = '';
                btn.textContent = btn.dataset.originalText;
                btn.classList.remove('btn-delete-active');
            }
        }, 3000);
    }
});
