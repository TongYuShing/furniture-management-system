/**
 * password-toggle.js — Auto-enhances all password fields with show/hide toggles.
 * Drop this script into any page and all type="password" inputs get an eye icon.
 */
(function () {
    'use strict';

    // CSS for the password toggle — injected once
    if (!document.getElementById('pw-toggle-styles')) {
        var style = document.createElement('style');
        style.id = 'pw-toggle-styles';
        style.textContent = ''
            + '.pw-wrapper { position: relative; display: flex; align-items: center; }'
            + '.pw-wrapper .form-control { width: 100%; padding-right: 42px; }'
            + '.pw-wrapper input[type="password"],'
            + '.pw-wrapper input[type="text"] { padding-right: 42px !important; }'
            + '.pw-toggle {'
            + '  position: absolute; right: 4px; top: 50%; transform: translateY(-50%);'
            + '  background: none; border: none; cursor: pointer;'
            + '  font-size: 1.2rem; padding: 6px 10px; line-height: 1;'
            + '  color: var(--gray-400, #999); z-index: 2;'
            + '  border-radius: 6px; transition: color 0.15s, background 0.15s;'
            + '  user-select: none; -webkit-user-select: none;'
            + '}'
            + '.pw-toggle:hover { color: var(--gray-700, #333); background: var(--gray-100, #f0f0f0); }'
            + '.pw-toggle:focus { outline: 2px solid var(--primary, #1a3c2a); outline-offset: -2px; }'
            + '';
        document.head.appendChild(style);
    }

    function togglePassword(btn) {
        var input = btn.previousElementSibling;
        if (!input || (input.tagName !== 'INPUT')) {
            // The input might be before the button's parent
            input = btn.parentElement.querySelector('input');
        }
        if (!input) return;

        var isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        btn.textContent = isPassword ? '🙈' : '👁';
        btn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
        btn.setAttribute('title', isPassword ? 'Hide password' : 'Show password');
    }

    function enhancePasswordField(input) {
        // Skip if already enhanced
        if (input.parentElement.classList.contains('pw-wrapper')) return;

        // Wrap input in pw-wrapper
        var wrapper = document.createElement('div');
        wrapper.className = 'pw-wrapper';
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);

        // Create toggle button
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'pw-toggle';
        btn.textContent = '👁';
        btn.setAttribute('aria-label', 'Show password');
        btn.setAttribute('title', 'Show password');
        btn.setAttribute('tabindex', '-1');
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            togglePassword(btn);
        });
        wrapper.appendChild(btn);
    }

    // Enhance all existing password fields
    function enhanceAll() {
        var inputs = document.querySelectorAll('input[type="password"]');
        for (var i = 0; i < inputs.length; i++) {
            enhancePasswordField(inputs[i]);
        }
    }

    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', enhanceAll);
    } else {
        enhanceAll();
    }
})();
