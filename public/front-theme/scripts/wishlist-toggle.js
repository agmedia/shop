document.addEventListener('DOMContentLoaded', function () {
    const forms = document.querySelectorAll('[data-wishlist-form]');
    if (!forms.length) {
        return;
    }

    const countNodes = document.querySelectorAll('[data-wishlist-count]');

    const setCount = function (count) {
        const safeCount = Number.isFinite(count) ? Math.max(0, count) : 0;
        countNodes.forEach(function (node) {
            node.textContent = String(safeCount);
        });
    };

    const showMessage = function (message, isError) {
        if (!message) {
            return;
        }

        let toast = document.querySelector('[data-wishlist-toast]');
        if (!toast) {
            toast = document.createElement('div');
            toast.setAttribute('data-wishlist-toast', '1');
            toast.className = 'fixed bottom-4 right-4 z-[90] border px-4 py-2 text-sm font-semibold shadow';
            document.body.appendChild(toast);
        }

        toast.textContent = message;
        if (isError) {
            toast.classList.remove('border-emerald-200', 'bg-emerald-50', 'text-emerald-800');
            toast.classList.add('border-rose-200', 'bg-rose-50', 'text-rose-800');
        } else {
            toast.classList.remove('border-rose-200', 'bg-rose-50', 'text-rose-800');
            toast.classList.add('border-emerald-200', 'bg-emerald-50', 'text-emerald-800');
        }

        window.clearTimeout(window.__wishlistToastTimer);
        window.__wishlistToastTimer = window.setTimeout(function () {
            if (toast && toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 1800);
    };

    const toggleVisualState = function (form, isActive) {
        form.dataset.wishlisted = isActive ? '1' : '0';

        const button = form.querySelector('[data-wishlist-button]');
        if (!button) {
            return;
        }

        button.classList.remove(
            'border-slate-900',
            'bg-slate-900',
            'text-white',
            'hover:bg-slate-700',
            'border-slate-200',
            'bg-white/95',
            'text-slate-700',
            'hover:text-slate-900'
        );

        if (isActive) {
            button.classList.add('border-slate-900', 'bg-slate-900', 'text-white', 'hover:bg-slate-700');
            button.setAttribute('aria-label', form.dataset.labelRemove || 'Remove');
        } else {
            button.classList.add('border-slate-200', 'bg-white/95', 'text-slate-700', 'hover:text-slate-900');
            button.setAttribute('aria-label', form.dataset.labelAdd || 'Add');
        }
    };

    const readCurrentCount = function () {
        const node = countNodes[0];
        if (!node) {
            return 0;
        }

        const parsed = Number.parseInt(String(node.textContent || '').trim(), 10);
        return Number.isNaN(parsed) ? 0 : parsed;
    };

    forms.forEach(function (form) {
        form.addEventListener('submit', async function (event) {
            event.preventDefault();

            const wasActive = form.dataset.wishlisted === '1';
            const optimisticActive = !wasActive;
            const currentCount = readCurrentCount();
            const optimisticCount = optimisticActive ? currentCount + 1 : currentCount - 1;

            toggleVisualState(form, optimisticActive);
            setCount(optimisticCount);

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: new FormData(form),
                });

                if (!response.ok) {
                    throw new Error('Wishlist request failed');
                }

                const data = await response.json();
                toggleVisualState(form, !!data.active);
                if (typeof data.count === 'number') {
                    setCount(data.count);
                }
                showMessage(String(data.message || ''), !data.ok);
            } catch (error) {
                toggleVisualState(form, wasActive);
                setCount(currentCount);
                showMessage(form.dataset.msgFailed || 'Wishlist update failed.', true);
            }
        });
    });
});
