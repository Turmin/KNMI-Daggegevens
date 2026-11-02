(function () {
    'use strict';

    var visibleMs = 4000;

    document.querySelectorAll('.admin-toast').forEach(function (toast) {
        var hide = function () {
            toast.classList.add('admin-toast-hiding');
            setTimeout(function () {
                toast.remove();
            }, 300);
        };

        var timer = setTimeout(hide, visibleMs);
        var closeButton = toast.querySelector('.btn-close');
        if (closeButton) {
            closeButton.addEventListener('click', function () {
                clearTimeout(timer);
                hide();
            });
        }
    });
})();
