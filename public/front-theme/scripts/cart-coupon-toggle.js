(() => {
    const init = () => {
        const toggle = document.querySelector('[data-coupon-toggle]');
        const panel = document.querySelector('[data-coupon-panel]');
        const wrap = document.querySelector('[data-coupon-wrap]');
        if (!toggle || !panel) {
            return;
        }

        const openLabel = String(toggle.dataset.labelOpen || '');
        const closeLabel = String(toggle.dataset.labelClose || '');

        const setOpen = (open) => {
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.textContent = open ? closeLabel : openLabel;
            panel.dataset.open = open ? '1' : '0';
            if (wrap) {
                wrap.classList.toggle('mb-4', open);
                wrap.classList.toggle('mb-0', !open);
            }

            if (open) {
                panel.style.maxHeight = `${panel.scrollHeight}px`;
                panel.style.opacity = '1';
            } else {
                panel.style.maxHeight = '0px';
                panel.style.opacity = '0';
            }
        };

        setOpen(panel.dataset.open === '1');

        toggle.addEventListener('click', () => {
            setOpen(panel.dataset.open !== '1');
        });

        window.addEventListener('resize', () => {
            if (panel.dataset.open === '1') {
                panel.style.maxHeight = `${panel.scrollHeight}px`;
            }
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
