document.addEventListener('DOMContentLoaded', function () {
    const root = document.querySelector('[data-product-detail], [data-mobile-product-gallery], [data-product-detail-form]');
    if (!root) {
        return;
    }

    const galleryThumbs = Array.from(document.querySelectorAll('[data-gallery-thumb]'));
    const galleryMain = Array.from(document.querySelectorAll('[data-gallery-main]'));
    const gallerySecondary = Array.from(document.querySelectorAll('[data-gallery-secondary]'));
    const galleryOpenButtons = Array.from(document.querySelectorAll('[data-gallery-open]'));
    const productSplide = document.querySelector('[data-product-splide]');

    if (productSplide && typeof window.Splide === 'function' && window.matchMedia('(max-width: 768px)').matches) {
        const slider = new window.Splide(productSplide, {
            type: 'slide',
            perPage: 1,
            perMove: 1,
            arrows: false,
            pagination: true,
            drag: true,
            speed: 450,
            rewind: true,
        });
        slider.mount();
    }

    const normalizeGalleryUrl = function (value) {
        const raw = String(value || '').trim();
        if (!raw) {
            return '';
        }

        if (/^https?:\/\//i.test(raw)) {
            if (window.location.protocol === 'https:' && raw.startsWith('http://')) {
                return 'https://' + raw.slice('http://'.length);
            }
            return raw;
        }

        if (raw.startsWith('//')) {
            return window.location.protocol + raw;
        }

        if (raw.startsWith('/')) {
            return window.location.origin + raw;
        }

        return raw;
    };

    const galleryItemsFromThumbs = galleryThumbs.map(function (button) {
        return {
            full: normalizeGalleryUrl(button.dataset.full || ''),
            alt: String(button.dataset.alt || '').trim(),
        };
    });

    const galleryItemsFromButtons = galleryOpenButtons.map(function (button) {
        const image = button.querySelector('img');
        return {
            full: normalizeGalleryUrl((image && image.getAttribute('src')) || ''),
            alt: String((image && image.getAttribute('alt')) || button.getAttribute('aria-label') || '').trim(),
        };
    });

    const galleryItemsRaw = (galleryItemsFromThumbs.some(function (item) { return item.full !== ''; })
        ? galleryItemsFromThumbs
        : galleryItemsFromButtons
    ).filter(function (item) {
        return item.full !== '';
    });
    const seenGallery = new Set();
    const galleryItems = galleryItemsRaw.filter(function (item) {
        if (seenGallery.has(item.full)) {
            return false;
        }
        seenGallery.add(item.full);
        return true;
    });

    const isDesktopPreview = function () {
        return window.matchMedia('(min-width: 769px)').matches;
    };

    const setGalleryPreview = function (index) {
        if (!galleryItems.length || !galleryMain.length || !isDesktopPreview()) {
            return;
        }

        const normalized = ((index % galleryItems.length) + galleryItems.length) % galleryItems.length;
        const next = (normalized + 1) % galleryItems.length;
        const primary = galleryItems[normalized];
        const secondary = galleryItems[next] || primary;

        galleryMain.forEach(function (imageEl) {
            imageEl.src = primary.full;
            imageEl.alt = primary.alt;
        });

        gallerySecondary.forEach(function (imageEl) {
            imageEl.src = secondary.full;
            imageEl.alt = secondary.alt;
        });
    };

    galleryThumbs.forEach(function (thumb) {
        thumb.addEventListener('click', function () {
            if (!isDesktopPreview()) {
                return;
            }
            const index = Number.parseInt(String(thumb.dataset.index || '0'), 10);
            setGalleryPreview(Number.isNaN(index) ? 0 : index);
        });
    });

    let galleryBox = null;
    if (galleryItems.length && typeof window.lightGallery === 'function') {
        const previousGalleryRoot = document.querySelector('[data-product-lightgallery="1"]');
        if (previousGalleryRoot) {
            previousGalleryRoot.remove();
        }

        const galleryRoot = document.createElement('div');
        galleryRoot.setAttribute('data-product-lightgallery', '1');
        document.body.appendChild(galleryRoot);

        galleryBox = window.lightGallery(galleryRoot, {
            dynamic: true,
            dynamicEl: galleryItems.map(function (item) {
                return {
                    src: encodeURI(item.full),
                    thumb: encodeURI(item.full),
                    subHtml: item.alt,
                };
            }),
            download: false,
            counter: true,
            closable: true,
            swipeThreshold: 30,
            mobileSettings: {
                controls: true,
                showCloseIcon: true,
                download: false,
            },
        });
    }

    const openFallbackImage = function (index) {
        if (!galleryItems.length) {
            return;
        }

        const normalized = ((index % galleryItems.length) + galleryItems.length) % galleryItems.length;
        const target = galleryItems[normalized];
        if (!target || !target.full) {
            return;
        }

        // iOS-safe fallback when lightbox plugin is unavailable or throws.
        window.location.href = target.full;
    };

    galleryOpenButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            if (!galleryItems.length) {
                return;
            }

            const index = Number.parseInt(String(button.dataset.galleryOpen || '0'), 10);
            const normalized = Number.isNaN(index) ? 0 : index;

            if (!galleryBox) {
                openFallbackImage(normalized);
                return;
            }

            try {
                galleryBox.openGallery(normalized);
            } catch (error) {
                openFallbackImage(normalized);
            }
        });
    });

    const mobileTrack = document.querySelector('[data-mobile-gallery-track]');
    if (mobileTrack) {
        const dots = Array.from(document.querySelectorAll('[data-mobile-gallery-dot]'));
        const updateDots = function () {
            if (!dots.length) {
                return;
            }

            const width = mobileTrack.clientWidth || 1;
            const index = Math.round(mobileTrack.scrollLeft / width);
            dots.forEach(function (dot, dotIndex) {
                if (dotIndex === index) {
                    dot.classList.add('bg-black');
                    dot.classList.remove('bg-slate-300', 'bg-white/70');
                    return;
                }

                dot.classList.remove('bg-black');
                if (!dot.classList.contains('bg-slate-300')) {
                    dot.classList.add('bg-slate-300');
                }
            });
        };

        dots.forEach(function (dot) {
            dot.addEventListener('click', function () {
                const index = Number.parseInt(String(dot.dataset.mobileGalleryDot || '0'), 10);
                const target = (Number.isNaN(index) ? 0 : index) * (mobileTrack.clientWidth || 0);
                mobileTrack.scrollTo({ left: target, behavior: 'smooth' });
            });
        });

        mobileTrack.addEventListener('scroll', updateDots, { passive: true });
        updateDots();
    }

    const qtyControls = document.querySelectorAll('[data-qty-control]');
    qtyControls.forEach(function (control) {
        const input = control.querySelector('[data-qty-input]');
        const dec = control.querySelector('[data-qty-dec]');
        const inc = control.querySelector('[data-qty-inc]');

        if (!input || !dec || !inc) {
            return;
        }

        const setValue = function (value) {
            const numeric = Number.parseInt(String(value), 10);
            const clamped = Number.isNaN(numeric) ? 1 : Math.min(99, Math.max(1, numeric));
            input.value = String(clamped);
        };

        dec.addEventListener('click', function () {
            setValue((Number.parseInt(String(input.value), 10) || 1) - 1);
        });

        inc.addEventListener('click', function () {
            setValue((Number.parseInt(String(input.value), 10) || 1) + 1);
        });
    });

    const forms = document.querySelectorAll('[data-product-detail-form]');
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
        const radioChecked = form.querySelector('input[name="product_option_value_id"]:checked');
        if (radioChecked) {
            const label = form.querySelector('label[for="' + radioChecked.id + '"] span');
            return label ? String(label.textContent || '').trim() : '';
        }

        const select = form.querySelector('select[name="product_option_value_id"]');
        if (!select || !select.value) {
            return '';
        }

        const option = select.options[select.selectedIndex];
        return option ? String(option.textContent || '').trim() : '';
    };

    const hasOptions = function (form) {
        return !!(
            form.querySelector('input[name="product_option_value_id"]')
            || form.querySelector('select[name="product_option_value_id"]')
        );
    };

    const optionSelected = function (form) {
        const radioChecked = form.querySelector('input[name="product_option_value_id"]:checked');
        if (radioChecked) {
            return true;
        }

        const select = form.querySelector('select[name="product_option_value_id"]');
        return !!(select && String(select.value || '').trim() !== '');
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

            const optionError = form.querySelector('[data-option-error]');
            if (hasOptions(form) && !optionSelected(form)) {
                if (optionError) {
                    optionError.classList.remove('hidden', 'd-none');
                }
                return;
            }

            if (optionError) {
                optionError.classList.add('hidden');
            }

            const formData = new FormData(form);

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                    },
                    body: formData,
                });

                const payload = await response.json();
                if (!response.ok || !payload.ok) {
                    if (optionError && payload && payload.errors && payload.errors.product_option_value_id) {
                        optionError.classList.remove('hidden', 'd-none');
                    }
                    return;
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
