(function () {
    function setup(select) {
        var targetId = select.getAttribute('data-target');
        var table = document.getElementById(targetId);
        if (!table || !table.tBodies[0]) return;

        var rows = Array.prototype.slice.call(table.tBodies[0].rows);
        var pagination = document.querySelector('[data-pagination-for="' + targetId + '"]');
        var prevBtn = pagination ? pagination.querySelector('.page-prev') : null;
        var nextBtn = pagination ? pagination.querySelector('.page-next') : null;
        var info = pagination ? pagination.querySelector('.page-info') : null;

        var storageKey = 'rowLimit:' + targetId;
        var stored = null;
        try {
            stored = localStorage.getItem(storageKey);
        } catch (e) {}
        if (stored) {
            var hasOption = Array.prototype.some.call(select.options, function (option) {
                return option.value === stored;
            });
            if (hasOption) select.value = stored;
        }

        var page = 1;

        function render() {
            var limit = parseInt(select.value, 10) || rows.length || 1;
            var totalPages = Math.max(1, Math.ceil(rows.length / limit));
            if (page > totalPages) page = totalPages;
            if (page < 1) page = 1;

            var start = (page - 1) * limit;
            var end = start + limit;
            rows.forEach(function (row, i) {
                row.hidden = i < start || i >= end;
            });

            if (pagination) {
                pagination.hidden = totalPages <= 1;
                if (info) info.textContent = 'Page ' + page + ' of ' + totalPages;
                if (prevBtn) prevBtn.disabled = page <= 1;
                if (nextBtn) nextBtn.disabled = page >= totalPages;
            }
        }

        select.addEventListener('change', function () {
            try {
                localStorage.setItem(storageKey, select.value);
            } catch (e) {}
            page = 1;
            render();
        });

        if (prevBtn) {
            prevBtn.addEventListener('click', function () {
                page -= 1;
                render();
            });
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', function () {
                page += 1;
                render();
            });
        }

        render();
    }

    Array.prototype.forEach.call(document.querySelectorAll('[data-row-limit]'), setup);
})();
