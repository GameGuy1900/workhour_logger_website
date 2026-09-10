(function () {
    function applyLimit(select) {
        var table = document.getElementById(select.getAttribute('data-target'));
        if (!table || !table.tBodies[0]) return;
        var limit = parseInt(select.value, 10) || 0;
        Array.prototype.forEach.call(table.tBodies[0].rows, function (row, i) {
            row.hidden = limit > 0 && i >= limit;
        });
    }

    function init(select) {
        var key = 'rowLimit:' + select.getAttribute('data-target');
        var stored = null;
        try {
            stored = localStorage.getItem(key);
        } catch (e) {}

        if (stored) {
            var hasOption = Array.prototype.some.call(select.options, function (option) {
                return option.value === stored;
            });
            if (hasOption) select.value = stored;
        }

        applyLimit(select);

        select.addEventListener('change', function () {
            try {
                localStorage.setItem(key, select.value);
            } catch (e) {}
            applyLimit(select);
        });
    }

    Array.prototype.forEach.call(document.querySelectorAll('[data-row-limit]'), init);
})();
