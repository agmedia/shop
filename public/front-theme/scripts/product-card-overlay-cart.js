document.addEventListener('DOMContentLoaded', function () {
    const cards = document.querySelectorAll('[data-product-card]');
    if (!cards.length) {
        return;
    }

    const closeCardOverlay = function (card) {
        const panel = card.querySelector('[data-card-overlay]');
        const toggle = card.querySelector('[data-card-overlay-toggle]');
        if (!panel || !toggle) {
            return;
        }

        panel.classList.remove('opacity-100', 'pointer-events-auto', 'md:opacity-100', 'md:pointer-events-auto');
        panel.classList.add('opacity-0', 'pointer-events-none', 'md:opacity-0', 'md:pointer-events-none');
        toggle.setAttribute('aria-expanded', 'false');
    };

    const openCardOverlay = function (card) {
        const panel = card.querySelector('[data-card-overlay]');
        const toggle = card.querySelector('[data-card-overlay-toggle]');
        if (!panel || !toggle) {
            return;
        }

        panel.classList.remove('opacity-0', 'pointer-events-none', 'md:opacity-0', 'md:pointer-events-none');
        panel.classList.add('opacity-100', 'pointer-events-auto', 'md:opacity-100', 'md:pointer-events-auto');
        toggle.setAttribute('aria-expanded', 'true');
    };

    cards.forEach(function (card) {
        const toggle = card.querySelector('[data-card-overlay-toggle]');
        const panel = card.querySelector('[data-card-overlay]');
        if (!toggle || !panel) {
            return;
        }

        toggle.addEventListener('click', function () {
            const isOpen = toggle.getAttribute('aria-expanded') === 'true';

            cards.forEach(function (otherCard) {
                if (otherCard !== card) {
                    closeCardOverlay(otherCard);
                }
            });

            if (isOpen) {
                closeCardOverlay(card);
                return;
            }

            openCardOverlay(card);
        });
    });

    document.addEventListener('product-card-overlay:close-all', function () {
        cards.forEach(function (card) {
            closeCardOverlay(card);
        });
    });
});
