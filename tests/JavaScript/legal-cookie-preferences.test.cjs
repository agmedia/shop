const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

test('legal-page preference button opens the lazily loaded CMP on the first click without granting consent', async () => {
    const source = fs.readFileSync(path.join(__dirname, '../../resources/views/front/partials/cookie-consent.blade.php'), 'utf8');
    const script = source.slice(source.indexOf('const openCookiePreferences ='), source.indexOf('const hasStoredCookieConsent ='));
    const documentListeners = new Map();
    const floatingListeners = new Map();
    let assetsRequested = 0;
    let initialized = 0;
    let shown = 0;
    let finishLoading;
    const window = {};
    const document = {
        getElementById: () => ({ addEventListener: (name, fn) => floatingListeners.set(name, fn) }),
        addEventListener: (name, fn) => documentListeners.set(name, fn),
    };
    vm.runInNewContext(script, {
        window, document,
        ensureCookieConsentAssets: () => { assetsRequested++; return new Promise(resolve => { finishLoading = resolve; }); },
        runCookieConsent: () => { initialized++; },
    });
    let prevented = false;
    documentListeners.get('click')({
        target: { closest: selector => selector === '[data-cookie-consent-preferences]' ? {} : null },
        preventDefault: () => { prevented = true; },
    });
    assert.equal(prevented, true);
    assert.equal(assetsRequested, 1);
    assert.equal(shown, 0);
    window.CookieConsent = {
        showPreferences: () => { shown++; },
        acceptCategory: () => { throw new Error('Opening preferences must not grant consent'); },
    };
    finishLoading();
    await new Promise(setImmediate);
    assert.equal(initialized, 1);
    assert.equal(shown, 1);
    documentListeners.get('click')({ target: { closest: () => null }, preventDefault: () => assert.fail('Unrelated click') });
    assert.equal(assetsRequested, 1);
    assert.equal(typeof floatingListeners.get('click'), 'function');
});
