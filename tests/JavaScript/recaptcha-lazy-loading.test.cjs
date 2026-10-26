const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const views = path.join(__dirname, '../../resources/views/front');
const flush = () => new Promise(setImmediate);

function scriptFrom(file) {
    return [...fs.readFileSync(file, 'utf8').matchAll(/<script>([\s\S]*?)<\/script>/g)]
        .map(match => match[1]).join('\n');
}

function page(template, action) {
    const requests = [];
    const activeScripts = [];
    const timers = new Map();
    const fields = {
        name: { value: 'Test Visitor' }, email: { value: 'visitor@example.test' },
        message: { value: 'A valid message for the shop.' }, accept_terms: { checked: true },
        order_number: { value: 'TEST-1' }, return_items: { value: 'One item' },
    };
    const token = { value: '' };
    let submitListener;
    const form = {
        dataset: { recaptchaSiteKey: 'public-site-key', recaptchaAction: action },
        submissions: 0,
        querySelector(selector) {
            if (selector === '[data-recaptcha-token]') return token;
            const name = selector.match(/^\[name="(.+)"\]$/);
            return name ? fields[name[1]] || null : null;
        },
        querySelectorAll() { return []; },
        addEventListener(name, callback) { if (name === 'submit') submitListener = callback; },
        submit() { this.submissions++; },
    };
    const window = {
        setTimeout(callback) { const id = timers.size + 1; timers.set(id, callback); return id; },
        clearTimeout(id) { timers.delete(id); },
    };
    const document = {
        querySelectorAll() { return [form]; },
        querySelector() { return activeScripts[0] || null; },
        createElement() {
            const listeners = new Map();
            const script = {
                addEventListener(name, callback) { listeners.set(name, callback); },
                emit(name) { listeners.get(name)(); },
                remove() { const index = activeScripts.indexOf(this); if (index >= 0) activeScripts.splice(index, 1); },
            };
            return script;
        },
        head: { appendChild(script) { requests.push(script.src); activeScripts.push(script); } },
    };
    const context = vm.createContext({ window, document });
    vm.runInContext(scriptFrom(path.join(views, 'partials/recaptcha-loader.blade.php')), context);
    vm.runInContext(scriptFrom(path.join(views, template)), context);
    return {
        window, requests, activeScripts, timers, fields, token, form,
        submit: () => submitListener({ preventDefault() {} }),
        ready(execute = () => Promise.resolve('fresh-v3-token')) {
            window.grecaptcha = { ready(callback) { callback(); }, execute };
            activeScripts[0].emit('load');
        },
    };
}

for (const variant of ['desktop', 'mobile']) {
    for (const [kind, action] of [['contact', 'contact_form'], ['returns', 'return_request_form']]) {
        test(`${variant} ${kind} loads Google only after a valid submission`, async () => {
            const shop = page(`${variant}/${kind}/create.blade.php`, action);
            assert.equal(shop.requests.length, 0);
            shop.fields.email.value = 'invalid';
            shop.submit();
            assert.equal(shop.requests.length, 0);
            assert.equal(shop.form.submissions, 0);

            shop.fields.email.value = 'visitor@example.test';
            shop.submit();
            assert.equal(shop.requests.length, 1);
            assert.equal(shop.form.submissions, 0);
            const executions = [];
            shop.ready((key, options) => {
                executions.push([key, options.action]);
                return Promise.resolve('fresh-v3-token');
            });
            await flush();
            assert.deepEqual(executions, [['public-site-key', action]]);
            assert.equal(shop.token.value, 'fresh-v3-token');
            assert.equal(shop.form.submissions, 1);
        });
    }
}

test('auth forms use the same on-submit loader and preserve their action', async () => {
    const shop = page('partials/recaptcha-v3.blade.php', 'login_form');
    assert.equal(shop.requests.length, 0);
    shop.submit();
    assert.equal(shop.requests.length, 1);
    shop.ready();
    await flush();
    assert.equal(shop.token.value, 'fresh-v3-token');
    assert.equal(shop.form.submissions, 1);
});

test('concurrent submissions share one SDK request', async () => {
    const shop = page('partials/recaptcha-v3.blade.php', 'register_form');
    const first = shop.window.getStoreRecaptchaToken('public-site-key', 'register_form');
    const second = shop.window.getStoreRecaptchaToken('public-site-key', 'contact_form');
    assert.equal(shop.requests.length, 1);
    shop.ready();
    assert.deepEqual(await Promise.all([first, second]), ['fresh-v3-token', 'fresh-v3-token']);
});

test('load and execute failures submit an empty token for existing server-side validation and allow retry', async () => {
    const shop = page('desktop/contact/create.blade.php', 'contact_form');
    shop.submit();
    shop.activeScripts[0].emit('error');
    await flush();
    assert.equal(shop.form.submissions, 1);
    assert.equal(shop.token.value, '');

    shop.submit();
    assert.equal(shop.requests.length, 2);
    shop.ready(() => Promise.reject(new Error('verification unavailable')));
    await flush();
    assert.equal(shop.form.submissions, 2);
    assert.equal(shop.token.value, '');
});

test('disabled CAPTCHA sends no SDK request', () => {
    const shop = page('mobile/returns/create.blade.php', 'return_request_form');
    delete shop.form.dataset.recaptchaSiteKey;
    shop.submit();
    assert.equal(shop.requests.length, 0);
    assert.equal(shop.form.submissions, 1);
});
