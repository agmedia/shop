const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

function firstScript(file, replacements) {
    let source = fs.readFileSync(path.join(__dirname, '../../resources/views/front/partials', file), 'utf8')
        .match(/<script>\s*([\s\S]*?)<\/script>/)[1];
    for (const [expression, value] of replacements) source = source.split(expression).join(JSON.stringify(value));
    return source;
}

test('Google destination is disabled before consent updates and SDK background events after withdrawal', () => {
    const id = 'G-DT810P4TK9';
    const scripts = [];
    const commands = [];
    const context = {
        dataLayer: { push(args) { commands.push({ args: [...args], disabled: context['ga-disable-' + id] }); } },
        document: { createElement: () => ({ remove() {} }), head: { appendChild: script => scripts.push(script) } },
    };
    context.window = context;
    vm.runInNewContext(firstScript('analytics.blade.php', [['@json($ga4Id)', id]]), context);
    assert.equal(context['ga-disable-' + id], true);
    assert.equal(commands[0].disabled, true);
    assert.equal(scripts.length, 0);

    context.updateGoogleConsentFromCookie(true, false);
    assert.equal(context['ga-disable-' + id], false);
    assert.equal(scripts.length, 1);
    assert.equal(commands.at(-1).disabled, false);

    // gtag checks this destination flag for automatic engagement/pagehide hits too.
    const requests = [];
    const sdkBackgroundHit = () => {
        if (context['ga-disable-' + id] !== true) requests.push('user_engagement');
    };
    sdkBackgroundHit();
    context.updateGoogleConsentFromCookie(false, false);
    assert.equal(commands.at(-1).disabled, true);
    sdkBackgroundHit();
    assert.deepEqual(requests, ['user_engagement']);
    assert.equal(context.canTrackAnalytics(), false);
    context.updateGoogleConsentFromCookie(true, true);
    sdkBackgroundHit();
    assert.equal(requests.length, 2);
    assert.equal(scripts.length, 1);
});

test('failed Google SDK loading can be retried after a new consent choice', () => {
    const scripts = [];
    let removed = false;
    const context = { document: {
        createElement: () => ({ remove() { removed = true; } }),
        head: { appendChild: script => scripts.push(script) },
    } };
    context.window = context;
    vm.runInNewContext(firstScript('analytics.blade.php', [['@json($ga4Id)', 'G-DT810P4TK9']]), context);
    context.updateGoogleConsentFromCookie(true, false);
    scripts[0].onerror();
    assert.equal(removed, true);
    context.updateGoogleConsentFromCookie(true, false);
    assert.equal(scripts.length, 2);
});

test('Klaviyo consent withdrawal removes cached identities and pending vendor events', () => {
    const scripts = [];
    const local = new Map([['__kl_key', 'old-identity'], ['unrelated', 'keep']]);
    const session = new Map([['klaviyoPagesVisitCountV2', '3']]);
    const context = {
        cookieMarketingAllowed: false,
        localStorage: { removeItem: key => local.delete(key) },
        sessionStorage: { removeItem: key => session.delete(key) },
        document: { cookie: '', createElement: () => ({}), head: { appendChild: script => scripts.push(script) } },
        location: { protocol: 'https:' },
    };
    context.window = context;
    vm.runInNewContext(firstScript('klaviyo.blade.php', [
        ["@json((bool) ($storeSettings['cookies']['enabled'] ?? true))", true],
        ['@json($klaviyoSiteId)', 'ABC123'],
    ]), context);
    assert.equal(scripts.length, 0);
    assert.match(context.document.cookie, /__kla_off=true/);
    assert.equal(local.has('__kl_key'), false);
    assert.equal(local.get('unrelated'), 'keep');
    context.updateKlaviyoConsentFromCookie(true);
    assert.equal(scripts.length, 1);
    assert.match(context.document.cookie, /__kla_off=false/);
    context._klOnsite.push(['track', 'pending']);
    local.set('__kl_key', 'new-identity');
    context.updateKlaviyoConsentFromCookie(false);
    assert.equal(context._klOnsite.length, 0);
    assert.equal(local.has('__kl_key'), false);
    assert.equal(session.size, 0);
    context.updateKlaviyoConsentFromCookie(true);
    assert.equal(scripts.length, 1);
});
