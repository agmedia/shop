const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const script = fs.readFileSync(path.join(__dirname, '../../public/front-theme/scripts/address-autofill.js'), 'utf8');
const places = [{ city: 'Zagreb', postal_code: '10000', county: 'Grad Zagreb', country_code: 'HR' }];

async function setup(country, deferData = false) {
    const field = value => ({
        value, options: [{ value: 'Grad Zagreb', textContent: 'Grad Zagreb' }], listeners: {},
        addEventListener(name, callback) { (this.listeners[name] ||= []).push(callback); },
        dispatchEvent(event) { for (const callback of this.listeners[event.type] || []) callback(event); },
    });
    const city = field('');
    const postal = field('');
    const county = field('');
    const selectedCountry = field(country);
    const fields = { '[data-address-city]': city, '[data-address-postal]': postal, '[data-address-county]': county, '[data-address-country]': selectedCountry };
    const scope = { querySelector: selector => fields[selector] || null };
    const root = { getAttribute: () => '/hr-places.json', querySelectorAll: () => [scope] };
    let resolveFetch;
    const response = { ok: true, json: async () => ({ places }) };
    const request = deferData ? new Promise(resolve => { resolveFetch = resolve; }) : Promise.resolve(response);
    vm.runInNewContext(script, {
        document: { readyState: 'complete', querySelectorAll: () => [root] },
        fetch: () => request,
        Event: class { constructor(type) { this.type = type; } },
    });
    const flush = () => new Promise(resolve => setImmediate(resolve));
    await flush();
    return {
        city, postal, county, country: selectedCountry,
        fire(node, type) { node.dispatchEvent({ type }); },
        async loadData() { resolveFetch(response); await flush(); },
    };
}

test('Croatian city and postal autofill still complete the equivalent address and county', async () => {
    const ui = await setup('HR');
    ui.city.value = ' Zagreb ';
    ui.fire(ui.city, 'blur');
    assert.equal(ui.city.value, 'Zagreb');
    assert.equal(ui.postal.value, '10000');
    assert.equal(ui.county.value, 'Grad Zagreb');
    assert.equal(ui.country.value, 'HR');

    ui.city.value = '';
    ui.postal.value = '10 000';
    ui.fire(ui.postal, 'input');
    assert.equal(ui.city.value, 'Zagreb');
    assert.equal(ui.postal.value, '10000');
});

for (const country of ['DE', 'AT']) {
    test(`${country} city/postal events never overwrite address, region or country from Croatian data`, async () => {
        const ui = await setup(country);
        ui.city.value = 'Zagreb';
        ui.postal.value = '10000';
        ui.county.value = 'Customer region';
        for (const type of ['change', 'blur']) ui.fire(ui.city, type);
        for (const type of ['input', 'change', 'blur']) ui.fire(ui.postal, type);
        assert.equal(ui.city.value, 'Zagreb');
        assert.equal(ui.postal.value, '10000');
        assert.equal(ui.county.value, 'Customer region');
        assert.equal(ui.country.value, country);
    });
}

test('country changes clear untouched Croatian suggestions and preserve manually edited fields', async () => {
    const ui = await setup('HR');
    ui.postal.value = '10000';
    ui.fire(ui.postal, 'input');
    ui.city.value = 'Berlin';
    ui.country.value = 'DE';
    ui.fire(ui.country, 'change');
    assert.equal(ui.city.value, 'Berlin');
    assert.equal(ui.postal.value, '');
    assert.equal(ui.county.value, '');
    assert.equal(ui.country.value, 'DE');

    ui.postal.value = '10115';
    ui.county.value = 'Berlin';
    ui.country.value = 'AT';
    ui.fire(ui.country, 'change');
    assert.equal(ui.city.value, 'Berlin');
    assert.equal(ui.postal.value, '10115');
    assert.equal(ui.county.value, 'Berlin');
});

test('late Croatian directory data respects a country changed while the request was pending', async () => {
    const ui = await setup('HR', true);
    ui.country.value = 'DE';
    ui.city.value = 'Zagreb';
    ui.postal.value = '10000';
    await ui.loadData();
    ui.fire(ui.postal, 'input');
    ui.fire(ui.city, 'blur');
    assert.equal(ui.country.value, 'DE');
    assert.equal(ui.city.value, 'Zagreb');
    assert.equal(ui.postal.value, '10000');
    assert.equal(ui.county.value, '');

    ui.country.value = 'HR';
    ui.fire(ui.country, 'change');
    ui.fire(ui.postal, 'input');
    assert.equal(ui.county.value, 'Grad Zagreb');
});
