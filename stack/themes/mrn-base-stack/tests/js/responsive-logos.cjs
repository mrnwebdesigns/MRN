/* Verify the opt-in motion contract and compatibility with existing sliders. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../../js/front-end-slider.js'), 'utf8');

function fixture(attributes = {}, overflowing = true) {
	const listeners = {};
	const queries = {};
	const timers = [];
	const toggle = {
		hidden: true, textContent: '',
		getAttribute(name) { return name === 'data-pause-label' ? 'Pause logos' : 'Resume logos'; },
		setAttribute(name, value) { this[name] = value; },
		addEventListener(name, callback) { this[name] = callback; },
	};
	const element = {
		dataset: {}, classList: { contains() { return overflowing; } },
		getAttribute(name) { return attributes[name] ?? null; },
		hasAttribute(name) { return name in attributes; },
		setAttribute(name, value) { attributes[name] = value; },
		querySelector() { return toggle; },
		contains(target) { return target === toggle; },
		addEventListener(name, callback) { listeners[name] = callback; },
	};
	let instance, intersection;
	const document = { readyState: 'complete', hidden: false, querySelectorAll() { return [element]; }, addEventListener(name, callback) { listeners[name] = callback; } };
	const window = {
		Splide: function(target, options) {
			instance = this; this.events = {};
			Object.defineProperty(this, 'options', {get() { return options; }, set(value) { Object.assign(options, value); }});
			this.Components = { Autoplay: { play() { instance.playing = true; }, pause() { instance.playing = false; } }, Move: { cancel() { instance.freezes = (instance.freezes || 0) + 1; } } };
			this.mount = () => {}; this.on = (name, callback) => { this.events[name] = callback; };
			this.go = (index) => { this.index = index; };
		},
		matchMedia(query) {
			return queries[query] ??= { matches: false, addEventListener(name, callback) { this.changed = callback; }, set(matches) { this.matches = matches; this.changed?.(); } };
		},
		setTimeout(callback) { timers.push(callback); },
		IntersectionObserver: function(callback) { intersection = callback; this.observe = () => {}; },
	};
	vm.runInNewContext(source, { window, document, console });
	return { element, instance, queries, toggle, listeners, document, timers, intersection: (visible) => intersection([{isIntersecting:visible}]) };
}

const legacy = fixture({'data-per-page':'6','data-autoplay':'true'});
assert.equal(legacy.instance.options.type, 'slide');
assert.equal(legacy.instance.options.perPage, 6);
assert.equal(legacy.instance.options.autoplay, true);
assert.equal(legacy.instance.options.pauseOnHover, true);
assert.equal(legacy.instance.options.pauseOnFocus, true);
assert.equal(legacy.instance.options.gap, '1.5rem');
assert.equal('easing' in legacy.instance.options, false);
assert.equal('clones' in legacy.instance.options, false);
assert.equal(legacy.toggle.hidden, true);

const attributes = {
	'data-logos-responsive':'true','data-per-page':'7','data-autoplay':'true',
	'data-slider-breakpoints':'{"1199":{"perPage":5},"767":{"perPage":3},"479":{"perPage":2}}',
	'data-autoplay-max-width':'1199','data-continuous-scroll':'true','data-slider-gap':'30px',
};
const f = fixture(attributes);
const reduced = f.queries['(prefers-reduced-motion: reduce)'];
const small = f.queries['(max-width: 1199px)'];
assert.equal(f.instance.options.type, 'loop');
assert.equal(f.instance.options.perPage, 7);
assert.equal(f.instance.options.breakpoints[1199].perPage, 5);
assert.equal(f.instance.options.breakpoints[767].perPage, 3);
assert.equal(f.instance.options.breakpoints[479].perPage, 2);
assert.equal(f.instance.options.gap, '30px');
assert.equal(f.instance.options.easing, 'linear');
assert.equal(f.instance.playing, false, 'Desktop stays static');
small.set(true);
assert.equal(f.instance.playing, true, 'Overflowing small screens autoplay');
assert.equal(f.toggle.hidden, false);
f.listeners.mouseenter(); assert.equal(f.instance.playing, false);
assert.equal(f.instance.freezes, 1, 'Pausing freezes the in-progress transition, not only the next autoplay tick');
f.listeners.mouseleave(); assert.equal(f.instance.playing, true);
f.listeners.focusin(); assert.equal(f.instance.playing, false);
f.listeners.focusout({relatedTarget:null}); assert.equal(f.instance.playing, true);
f.toggle.click(); assert.equal(f.instance.playing, false); assert.equal(f.toggle.textContent, 'Resume logos');
f.toggle.click(); assert.equal(f.instance.playing, true); assert.equal(f.toggle.textContent, 'Pause logos');
reduced.set(true); assert.equal(f.instance.playing, false);
reduced.set(false); assert.equal(f.instance.playing, true);
f.document.hidden = true; f.listeners.visibilitychange(); assert.equal(f.instance.playing, false);
f.document.hidden = false; f.listeners.visibilitychange(); assert.equal(f.instance.playing, true);
f.intersection(false); assert.equal(f.instance.playing, false);
f.intersection(true); assert.equal(f.instance.playing, true);
f.instance.events.overflow(false); assert.equal(f.instance.playing, false); assert.equal(f.instance.index, 0); assert.equal(f.instance.options.clones, 0);
f.instance.events.overflow(true); assert.equal(f.instance.playing, true);
small.set(false); assert.equal(f.instance.playing, false);
const malformed = fixture({...attributes,'data-slider-breakpoints':'{"767":null}'});
assert.equal(malformed.instance.options.breakpoints[480].perPage, 1, 'Malformed overrides retain the existing views');
const delayed = fixture({...attributes,'data-delay-start':'2'});
delayed.queries['(max-width: 1199px)'].set(true);
assert.equal(delayed.instance.playing, false);
delayed.timers[0](); assert.equal(delayed.instance.playing, true);
console.log('Passed: existing slider defaults; seven/five/three/two views; small-screen overflow, pause/resume, focus/hover, reduced motion, visibility, delay and malformed configuration');
