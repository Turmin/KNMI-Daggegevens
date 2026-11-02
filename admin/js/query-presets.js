(function () {
    'use strict';

    var input = document.getElementById('query_sql');
    if (!input) {
        return;
    }

    document.querySelectorAll('.query-preset').forEach(function (button) {
        button.addEventListener('click', function () {
            input.value = button.getAttribute('data-sql') || '';
            input.focus();
        });
    });
})();
