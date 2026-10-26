const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

function navigation() {
    const document = { readyState: 'complete', activeElement: null };
    function target(object = {}) {
        const listeners = new Map();
        return Object.assign(object, {
            addEventListener(name, callback) {
                const callbacks = listeners.get(name) || [];
                callbacks.push(callback);
                listeners.set(name, callbacks);
            },
            emit(name, values = {}) {
                const event = { target: this, currentTarget: this, defaultPrevented: false,
                    preventDefault() { this.defaultPrevented = true; }, stopPropagation() {}, ...values };
                (listeners.get(name) || []).forEach(callback => callback(event));
                return event;
            },
        });
    }
    target(document);
    function element(parent = null, attributes = {}) {
        const classes = new Set();
        const node = target({ parentElement: parent, children: [], dataset: {}, inert: false, tagName: 'DIV', attributes,
            classList: { add: (...items) => items.forEach(item => classes.add(item)),
                remove: (...items) => items.forEach(item => classes.delete(item)), contains: item => classes.has(item) },
            setAttribute(name, value) { this.attributes[name] = value; },
            getAttribute(name) { return this.attributes[name] ?? null; },
            removeAttribute(name) { delete this.attributes[name]; },
            contains(other) { return !!other && (other === this || this.children.some(child => child.contains(other))); },
            closest(selector) {
                if (selector === '[inert]') return this.inert ? this : this.parentElement?.closest(selector) ?? null;
                return null;
            },
            querySelectorAll(selector) { return this.queries?.[selector] ?? []; },
            querySelector(selector) { return this.querySelectorAll(selector)[0] ?? null; },
            getClientRects() { return [{}]; },
            focus() {
                const previous = document.activeElement;
                document.activeElement = this;
                for (let current = previous; current; current = current.parentElement) {
                    current.emit('focusout', { relatedTarget: this });
                }
            },
        });
        if (parent) parent.children.push(node);
        if (attributes.href) node.href = attributes.href;
        return node;
    }
    const body = element();
    document.body = body;
    document.activeElement = body;
    const groups = [0, 1].map(() => {
        const group = element(body);
        const toggle = element(group);
        const panel = element(group);
        const link = element(panel, { href: 'https://shop.test/category/example' });
        panel.queries = { 'a[href], button:not([disabled])': [link] };
        group.queries = { '[data-desktop-nav-toggle]': [toggle], '[data-desktop-nav-panel]': [panel] };
        return { group, toggle, panel, link };
    });
    const opener = element(body);
    const root = element(body);
    const overlay = element(root);
    const panel = element(root);
    const close = element(panel);
    const details = element(panel);
    details.tagName = 'DETAILS';
    details.open = false;
    const summary = element(details);
    summary.tagName = 'SUMMARY';
    const last = element(summary, { href: 'https://shop.test/category/other' });
    const hiddenLink = element(details, { href: 'https://shop.test/category/hidden' });
    panel.queries = { 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), summary, [tabindex]:not([tabindex="-1"])': [close, summary, last, hiddenLink] };
    root.queries = { '[data-mobile-menu-panel]': [panel], '[data-mobile-menu-close]': [overlay, close], 'a[href]': [last, hiddenLink] };
    document.querySelector = selector => selector === '[data-mobile-menu-root]' ? root : null;
    document.querySelectorAll = selector => ({
        '[data-desktop-nav-group]': groups.map(item => item.group), '[data-mobile-menu-open]': [opener],
    })[selector] ?? [];
    let desktopWidth = false;
    const window = target({ location: { href: 'https://shop.test/', origin: 'https://shop.test' },
        matchMedia: () => ({ matches: desktopWidth }) });
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/front-theme/scripts/desktop-header-menu.js'), 'utf8'), {
        document, window, URL, sessionStorage: { getItem: () => null, setItem() {} },
    });
    return { document, window, body, groups, opener, root, panel, close, last, details, summary, hiddenLink,
        desktopWidth() { desktopWidth = true; window.emit('resize'); } };
}

test('desktop disclosure activation and Escape restore focus without reopening the panel', () => {
    const nav = navigation();
    const { toggle, panel, link } = nav.groups[0];
    assert.equal(panel.inert, true);
    toggle.focus();
    toggle.emit('click'); // Native button activation is shared by mouse, Enter and Space.
    assert.equal(toggle.getAttribute('aria-expanded'), 'true');
    assert.equal(panel.inert, false);
    link.focus();
    const escape = nav.document.emit('keydown', { key: 'Escape' });
    assert.equal(escape.defaultPrevented, true);
    assert.equal(nav.document.activeElement, toggle);
    assert.equal(toggle.getAttribute('aria-expanded'), 'false');
    assert.equal(panel.getAttribute('aria-hidden'), 'true');
    assert.equal(panel.inert, true);
    toggle.emit('keydown', { key: 'ArrowDown' });
    assert.equal(nav.document.activeElement, link);
    nav.body.focus();
    assert.equal(panel.inert, true);
});

test('desktop hover still works and opening another group closes the earlier panel', () => {
    const nav = navigation();
    nav.groups[0].group.emit('mouseenter');
    assert.equal(nav.groups[0].panel.inert, false);
    nav.groups[1].group.emit('mouseenter');
    assert.equal(nav.groups[0].panel.inert, true);
    assert.equal(nav.groups[1].panel.inert, false);
    nav.groups[1].group.emit('mouseleave');
    assert.equal(nav.groups[1].panel.inert, true);
});

test('mobile navigation traps Tab in the open panel and Escape restores its opener', () => {
    const nav = navigation();
    assert.equal(nav.root.inert, true);
    nav.opener.focus();
    nav.opener.emit('click');
    assert.equal(nav.opener.getAttribute('aria-expanded'), 'true');
    assert.equal(nav.root.inert, false);
    assert.equal(nav.document.activeElement, nav.close);
    nav.last.focus();
    nav.document.emit('keydown', { key: 'Tab' });
    assert.equal(nav.document.activeElement, nav.close);
    nav.document.emit('keydown', { key: 'Tab', shiftKey: true });
    assert.equal(nav.document.activeElement, nav.last);
    nav.document.emit('keydown', { key: 'Escape' });
    assert.equal(nav.document.activeElement, nav.opener);
    assert.equal(nav.root.inert, true);
    assert.equal(nav.root.getAttribute('aria-hidden'), 'true');
    assert.equal(nav.opener.getAttribute('aria-expanded'), 'false');
    assert.equal(nav.body.classList.contains('overflow-hidden'), false);
});

test('mobile close button restores focus and desktop resize releases the modal state', () => {
    const nav = navigation();
    nav.opener.emit('click');
    nav.close.emit('click');
    assert.equal(nav.document.activeElement, nav.opener);
    nav.opener.emit('click');
    nav.desktopWidth();
    assert.equal(nav.root.inert, true);
    assert.equal(nav.opener.getAttribute('aria-expanded'), 'false');
    assert.equal(nav.body.classList.contains('desktop-mobile-menu-open'), false);
});

test('closed details descendants with Safari rectangles do not extend the mobile focus loop', () => {
    const nav = navigation();
    nav.opener.emit('click');
    assert.equal(nav.hiddenLink.getClientRects().length, 1);
    nav.summary.focus();
    assert.equal(nav.document.emit('keydown', { key: 'Tab' }).defaultPrevented, false);
    nav.last.focus();
    assert.equal(nav.document.emit('keydown', { key: 'Tab' }).defaultPrevented, true);
    assert.equal(nav.document.activeElement, nav.close);
    nav.document.emit('keydown', { key: 'Tab', shiftKey: true });
    assert.equal(nav.document.activeElement, nav.last);

    nav.details.open = true;
    nav.hiddenLink.focus();
    nav.document.emit('keydown', { key: 'Tab' });
    assert.equal(nav.document.activeElement, nav.close);
    nav.document.emit('keydown', { key: 'Tab', shiftKey: true });
    assert.equal(nav.document.activeElement, nav.hiddenLink);
});
