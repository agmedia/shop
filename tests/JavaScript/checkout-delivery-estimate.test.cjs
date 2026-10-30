const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

test('delivery estimate updates immediately when countries or separate-delivery choice change', () => {
    const template = fs.readFileSync(path.join(__dirname, '../../resources/views/front/partials/delivery-estimate.blade.php'), 'utf8');
    const script = template.match(/<script>([\s\S]*?)<\/script>/)[1];
    const listeners = new Map();
    const note = { hidden: false };
    const fields = {
        billing_country_code: { value: 'HR' },
        shipping_country_code: { value: 'DE' },
        ship_to_different_address: { checked: false },
    };
    const form = {
        querySelector(selector) {
            if (selector === '[data-de-at-delivery-note]') return note;
            const name = selector.match(/^\[name="(.+)"\]$/);
            return name ? fields[name[1]] : null;
        },
        addEventListener(name, callback) { listeners.set(name, callback); },
    };
    vm.runInNewContext(script, { document: { querySelectorAll: () => [form] } });
    assert.equal(note.hidden, true, 'unused German address must not affect Croatian delivery');

    fields.ship_to_different_address.checked = true;
    listeners.get('change')();
    assert.equal(note.hidden, false);

    fields.shipping_country_code.value = 'AT';
    listeners.get('input')();
    assert.equal(note.hidden, false);

    fields.shipping_country_code.value = 'HR';
    fields.billing_country_code.value = 'DE';
    listeners.get('change')();
    assert.equal(note.hidden, true, 'Croatian delivery must override German billing');

    fields.ship_to_different_address.checked = false;
    listeners.get('change')();
    assert.equal(note.hidden, false);

    fields.billing_country_code.value = 'HR';
    listeners.get('change')();
    assert.equal(note.hidden, true);
});
