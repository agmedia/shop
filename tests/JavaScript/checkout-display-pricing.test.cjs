const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

test('destination updates replace every gross summary price, included VAT label and product line', () => {
    const template = fs.readFileSync(path.join(__dirname, '../../resources/views/front/partials/checkout-display-pricing.blade.php'), 'utf8');
    const script = template.match(/<script>([\s\S]*?)<\/script>/)[1];
    let listener;
    const nodes = Object.fromEntries(['subtotal', 'discount_total', 'tax_total', 'shipping_total', 'payment_fee_total', 'grand_total'].map(key => [key, { textContent: 'old' }]));
    const taxLabel = { textContent: 'old' };
    const discountRow = { hidden: true, style: { display: 'none' } };
    const line = { textContent: 'old', dataset: { checkoutLineTotal: 'item-1' } };
    const form = {
        querySelectorAll(selector) {
            const price = selector.match(/^\[data-checkout-price="(.+)"\]$/);
            if (price) return [nodes[price[1]]];
            return { '[data-checkout-tax-label]': [taxLabel], '[data-summary-discount-row]': [discountRow], '[data-checkout-line-total]': [line] }[selector] || [];
        },
    };
    vm.runInNewContext(script, {
        window: { addEventListener(name, callback) { assert.equal(name, 'shop:checkout-pricing'); listener = callback; } },
        document: { querySelectorAll: () => [form] },
    });
    for (const [country, rate, subtotal, discount, total] of [['DE', 19, '119 €', '11.90 €', '119.10 €'], ['AT', 20, '120 €', '12 €', '120 €']]) {
        const pricing = { subtotal_formatted: subtotal, discount_total: 12, discount_total_formatted: discount, tax_total_formatted: '20 €', shipping_total_formatted: '10 €', payment_fee_total_formatted: '2 €', grand_total_formatted: total, tax_label: `Enthaltene MwSt. (${rate} %)` };
        listener({ detail: { display_pricing: pricing, lines: [{ key: 'item-1', total_formatted: `${country} product` }] } });
        for (const key of Object.keys(nodes)) assert.equal(nodes[key].textContent, pricing[key + '_formatted']);
        assert.equal(taxLabel.textContent, pricing.tax_label);
        assert.equal(line.textContent, `${country} product`);
        assert.equal(discountRow.hidden, false);
        assert.equal(discountRow.style.display, '');
    }
    listener({ detail: { display_pricing: { discount_total: 0 }, lines: [] } });
    assert.equal(discountRow.hidden, true);
    assert.equal(discountRow.style.display, 'none');
});

for (const device of ['desktop', 'mobile']) {
    test(`${device} checkout refresh uses gross display data from the country response`, () => {
        const template = fs.readFileSync(path.join(__dirname, `../../resources/views/front/${device}/checkout/create.blade.php`), 'utf8');
        assert.ok(template.includes('renderCheckoutTotals(payload.display_pricing || totals)'));
        assert.ok(template.includes("new CustomEvent('shop:checkout-pricing', { detail: payload })"));
        assert.ok(template.includes("@include('front.partials.checkout-display-pricing')"));
    });
}
