(() => {
    const initDesktopNavigation = () => {
        const groups = Array.from(document.querySelectorAll('[data-desktop-nav-group]'));
        groups.forEach((group) => {
            const toggle = group.querySelector('[data-desktop-nav-toggle]');
            const panel = group.querySelector('[data-desktop-nav-panel]');
            if (!toggle || !panel) return;

            const setOpen = (open) => {
                toggle.setAttribute('aria-expanded', String(open));
                panel.setAttribute('aria-hidden', String(!open));
                panel.dataset.open = String(open);
                panel.inert = !open;
            };
            setOpen(false);
            if (group.dataset.navInitialized === '1') return;
            group.dataset.navInitialized = '1';

            const open = () => {
                groups.forEach((other) => {
                    if (other === group) return;
                    const otherToggle = other.querySelector('[data-desktop-nav-toggle]');
                    const otherPanel = other.querySelector('[data-desktop-nav-panel]');
                    otherToggle?.setAttribute('aria-expanded', 'false');
                    if (otherPanel) {
                        otherPanel.setAttribute('aria-hidden', 'true');
                        otherPanel.dataset.open = 'false';
                        otherPanel.inert = true;
                    }
                });
                setOpen(true);
            };

            // Native buttons provide Enter/Space activation without overriding category links.
            toggle.addEventListener('click', () => {
                if (toggle.getAttribute('aria-expanded') === 'true') setOpen(false);
                else open();
            });
            toggle.addEventListener('keydown', (event) => {
                if (event.key !== 'ArrowDown') return;
                event.preventDefault();
                open();
                panel.querySelector('a[href], button:not([disabled])')?.focus();
            });
            group.addEventListener('mouseenter', open);
            group.addEventListener('mouseleave', () => {
                if (!group.contains(document.activeElement)) setOpen(false);
            });
            group.addEventListener('focusout', (event) => {
                if (!group.contains(event.relatedTarget)) setOpen(false);
            });
            document.addEventListener('pointerdown', (event) => {
                if (!group.contains(event.target)) setOpen(false);
            });
            document.addEventListener('keydown', (event) => {
                if (event.key !== 'Escape' || toggle.getAttribute('aria-expanded') !== 'true') return;
                const restoreFocus = group.contains(document.activeElement);
                setOpen(false);
                if (restoreFocus) {
                    event.preventDefault();
                    toggle.focus();
                }
            });
        });
    };

    const init = () => {
        initDesktopNavigation();
        const root = document.querySelector('[data-mobile-menu-root]');
        if (!root) {
            document.body.classList.remove('overflow-hidden');
            document.body.classList.remove('desktop-mobile-menu-open');
            return;
        }

        const panel = root.querySelector('[data-mobile-menu-panel]');
        const overlay = root.querySelector('[data-mobile-menu-close]');
        const openButtons = document.querySelectorAll('[data-mobile-menu-open]');
        const closeButtons = root.querySelectorAll('[data-mobile-menu-close]');
        const accordionSections = root.querySelectorAll('[data-mobile-menu-accordion]');
        const menuLinks = Array.from(root.querySelectorAll('a[href]'));
        const accordionStateKey = 'desktop-mobile-menu-accordion-state-v2';
        let isRestoringAccordionState = false;
        let lastOpener = null;

        const forceClosedState = () => {
            root.classList.add('pointer-events-none');
            root.dataset.menuOpen = '0';
            root.inert = true;
            root.setAttribute('aria-hidden', 'true');
            openButtons.forEach((button) => button.setAttribute('aria-expanded', 'false'));
            overlay?.classList.remove('opacity-100');
            overlay?.classList.add('opacity-0');
            panel?.classList.add('-translate-x-full');
            panel?.classList.remove('translate-x-0');
            document.body.classList.remove('overflow-hidden');
            document.body.classList.remove('desktop-mobile-menu-open');
        };

        forceClosedState();

        if (root.dataset.menuInit === '1') {
            return;
        }
        root.dataset.menuInit = '1';

        const normalizePath = (href) => {
            try {
                const url = new URL(href, window.location.origin);
                if (url.origin !== window.location.origin) {
                    return null;
                }

                const pathname = url.pathname.replace(/\/+$/, '');
                return pathname === '' ? '/' : pathname;
            } catch {
                return null;
            }
        };

        const slugify = (value) => value
            .toLowerCase()
            .trim()
            .replace(/\s+/g, '-')
            .replace(/[^a-z0-9\-/_]/g, '');

        const getSectionDepth = (link) => {
            let depth = 0;
            let currentSection = link.closest('details');

            while (currentSection) {
                depth += 1;
                currentSection = currentSection.parentElement?.closest('details') ?? null;
            }

            return depth;
        };

        const getSectionKey = (section) => {
            if (section.dataset.menuSectionKey) {
                return section.dataset.menuSectionKey;
            }

            const link = section.querySelector(':scope > summary [data-mobile-nav-link]');
            const path = link ? normalizePath(link.href) : null;
            const fallbackLabel = link ? slugify(link.textContent || '') : '';
            const key = path || fallbackLabel || '';

            if (key) {
                section.dataset.menuSectionKey = key;
            }

            return key;
        };

        const readAccordionState = () => {
            try {
                const raw = sessionStorage.getItem(accordionStateKey);
                if (!raw) {
                    return {};
                }

                const parsed = JSON.parse(raw);
                return parsed && typeof parsed === 'object' ? parsed : {};
            } catch {
                return {};
            }
        };

        const writeAccordionState = (state) => {
            try {
                sessionStorage.setItem(accordionStateKey, JSON.stringify(state));
            } catch {
                // Ignore storage failures and keep menu functional.
            }
        };

        const collapseSection = (section) => {
            section.querySelectorAll('[data-mobile-menu-accordion][open]').forEach((nestedSection) => {
                nestedSection.open = false;
            });
            section.open = false;
        };

        const getSiblingSections = (section) => {
            const directParent = section.parentElement;
            if (!directParent) {
                return [];
            }

            const container = directParent.tagName === 'LI' ? directParent.parentElement : directParent;
            if (!container) {
                return [];
            }

            return Array.from(container.children).flatMap((child) => {
                if (child.matches('[data-mobile-menu-accordion]')) {
                    return [child];
                }

                if (child.tagName === 'LI') {
                    const nestedSection = child.querySelector(':scope > [data-mobile-menu-accordion]');
                    return nestedSection ? [nestedSection] : [];
                }

                return [];
            });
        };

        const restoreAccordionState = () => {
            const accordionState = readAccordionState();

            isRestoringAccordionState = true;
            accordionSections.forEach((section) => {
                const key = getSectionKey(section);
                section.open = key ? Boolean(accordionState[key]) : false;
            });
            isRestoringAccordionState = false;
        };

        const findBestLinkForPath = (path) => menuLinks
            .filter((link) => normalizePath(link.href) === path)
            .sort((firstLink, secondLink) => getSectionDepth(secondLink) - getSectionDepth(firstLink))[0] ?? null;

        const clearActiveState = () => {
            root.querySelectorAll('.desktop-mobile-menu-row-active').forEach((row) => {
                row.classList.remove('desktop-mobile-menu-row-active');
            });

            root.querySelectorAll('.desktop-mobile-menu-link-active').forEach((link) => {
                link.classList.remove('desktop-mobile-menu-link-active');
                if (link.getAttribute('aria-current') === 'page') {
                    link.removeAttribute('aria-current');
                }
            });
        };

        const revealLinkPath = (link) => {
            if (!link) {
                return;
            }

            clearActiveState();
            link.classList.add('desktop-mobile-menu-link-active');
            link.setAttribute('aria-current', 'page');
            link.closest('.desktop-mobile-menu-row')?.classList.add('desktop-mobile-menu-row-active');
        };

        const syncMenuState = () => {
            const currentPath = normalizePath(window.location.href);
            const currentLink = currentPath ? findBestLinkForPath(currentPath) : null;

            restoreAccordionState();
            clearActiveState();

            if (!currentLink) {
                return;
            }

            const accordionState = readAccordionState();
            const sectionsToReveal = [];
            let currentSection = currentLink.closest('details');

            while (currentSection) {
                sectionsToReveal.unshift(currentSection);
                currentSection = currentSection.parentElement?.closest('details') ?? null;
            }

            isRestoringAccordionState = true;
            sectionsToReveal.forEach((section) => {
                const key = getSectionKey(section);
                if (key && !Object.prototype.hasOwnProperty.call(accordionState, key)) {
                    section.open = true;
                }
            });
            isRestoringAccordionState = false;

            clearActiveState();
            revealLinkPath(currentLink);
        };

        const closeMenu = (restoreFocus = true) => {
            const wasOpen = root.dataset.menuOpen === '1';
            forceClosedState();
            if (wasOpen && restoreFocus) lastOpener?.focus();
        };

        const focusableMenuItems = () => Array.from(panel?.querySelectorAll(
            'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), summary, [tabindex]:not([tabindex="-1"])'
        ) ?? []).filter((element) => {
            if (!element.getClientRects().length || element.closest('[inert]')) return false;
            // Safari may report rectangles for links hidden by a closed details element.
            for (let ancestor = element.parentElement; ancestor && ancestor !== panel; ancestor = ancestor.parentElement) {
                if (ancestor.tagName !== 'DETAILS' || ancestor.open) continue;
                const summary = Array.from(ancestor.children).find((child) => child.tagName === 'SUMMARY');
                if (!summary?.contains(element)) return false;
            }
            return true;
        });

        const openMenu = (event) => {
            if (event) {
                event.preventDefault();
            }
            if (root.dataset.menuOpen === '1') return;
            lastOpener = event?.currentTarget ?? document.activeElement;
            root.classList.remove('pointer-events-none');
            root.dataset.menuOpen = '1';
            root.inert = false;
            root.setAttribute('aria-hidden', 'false');
            openButtons.forEach((button) => button.setAttribute('aria-expanded', 'true'));
            syncMenuState();
            overlay?.classList.remove('opacity-0');
            overlay?.classList.add('opacity-100');
            panel?.classList.remove('-translate-x-full');
            panel?.classList.add('translate-x-0');
            document.body.classList.add('overflow-hidden');
            document.body.classList.add('desktop-mobile-menu-open');
            (focusableMenuItems()[0] ?? panel)?.focus();
        };

        openButtons.forEach((button) => {
            button.addEventListener('click', openMenu);
            button.addEventListener('touchend', openMenu, { passive: false });
        });
        closeButtons.forEach((button) => button.addEventListener('click', () => closeMenu()));
        document.addEventListener('keydown', (event) => {
            if (root.dataset.menuOpen !== '1') return;
            if (event.key === 'Escape') {
                event.preventDefault();
                closeMenu();
                return;
            }
            if (event.key !== 'Tab') return;
            const items = focusableMenuItems();
            const first = items[0];
            const last = items[items.length - 1];
            if (!first) {
                event.preventDefault();
                panel?.focus();
            } else if (event.shiftKey && (document.activeElement === first || !panel.contains(document.activeElement))) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && (document.activeElement === last || !panel.contains(document.activeElement))) {
                event.preventDefault();
                first.focus();
            }
        });
        window.addEventListener('resize', () => {
            if (window.matchMedia('(min-width: 1024px)').matches) closeMenu(false);
        });
        accordionSections.forEach((section) => {
            section.addEventListener('toggle', () => {
                if (isRestoringAccordionState) {
                    return;
                }

                const key = getSectionKey(section);
                if (key) {
                    const accordionState = readAccordionState();
                    accordionState[key] = section.open;
                    writeAccordionState(accordionState);
                }

                if (!section.open) {
                    return;
                }

                getSiblingSections(section).forEach((otherSection) => {
                    if (otherSection !== section) {
                        collapseSection(otherSection);
                    }
                });
            });
        });
        root.querySelectorAll('summary [data-mobile-nav-link]').forEach((link) => {
            link.addEventListener('click', (event) => {
                event.stopPropagation();
            });
        });
        menuLinks.forEach((link) => {
            link.addEventListener('click', () => {
                closeMenu(false);
            });
        });

        syncMenuState();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
    window.addEventListener('pageshow', init);
})();
