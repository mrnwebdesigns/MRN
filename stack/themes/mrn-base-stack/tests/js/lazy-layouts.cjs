/* Exercise the adapter's asynchronous contract without WordPress or a browser. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const actions = {};
const filters = {};
const requests = [];
let submit;
let clone;
let rows = 0;
let callbacks = 0;
let additions = 0;

function template(loaded = false) {
	const attributes = { 'data-layout': 'basic', 'data-mrn-loaded': loaded ? '1' : '0' };
	return {
		length: 1,
		attr(name, value) {
			if (value === undefined) return attributes[name];
			attributes[name] = value;
			return this;
		},
		filter() { return this; },
		replaceWith(replacement) { clone = replacement; }
	};
}
clone = template();
const control = {
	attr() { return this; },
	removeAttr() { return this; },
	children() { return { first: () => ({ attr: () => 'acf[field_mrn_test]' }) }; }
};
const field = {
	$el: { hasClass: () => true, closest: () => ({ length: 1 }) },
	$control: () => control,
	$clone: () => clone,
	get: key => key === 'key' ? 'field_mrn_test' : 'test',
	validateAdd: () => true,
	countLayoutsByName: () => rows < 1,
	showNotice() {},
	removeNotice() {},
	add() { additions++; rows++; return { length: 1 }; }
};
function $(html) { return template(); }
$.ajax = () => {
	const handlers = {};
	const request = {
		done(fn) { handlers.done = fn; return this; },
		fail(fn) { handlers.fail = fn; return this; },
		always(fn) { handlers.always = fn; return this; },
		resolve(value) { handlers.done(value); handlers.always(); },
		reject() { handlers.fail(); handlers.always(); }
	};
	requests.push(request);
	return request;
};
const acf = {
	addAction: (name, fn) => { actions[name] = fn; },
	addFilter: (name, fn) => { filters[name] = fn; },
	parseArgs: (args, defaults) => ({ ...defaults, ...args }),
	get: () => 'test',
	prepareForAjax: data => data,
	disable() {},
	newNotice() {}
};
vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../js/admin-lazy-layouts.js'), 'utf8'), {
	acf, jQuery: $, mrnBuilderLayouts: {},
	document: { addEventListener: (name, fn) => { submit = fn; } }
});
actions['new_field/type=flexible_content'](field);
actions['ready_field/type=flexible_content'](field);
function saveBlocked() {
	let blocked = false;
	submit({ target: { id: 'post' }, preventDefault() { blocked = true; }, stopImmediatePropagation() {} });
	return blocked;
}
const args = { layout: 'basic', mrnComplete() { callbacks++; } };
field.add(args);
field.add(args);
assert.equal(requests.length, 1, 'Rapid add clicks must not queue duplicate rows.');
assert.equal(saveBlocked(), true, 'Native form save waits for loading.');
assert.equal(filters.validation_complete({ valid: true }, { attr: () => 'post' }).valid, false);
requests[0].resolve(null);
assert.equal(additions, 0, 'Malformed responses must never create an empty row.');
assert.equal(saveBlocked(), false, 'Malformed responses must release the save guard.');

field.add(args);
assert.equal(requests.length, 2, 'A failed request remains retryable.');
rows = 1; // Another editor action fills the per-layout limit during the request.
requests[1].resolve({ success: true, data: { html: '<div class="layout acf-clone"></div>' } });
assert.equal(additions, 0, 'Delayed completion must honor the current per-layout limit.');
assert.equal(callbacks, 0, 'Blocked conversion must not remove its original row.');
assert.equal(saveBlocked(), false);

rows = 0;
field.add(args);
assert.equal(requests.length, 2, 'The already loaded template is reused.');
assert.equal(additions, 1);
assert.equal(callbacks, 1, 'Conversion runs only after the native row is available.');
field.add(args);
assert.equal(additions, 1, 'Cached templates still honor layout limits.');

rows = 0;
clone = template();
field.add(args);
requests[2].reject();
assert.equal(saveBlocked(), false, 'Transport failure releases the save guard.');
assert.equal(additions, 1);
console.log('PASS: async retry, malformed/failed requests, save guards, limits, and conversion completion.');
