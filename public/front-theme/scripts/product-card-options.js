document.addEventListener('DOMContentLoaded', function () {
    const forms = document.querySelectorAll('[data-product-card-form]');

    forms.forEach(function (form) {
        const optionInputs = form.querySelectorAll('input[name="product_option_value_id"]');
        const errorEl = form.querySelector('[data-option-error]');

        if (!optionInputs.length || !errorEl) {
            return;
        }

        const hideError = function () {
            errorEl.classList.add('hidden');
        };

        optionInputs.forEach(function (input) {
            input.addEventListener('change', hideError);
        });

        form.addEventListener('submit', function (event) {
            const selected = form.querySelector('input[name="product_option_value_id"]:checked');

            if (selected) {
                hideError();
                return;
            }

            event.preventDefault();
            errorEl.classList.remove('hidden');
        });
    });
});
