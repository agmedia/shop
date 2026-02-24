document.addEventListener('DOMContentLoaded', function () {
    const controls = document.querySelectorAll('[data-qty-control]');

    controls.forEach(function (control) {
        const input = control.querySelector('[data-qty-input]');
        const valueEl = control.querySelector('[data-qty-value]');
        const dec = control.querySelector('[data-qty-dec]');
        const inc = control.querySelector('[data-qty-inc]');

        if (!input || !valueEl || !dec || !inc) {
            return;
        }

        const setValue = function (value) {
            const numeric = Number.parseInt(String(value), 10);
            const clamped = Number.isNaN(numeric) ? 1 : Math.min(99, Math.max(1, numeric));
            input.value = String(clamped);
            if ('value' in valueEl) {
                valueEl.value = String(clamped);
            } else {
                valueEl.textContent = String(clamped);
            }
        };

        dec.addEventListener('click', function () {
            setValue((Number.parseInt(input.value, 10) || 1) - 1);
        });

        inc.addEventListener('click', function () {
            setValue((Number.parseInt(input.value, 10) || 1) + 1);
        });
    });
});
