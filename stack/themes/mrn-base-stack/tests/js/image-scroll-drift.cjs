/* Native scroll drift: section geometry, caps, media changes and unchanged transforms. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../../js/presentation.js'), 'utf8');
function fixture(options = {}) {
 const queries = new Map(), handlers = {}, frames = [], resizeObservers = [];
 let rect = {top: 100, height: 400, bottom: 500};
 const image = {style: {translate: options.original || '', transform: 'rotate(2deg)'}};
 const section = {getBoundingClientRect: () => rect};
 const stage = {dataset: {mrnImageDrift: options.json ?? '{"speed":0.08,"maximum":36}'}, querySelector: () => image, closest: () => section};
 function matchMedia(query) {
  if (!queries.has(query)) queries.set(query, {matches: query.includes('reduce') ? !!options.reduced : query.includes('pointer') ? !options.touch : query.includes('768') ? !options.mobile : true, listeners: [], addEventListener(type, cb) {this.listeners.push(cb);}});
  return queries.get(query);
 }
 const document = {readyState: 'complete', querySelector: () => null, querySelectorAll: selector => selector === '[data-mrn-image-drift]' && !options.absent ? [stage] : []};
 class ResizeObserver {constructor(cb) {resizeObservers.push(cb);} observe() {}}
 const window = {innerHeight: 1000, matchMedia, ResizeObserver, requestAnimationFrame: cb => frames.push(cb), addEventListener(type, cb) {(handlers[type] ??= []).push(cb);}};
 vm.runInNewContext(source, {window, document, ResizeObserver});
 const flush = () => {while (frames.length) frames.shift()();};
 return {image, rect: value => {rect = value;}, resize() {for (const cb of resizeObservers) cb();}, scroll() {for (const cb of handlers.scroll) cb(); flush();}, change(term, value) {for (const [q, media] of queries) if (q.includes(term)) {media.matches = value; for (const cb of media.listeners) cb();} flush();}};
}
const f = fixture();
assert.equal(f.image.style.translate, '0 16.00px', 'Measure enclosing Split center, not the image center');
assert.equal(f.image.style.transform, 'rotate(2deg)', 'Drift composes with existing image transforms');
f.rect({top: 100, height: 600, bottom: 700}); f.resize(); assert.equal(f.image.style.translate, '0 8.00px', 'Accordion/image resizing refreshes the enclosing section geometry');
f.rect({top: -500, height: 800, bottom: 300}); f.scroll(); assert.equal(f.image.style.translate, '0 36.00px');
f.rect({top: 900, height: 600, bottom: 1500}); f.scroll(); assert.equal(f.image.style.translate, '0 -36.00px');
f.change('reduce', true); assert.equal(f.image.style.translate, '', 'Reduced motion resets immediately');
f.change('reduce', false); assert.equal(f.image.style.translate, '0 -36.00px');
f.change('768', false); assert.equal(f.image.style.translate, '', 'Resizing to mobile resets immediately');
f.change('768', true); assert.equal(f.image.style.translate, '0 -36.00px');
f.change('pointer', false); assert.equal(f.image.style.translate, '', 'Changing to touch resets immediately');
f.change('pointer', true); assert.equal(f.image.style.translate, '0 -36.00px');
for (const options of [{reduced:true}, {touch:true}, {mobile:true}, {absent:true}, {json:'{"speed":999,"maximum":36}'}, {json:'{"speed":0.08,"maximum":36.5}'}, {json:'null'}, {json:'invalid'}]) assert.equal(fixture(options).image.style.translate, '');
assert.equal(fixture({original:'0 5px'}).image.style.translate, '0 5px', 'Existing individual translation is left alone');
console.log('Passed: native section geometry, bidirectional caps, live reduced-motion/pointer/mobile changes, invalid settings and unchanged transforms/defaults');
