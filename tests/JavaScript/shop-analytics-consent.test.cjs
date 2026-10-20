const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

function storefront() {
    const listeners = new Map();
    const storage = new Map();
    const google = [];
    const meta = [];
    const window = {
        cookieMarketingAllowed: false,
        cookieAnalyticsAllowed: false,
        canTrackAnalytics() { return this.cookieAnalyticsAllowed; },
        gtag(...args) { google.push(args); },
        ShopMetaPixel: { trackFromGa4(name, payload) {
            if (!window.cookieMarketingAllowed) return false;
            meta.push({ name, payload });
            return true;
        } },
        sessionStorage: { getItem: key => storage.get(key), setItem: (key, value) => storage.set(key, value) },
        addEventListener: (name, callback) => listeners.set(name, callback),
    };
    // The production callback does not rely on the receiver of canTrackAnalytics.
    window.canTrackAnalytics = () => window.cookieAnalyticsAllowed;
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/front-theme/scripts/shop-analytics.js'), 'utf8'), {
        window,
        document: { addEventListener() {} },
    });
    return { window, google, meta, consent: () => listeners.get('shop:tracking-consent')() };
}

test('no events before consent; separate consent choices replay each purchase only once', () => {
    const shop = storefront();
    shop.window.ShopAnalytics.trackOnce('purchase:1', 'purchase', { transaction_id: '1', value: 119 });
    assert.equal(shop.google.length, 0);
    assert.equal(shop.meta.length, 0);
    shop.window.cookieMarketingAllowed = true;
    shop.consent();
    assert.equal(shop.meta.length, 1);
    assert.equal(shop.google.length, 0);
    shop.window.cookieAnalyticsAllowed = true;
    shop.consent();
    shop.consent();
    assert.equal(shop.meta.length, 1);
    assert.equal(shop.google.length, 1);
    shop.window.ShopAnalytics.trackOnce('purchase:1', 'purchase', { transaction_id: '1' });
    assert.equal(shop.google.length, 1);
});

test('revoked consent blocks later events and previous cart actions are not replayed', () => {
    const shop = storefront();
    shop.window.ShopAnalytics.track('add_to_cart', { items: [] });
    shop.window.cookieAnalyticsAllowed = true;
    shop.consent();
    assert.equal(shop.google.length, 0);
    shop.window.ShopAnalytics.track('add_to_cart', { items: [] });
    assert.equal(shop.google.length, 1);
    shop.window.cookieAnalyticsAllowed = false;
    shop.window.ShopAnalytics.track('add_to_cart', { items: [] });
    assert.equal(shop.google.length, 1);
});
