const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../../public/front-theme/scripts/catalog-custom-select.js'), 'utf8');
const context = {
    document: { readyState: 'loading', addEventListener() {} },
    window: { addEventListener() {} },
};
// Expose the real private renderer without running the DOM initialization.
vm.runInNewContext(source.replace('    const buildCustomSelect =', '    globalThis.renderSwatch = applySwatchStyle;\n    const buildCustomSelect ='), context);

test('German filter labels do not change the catalog-provided swatch colors', () => {
    for (const [label, style] of [['Hautfarbe', 'background:#d2a181;'], ['Schwarz', 'background:#050505;'], ['Weiß', 'background:#fcfcfc;']]) {
        const swatch = { style: {}, textContent: '' };
        context.renderSwatch(swatch, { dataset: { filterSwatchStyle: style }, textContent: label });
        assert.equal(swatch.style.cssText, style);
        assert.equal(swatch.textContent, '');
    }
});

test('an uploaded swatch image takes precedence over a generated solid style', () => {
    const swatch = { style: {}, textContent: '?' };
    context.renderSwatch(swatch, { dataset: { filterSwatch: '/storage/real-upload.png', filterSwatchStyle: 'background:#d62828;' } });
    assert.equal(swatch.style.backgroundImage, 'url("/storage/real-upload.png")');
    assert.equal(swatch.style.cssText, '');
    assert.equal(swatch.textContent, '');
});

test('unknown colors show an unknown marker without inventing a shade from the label', () => {
    const swatch = { style: {}, textContent: '' };
    context.renderSwatch(swatch, { dataset: {}, textContent: 'Sondermuster' });
    assert.equal(swatch.style.cssText, '');
    assert.equal(swatch.textContent, '?');
});
