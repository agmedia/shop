document.addEventListener('DOMContentLoaded', function () {
    const panel = document.querySelector('[data-header-search-panel]');
    const toggles = document.querySelectorAll('[data-header-search-toggle]');
    const input = document.querySelector('[data-header-search-input]');

    if (!panel || !toggles.length) {
        return;
    }

    let isOpen = false;

    const openPanel = function () {
        panel.classList.remove('max-h-0', 'opacity-0', 'pointer-events-none');
        panel.classList.add('max-h-40', 'opacity-100');
        isOpen = true;
        if (input) {
            window.setTimeout(function () {
                input.focus();
            }, 120);
        }
    };

    const closePanel = function () {
        panel.classList.add('max-h-0', 'opacity-0', 'pointer-events-none');
        panel.classList.remove('max-h-40', 'opacity-100');
        isOpen = false;
    };

    toggles.forEach(function (toggle) {
        toggle.addEventListener('click', function () {
            if (isOpen) {
                closePanel();
                return;
            }
            openPanel();
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && isOpen) {
            closePanel();
        }
    });
});
