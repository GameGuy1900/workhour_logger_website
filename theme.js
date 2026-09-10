(function () {
    var root = document.documentElement;
    var btn = document.getElementById('theme-toggle');
    if (!btn) return;

    function systemPrefersDark() {
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    }

    function currentTheme() {
        try {
            var stored = localStorage.getItem('theme');
            if (stored === 'dark' || stored === 'light') return stored;
        } catch (e) {}
        return systemPrefersDark() ? 'dark' : 'light';
    }

    function updateLabel() {
        btn.textContent = currentTheme() === 'dark' ? '☀️ Light mode' : '\u{1F319} Dark mode';
    }

    updateLabel();

    btn.addEventListener('click', function () {
        var next = currentTheme() === 'dark' ? 'light' : 'dark';
        try {
            localStorage.setItem('theme', next);
        } catch (e) {}
        root.setAttribute('data-theme', next);
        updateLabel();
    });
})();
