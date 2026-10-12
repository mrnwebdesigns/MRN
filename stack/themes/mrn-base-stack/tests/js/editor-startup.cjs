'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const actions = new Map();
let calls = 0;
function Select() {}
Select.prototype.initialize = function() { calls++; };
const acf = {
	getFieldType: () => Select,
	addAction: (name, callback) => actions.set(name, callback),
	getFields: () => [],
};
vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../js/admin-editor-startup.js'), 'utf8'), {acf, jQuery: {}});
let closed = true;
const field = new Select();
field.$el = {
	closest: selector => ({length: selector === '.mrn-acf-lazy-layouts' ? 1 : selector === '.acf-clone' ? 0 : closed ? 1 : 0}),
	is: () => true,
};
field.get = key => key === 'key' ? 'field_mrn_saved_choice' : key === 'ui' ? 1 : null;
field.$input = () => ({value: 'saved-choice'});
let inherited;
field.inherit = input => { inherited = input.value; };
field.initialize();
assert.equal(calls, 0, 'Closed rows must not create enhanced select widgets');
assert.equal(inherited, 'saved-choice', 'Native input data remains available before enhancement');
actions.get('load')();
actions.get('show_field/type=select')(field);
assert.equal(calls, 0, 'A show event for a still-closed ancestor must not initialize');
closed = false;
actions.get('show_field/type=select')(field);
actions.get('show_field/type=select')(field);
assert.equal(calls, 1, 'Opening the row initializes the native widget once');
field.initialize();
assert.equal(calls, 2, 'Visible/new rows retain native initialization');
console.log('PASS: native values, closed widgets, nested visibility and one-time opening initialization');
