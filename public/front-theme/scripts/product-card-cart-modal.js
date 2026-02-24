document.addEventListener('DOMContentLoaded', function () {
    const forms = document.querySelectorAll('[data-product-card-form]');
    if (!forms.length) {
        return;
    }

    const cartCountNodes = document.querySelectorAll('[data-cart-count]');
    let modal = null;

    const setCartCount = function (count) {
        const safeCount = Number.isFinite(count) ? Math.max(0, count) : 0;
        cartCountNodes.forEach(function (node) {
            node.textContent = String(safeCount);
        });
    };

    const ensureModal = function () {
        if (modal) {
            return modal;
        }

        const wrapper = document.createElement('div');
        wrapper.className = 'fixed inset-0 hidden items-center justify-center p-4';
        wrapper.style.zIndex = '9999';
        wrapper.innerHTML = [
            '<div class="fixed inset-0" style="z-index: 1; background: rgba(0, 0, 0, 0.6);"></div>',
            '<div class="relative w-full border border-slate-300 bg-white p-4 shadow-2xl" style="z-index: 2; max-width: 460px;">',
            '  <div class="flex gap-4">',
            '    <img src="" alt="" class="h-28 w-20 border border-slate-200 object-cover" data-cart-modal-image>',
            '    <div class="min-w-0 flex-1">',
            '      <h3 class="text-lg font-semibold text-slate-900" data-cart-modal-name></h3>',
            '      <p class="mt-2 text-sm text-slate-600" data-cart-modal-option-wrap><span class="font-semibold text-slate-800" data-cart-modal-option-label></span>: <span data-cart-modal-option></span></p>',
            '      <p class="mt-1 text-sm text-slate-600"><span class="font-semibold text-slate-800" data-cart-modal-qty-label></span>: <span data-cart-modal-qty></span></p>',
            '    </div>',
            '  </div>',
            '  <div class="mt-5 grid grid-cols-2 gap-2">',
            '    <button type="button" class="h-11 border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-700 hover:bg-slate-100" data-cart-modal-continue></button>',
            '    <a href="#" class="inline-flex h-11 items-center justify-center border border-slate-900 bg-slate-900 px-3 text-sm font-semibold text-white hover:bg-slate-700" data-cart-modal-cart></a>',
            '  </div>',
            '</div>',
        ].join('');

        document.body.appendChild(wrapper);
        modal = wrapper;

        const continueBtn = modal.querySelector('[data-cart-modal-continue]');
        continueBtn.addEventListener('click', function () {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
        });

        return modal;
    };

    const selectedOptionLabel = function (form) {
        const checked = form.querySelector('input[name="product_option_value_id"]:checked');
        if (!checked) {
            return '';
        }

        const label = form.querySelector('label[for="' + checked.id + '"] span');
        return label ? String(label.textContent || '').trim() : '';
    };

    const currentQty = function (form) {
        const input = form.querySelector('input[name="quantity"]');
        const value = Number.parseInt(String(input ? input.value : '1'), 10);
        if (Number.isNaN(value)) {
            return 1;
        }

        return Math.min(99, Math.max(1, value));
    };

    const showModal = function (form) {
        document.dispatchEvent(new CustomEvent('product-card-overlay:close-all'));

        const popup = ensureModal();
        const image = popup.querySelector('[data-cart-modal-image]');
        const name = popup.querySelector('[data-cart-modal-name]');
        const optionWrap = popup.querySelector('[data-cart-modal-option-wrap]');
        const optionLabel = popup.querySelector('[data-cart-modal-option-label]');
        const optionValue = popup.querySelector('[data-cart-modal-option]');
        const qtyLabel = popup.querySelector('[data-cart-modal-qty-label]');
        const qtyValue = popup.querySelector('[data-cart-modal-qty]');
        const continueBtn = popup.querySelector('[data-cart-modal-continue]');
        const cartBtn = popup.querySelector('[data-cart-modal-cart]');

        const imageUrl = String(form.dataset.productImage || '').trim();
        const productName = String(form.dataset.productName || '').trim();
        const optionText = selectedOptionLabel(form);
        const qty = currentQty(form);

        image.src = imageUrl;
        image.alt = productName;
        name.textContent = productName;

        if (optionText !== '') {
            optionWrap.classList.remove('hidden');
            optionLabel.textContent = String(form.dataset.modalOption || 'Option');
            optionValue.textContent = optionText;
        } else {
            optionWrap.classList.add('hidden');
        }

        qtyLabel.textContent = String(form.dataset.modalQuantity || 'Quantity');
        qtyValue.textContent = String(qty);

        continueBtn.textContent = String(form.dataset.modalContinue || 'Continue shopping');
        cartBtn.textContent = String(form.dataset.modalGoCart || 'Go to cart');
        cartBtn.setAttribute('href', String(form.dataset.cartUrl || '/cart'));

        popup.classList.remove('hidden');
        popup.classList.add('flex');
        document.body.classList.add('overflow-hidden');
    };

    forms.forEach(function (form) {
        form.addEventListener('submit', async function (event) {
            event.preventDefault();

            const optionInputs = form.querySelectorAll('input[name="product_option_value_id"]');
            const hasOptions = optionInputs.length > 0;
            const hasSelectedOption = !!form.querySelector('input[name="product_option_value_id"]:checked');
            const optionError = form.querySelector('[data-option-error]');

            if (hasOptions && !hasSelectedOption) {
                if (optionError) {
                    optionError.classList.remove('hidden');
                }
                return;
            }

            const formData = new FormData(form);

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: formData,
                });

                const payload = await response.json();
                if (!response.ok || !payload.ok) {
                    if (optionError && payload && payload.errors && payload.errors.product_option_value_id) {
                        optionError.classList.remove('hidden');
                    }
                    return;
                }

                if (optionError) {
                    optionError.classList.add('hidden');
                }

                if (payload.summary && typeof payload.summary.item_qty === 'number') {
                    setCartCount(payload.summary.item_qty);
                }

                if (window.ShopAnalytics && typeof window.ShopAnalytics.trackAddToCartFromForm === 'function') {
                    window.ShopAnalytics.trackAddToCartFromForm(form, currentQty(form), selectedOptionLabel(form));
                }

                showModal(form);
            } catch (error) {
                // Keep UI stable on network errors.
            }
        });
    });
});
