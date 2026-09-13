(function () {
    'use strict';

    function togglePassword(wrapId) {
        var wrap = document.getElementById(wrapId);
        if (!wrap) {
            return;
        }
        var input = wrap.querySelector('input');
        var showIcon = wrap.querySelector('.qx-eye-show');
        var hideIcon = wrap.querySelector('.qx-eye-hide');
        if (!input) {
            return;
        }
        var isHidden = input.type === 'password';
        input.type = isHidden ? 'text' : 'password';
        if (showIcon && hideIcon) {
            showIcon.hidden = isHidden;
            hideIcon.hidden = !isHidden;
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var toggleBtn = document.querySelector('.app-auth-password-toggle');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function () {
                togglePassword('show_hide_password');
            });
        }
    });

    window.refreshCaptcha = function () {
        var target = document.getElementById('captcha_image');
        if (!target) {
            return;
        }
        fetch(window.qxLoginBaseUrl + 'site/refreshCaptcha', { method: 'POST' })
            .then(function (r) { return r.text(); })
            .then(function (html) { target.innerHTML = html; });
    };
})();
