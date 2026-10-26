/* Runtime behavior without driving a browser: bounds, batching and motion changes. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const script = fs.readFileSync(path.resolve(__dirname, '../../js/front-end-image-action.js'), 'utf8');
const queries = [{matches: true, listeners: []}, {matches: false, listeners: []}];
queries.forEach(q => q.addEventListener = (_, callback) => q.listeners.push(callback));
let nextFrame = 0;
const frames = new Map();
const windowEvents = {};
function action() {
  const classes = new Set(); const values = new Map(); const events = {};
  return {
    dataset: {}, classes, values, events,
    classList: {add: n => classes.add(n), remove: n => classes.delete(n), toggle: (n, state) => state ? classes.add(n) : classes.delete(n)},
    style: {setProperty: (n, v) => values.set(n, v), removeProperty: n => values.delete(n)},
    querySelector: () => ({offsetWidth: 52, offsetHeight: 54}),
    getAttribute: () => '18',
    getBoundingClientRect: () => ({left: 100, top: 200, width: 570, height: 360}),
    addEventListener: (n, callback) => events[n] = callback,
  };
}
const actions = [action(), action()];
const window = {
  matchMedia: q => queries[q.includes('reduced') ? 1 : 0],
  requestAnimationFrame: callback => {frames.set(++nextFrame, callback); return nextFrame;},
  cancelAnimationFrame: id => frames.delete(id),
  addEventListener: (name, callback) => (windowEvents[name] ||= []).push(callback),
};
vm.runInNewContext(script, {window, document: {readyState: 'complete', querySelectorAll: () => actions}});
assert.ok(actions.every(a => a.classes.has('has-pointer-follow')));
actions[0].events.pointerenter({clientX: 0, clientY: 0, pointerType: 'mouse'});
actions[0].events.pointermove({clientX: 10000, clientY: 10000, pointerType: 'mouse'});
assert.equal(frames.size, 1, 'Pointer updates coalesce into one frame');
for (const [id, callback] of frames) {frames.delete(id); callback();}
assert.equal(actions[0].values.get('--mrn-image-action-x'), '526px');
assert.equal(actions[0].values.get('--mrn-image-action-y'), '315px');
assert.ok(!actions[1].classes.has('is-following'), 'Actions do not affect siblings');
actions[0].events.pointermove({clientX: 0, clientY: 0, pointerType: 'mouse'});
for (const [id, callback] of frames) {frames.delete(id); callback();}
assert.equal(actions[0].values.get('--mrn-image-action-x'), '44px');
actions[0].events.focus();
assert.equal(actions[0].values.size, 0, 'Keyboard focus resets to fixed marker');
queries[1].matches = true; queries[1].listeners.forEach(f => f());
assert.ok(actions.every(a => !a.classes.has('has-pointer-follow')), 'Reduced motion disables following');
actions[0].events.pointermove({clientX: 300, clientY: 300, pointerType: 'mouse'});
assert.equal(frames.size, 0, 'Reduced motion schedules no movement');
queries[1].matches = false; queries[1].listeners.forEach(f => f());
actions[0].events.pointerenter({clientX: 300, clientY: 300, pointerType: 'mouse'});
windowEvents.scroll.forEach(f => f());
assert.equal(frames.size, 0, 'Scroll cancels pending frames and stale geometry');
queries[0].matches = false; queries[0].listeners.forEach(f => f());
assert.ok(actions.every(a => !a.classes.has('has-pointer-follow')), 'Touch/coarse pointer retains fixed marker');
vm.runInNewContext(script, {window: {}});
console.log('PASS: pointer bounds, frame coalescing, sibling isolation, focus, scroll, motion and no-API fallback.');
