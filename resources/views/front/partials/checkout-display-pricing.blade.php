@once
    @push('scripts')
        <script>
            window.addEventListener('shop:checkout-pricing', function (event) {
                const payload = event.detail || {};
                const pricing = payload.display_pricing;
                if (!pricing) return;

                document.querySelectorAll('[data-checkout-options-url]').forEach(function (form) {
                    ['subtotal', 'discount_total', 'tax_total', 'shipping_total', 'payment_fee_total', 'grand_total'].forEach(function (key) {
                        form.querySelectorAll('[data-checkout-price="' + key + '"]').forEach(function (node) {
                            if (pricing[key + '_formatted'] !== undefined) node.textContent = String(pricing[key + '_formatted']);
                        });
                    });
                    form.querySelectorAll('[data-checkout-tax-label]').forEach(function (node) {
                        node.textContent = String(pricing.tax_label);
                    });
                    form.querySelectorAll('[data-summary-discount-row]').forEach(function (node) {
                        node.hidden = Number(pricing.discount_total || 0) <= 0;
                        node.style.display = node.hidden ? 'none' : '';
                    });
                    form.querySelectorAll('[data-checkout-line-total]').forEach(function (node) {
                        const line = (payload.lines || []).find(function (line) {
                            return String(line.key) === node.dataset.checkoutLineTotal;
                        });
                        if (line) node.textContent = String(line.total_formatted);
                    });
                });
            });
        </script>
    @endpush
@endonce
