/* A browser without matchMedia must retain usable native disclosures. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const panels = Array.from({length: 4}, () => ({hidden: false}));
const buttons = Array.from({length: 4}, (_, index) => ({
  getAttribute: name => name === 'aria-selected' ? String(index === 0) : null,
  addEventListener() {},
}));
const drawers = Array.from({length: 4}, (_, index) => ({open: index === 0}));
const rootClasses = new Set();
const root = {
  classList: {add: name => rootClasses.add(name)},
  getAttribute: name => name === 'data-mrn-tab-accordion' ? '767' : null,
  hasAttribute: name => name === 'data-mrn-tab-accordion',
  querySelectorAll: selector => ({
    '[data-mrn-tab-button]': buttons,
    '[data-mrn-tab-panel]': panels,
    '[data-mrn-tab-drawer]': drawers,
  }[selector] || []),
  setAttribute() { throw new Error('Unsupported enhancement must remain unmounted'); },
};
const document = {readyState: 'complete', querySelectorAll: () => [root]};
vm.runInNewContext(fs.readFileSync(path.resolve(__dirname, '../../js/front-end-tabs.js'), 'utf8'), {document, window: {}});
assert.ok(rootClasses.has('is-mobile-tabs'), 'Unsupported enhancement exposes disclosure controls');
assert.ok(panels.every(panel => panel.hidden === false));
assert.deepEqual(drawers.map(drawer => drawer.open), [true, false, false, false]);
console.log('PASS: no matchMedia retains native disclosure content and open state.');
