/**
 * usability.js - System Usability Features
 * Part IV: Session timeout, unsaved changes, keyboard shortcuts, toasts
 */

document.addEventListener('DOMContentLoaded', function() {

    // ═══════════════════════════════════════════
    // 1. SESSION TIMEOUT WARNING (30 min)
    // ═══════════════════════════════════════════
    var SESSION_TIMEOUT_MINUTES = 30;
    var WARNING_BEFORE_MINUTES = 2;
    var timeoutWarningShown = false;

    function resetSessionTimer() {
        clearTimeout(window._sessionTimeout);
        clearTimeout(window._sessionWarning);
        timeoutWarningShown = false;

        // Remove warning bar if visible
        var bar = document.getElementById('timeoutWarning');
        if (bar) bar.remove();

        // Set warning timeout
        window._sessionWarning = setTimeout(showTimeoutWarning,
            (SESSION_TIMEOUT_MINUTES - WARNING_BEFORE_MINUTES) * 60 * 1000);

        // Set actual timeout
        window._sessionTimeout = setTimeout(handleSessionTimeout,
            SESSION_TIMEOUT_MINUTES * 60 * 1000);
    }

    function showTimeoutWarning() {
        if (timeoutWarningShown) return;
        timeoutWarningShown = true;

        var bar = document.createElement('div');
        bar.id = 'timeoutWarning';
        bar.style.cssText = 'position:fixed;top:0;left:0;right:0;z-index:9999;background:var(--warning);color:#000;padding:12px 20px;text-align:center;font-weight:600;display:flex;justify-content:center;align-items:center;gap:12px;';
        bar.innerHTML = '<span>⏰ Your session will expire in <strong id="timeoutCountdown">' + WARNING_BEFORE_MINUTES + ':00</strong> due to inactivity.</span>' +
            '<button onclick="location.reload()" style="padding:4px 12px;border-radius:4px;border:none;background:#000;color:#fff;cursor:pointer;font-weight:600;">Stay Signed In</button>';
        document.body.prepend(bar);

        // Countdown
        var seconds = WARNING_BEFORE_MINUTES * 60;
        var countdown = setInterval(function() {
            seconds--;
            var el = document.getElementById('timeoutCountdown');
            if (el) {
                var m = Math.floor(seconds / 60);
                var s = seconds % 60;
                el.textContent = m + ':' + (s < 10 ? '0' : '') + s;
            }
            if (seconds <= 0) clearInterval(countdown);
        }, 1000);
    }

    function handleSessionTimeout() {
        var bar = document.getElementById('timeoutWarning');
        if (bar) {
            bar.style.background = 'var(--danger)';
            bar.style.color = '#fff';
            bar.innerHTML = '<span>⚠️ Your session has expired. <a href="../index.php" style="color:#fff;text-decoration:underline;">Sign in again</a></span>';
        }
    }

    // Reset timer on user activity
    ['click', 'keydown', 'scroll', 'mousemove'].forEach(function(evt) {
        document.addEventListener(evt, resetSessionTimer);
    });
    resetSessionTimer();

    // ═══════════════════════════════════════════
    // 2. UNSAVED CHANGES WARNING
    // ═══════════════════════════════════════════
    var forms = document.querySelectorAll('form[data-validate], form[method="POST"]');
    forms.forEach(function(form) {
        var originalData = new FormData(form);
        var isDirty = false;

        form.addEventListener('input', function() {
            var current = new FormData(form);
            isDirty = false;
            for (var pair of current.entries()) {
                if (pair[1] !== (originalData.get(pair[0]) || '')) {
                    isDirty = true;
                    break;
                }
            }
        });

        form.addEventListener('submit', function() {
            isDirty = false;
        });

        window.addEventListener('beforeunload', function(e) {
            if (isDirty) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
                return e.returnValue;
            }
        });
    });

    // ═══════════════════════════════════════════
    // 3. KEYBOARD SHORTCUTS
    // ═══════════════════════════════════════════
    document.addEventListener('keydown', function(e) {
        // Only if not typing in an input
        var tag = document.activeElement.tagName;
        if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;

        switch(e.key.toLowerCase()) {
            case 'h':
                if (e.ctrlKey || e.metaKey) {
                    e.preventDefault();
                    window.location.href = 'dashboard.php';
                }
                break;
            case 'p':
                if (e.ctrlKey || e.metaKey) {
                    e.preventDefault();
                    window.location.href = 'insert_furniture.php';
                }
                break;
            case 'o':
                if (e.ctrlKey || e.metaKey) {
                    e.preventDefault();
                    window.location.href = 'manage_orders.php';
                }
                break;
            case 'r':
                if (e.ctrlKey || e.metaKey) {
                    e.preventDefault();
                    window.location.href = 'generate_report.php';
                }
                break;
            case 'g':
                if (e.altKey && !e.ctrlKey) {
                    e.preventDefault();
                    window.location.href = 'dashboard.php';
                }
                break;
            case '?':
                showShortcutsHelp();
                break;
        }
    });

    function showShortcutsHelp() {
        var existing = document.getElementById('shortcutsModal');
        if (existing) { existing.remove(); return; }

        var modal = document.createElement('div');
        modal.id = 'shortcutsModal';
        modal.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.6);display:flex;align-items:center;justify-content:center;';
        modal.innerHTML = '<div style="background:#fff;border-radius:12px;padding:30px;max-width:420px;width:90%;">' +
            '<h3>⌨️ Keyboard Shortcuts</h3>' +
            '<table style="width:100%;margin:16px 0;"><tbody>' +
            '<tr><td><kbd>Ctrl+H</kbd></td><td>Dashboard</td></tr>' +
            '<tr><td><kbd>Ctrl+P</kbd></td><td>Add Product</td></tr>' +
            '<tr><td><kbd>Ctrl+O</kbd></td><td>Manage Orders</td></tr>' +
            '<tr><td><kbd>Ctrl+R</kbd></td><td>Reports</td></tr>' +
            '<tr><td><kbd>?</kbd></td><td>Show this help</td></tr>' +
            '<tr><td><kbd>Esc</kbd></td><td>Close modals</td></tr>' +
            '</tbody></table>' +
            '<button onclick="document.getElementById(\'shortcutsModal\').remove()" style="padding:8px 20px;border-radius:6px;border:none;background:var(--primary);color:#fff;cursor:pointer;width:100%;">Close</button>' +
            '</div>';
        document.body.appendChild(modal);
        modal.addEventListener('click', function(e) { if (e.target === modal) modal.remove(); });
    }

    // ═══════════════════════════════════════════
    // 4. ENHANCED TOAST NOTIFICATIONS
    // ═══════════════════════════════════════════
    window.showToast = function(message, type) {
        type = type || 'info';
        var container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            container.style.cssText = 'position:fixed;bottom:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:8px;';
            document.body.appendChild(container);
        }

        var icons = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };
        var colors = { success: 'var(--success)', error: 'var(--danger)', warning: 'var(--warning)', info: 'var(--info)' };

        var toast = document.createElement('div');
        toast.style.cssText = 'padding:12px 20px;border-radius:8px;color:#fff;font-weight:500;font-size:0.9rem;box-shadow:0 4px 12px rgba(0,0,0,0.2);animation:slideInRight 0.3s ease;cursor:pointer;max-width:380px;display:flex;align-items:center;gap:8px;';
        toast.style.background = colors[type] || colors.info;
        toast.innerHTML = '<span>' + (icons[type] || '') + '</span> ' + message;
        toast.addEventListener('click', function() { toast.remove(); });
        container.appendChild(toast);

        setTimeout(function() {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.3s';
            setTimeout(function() { if (toast.parentNode) toast.remove(); }, 300);
        }, 4000);
    };
});

// Add toast animation
var style = document.createElement('style');
style.textContent = '@keyframes slideInRight { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }';
document.head.appendChild(style);