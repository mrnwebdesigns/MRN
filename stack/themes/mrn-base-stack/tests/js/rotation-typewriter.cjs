/* Opt-in Type and Erase rotation: timing, pause, visibility, reduced motion and the unchanged swap default. */
const fs = require('node:fs'), path = require('node:path'), assert = require('node:assert/strict');
const { chromium } = require(process.env.MRN_PLAYWRIGHT_MODULE || 'playwright'), axe = require(process.env.MRN_AXE_MODULE || 'axe-core');
const root = path.resolve(__dirname, '../..'), css = fs.readFileSync(root + '/css/presentation.css', 'utf8'), controller = fs.readFileSync(root + '/js/presentation.js', 'utf8');
const group = (attrs, slot) => `<span data-mrn-rotation-group data-interval="6000"${attrs} data-pause-label="Pause rotating text" data-resume-label="Resume rotating text">Like the ${slot} you see?</span>`;
const typed = `<span class="mrn-rotate mrn-rotate--flow" data-mrn-phrases='["work","solutions"]'><span class="screen-reader-text">work</span><span class="mrn-rotate__visual" aria-hidden="true">work</span></span>`;
const swapped = `<span class="mrn-rotate" data-mrn-phrases='["one","two"]'><span class="screen-reader-text">one</span><span class="mrn-rotate__visual" aria-hidden="true">one</span><span class="mrn-rotate__size" aria-hidden="true">one</span><span class="mrn-rotate__size" aria-hidden="true">two</span></span>`;
const shell = `<!doctype html><html lang="en"><head><title>Rotation</title><style>${css}</style><style>body{margin:0;font:16px/1.5 Arial,sans-serif}.screen-reader-text{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)}</style></head><body><main>
<section class="mrn-reusable-block mrn-reusable-block--cta"><h2 id="typed">${group(' data-effect="typewriter" data-control="icon"', typed)}</h2></section>
<section class="mrn-reusable-block mrn-reusable-block--cta"><h2 id="swapped">${group('', swapped)}</h2></section></main></body></html>`;
// A manual clock: the controller's timers run only when the test advances time.
const clock = () => {
	let now = 0, next = 1; const queue = new Map(); const real = { setTimeout: window.setTimeout, clearTimeout: window.clearTimeout, setInterval: window.setInterval, clearInterval: window.clearInterval };
	window.__restore = () => { queue.clear(); Object.assign(window, real); };
	window.setTimeout = (fn, ms) => { const id = next++; queue.set(id, { at: now + (ms || 0), fn }); return id; };
	window.clearTimeout = (id) => queue.delete(id);
	window.setInterval = (fn, ms) => { const id = next++; const run = () => { queue.set(id, { at: now + ms, fn: () => { run(); fn(); } }); }; run(); return id; };
	window.clearInterval = (id) => queue.delete(id);
	window.__tick = (ms) => { const end = now + ms; for (;;) { let id = null, due = null; queue.forEach((t, k) => { if (t.at <= end && (!due || t.at < due.at || (t.at === due.at && k < id))) { id = k; due = t; } }); if (!due) break; queue.delete(id); now = due.at; due.fn(); } now = end; };
	window.__hidden = false; Object.defineProperty(document, 'hidden', { configurable: true, get: () => window.__hidden });
};
(async () => {
	const browser = await chromium.launch({ headless: true }); const checks = []; const expect = (ok, label) => { assert(ok, label); checks.push(label); };
	try {
		const page = await browser.newPage({ viewport: { width: 1200, height: 900 } });
		await page.route('**/*', (route) => route.abort()); await page.setContent(shell); await page.evaluate(clock); await page.addScriptTag({ content: controller });
		const text = (id) => page.$eval('#' + id + ' .mrn-rotate__visual', (e) => e.textContent);
		const tick = (ms) => page.evaluate((m) => window.__tick(m), ms);
		await page.waitForFunction(() => document.querySelector('#typed .mrn-rotate__visual').textContent === '');
		expect(true, 'Coming into view clears the first phrase and starts typing');
		const typing = []; for (let i = 0; i < 4; i++) { await tick(140); typing.push(await text('typed')); }
		expect(typing.join('|') === 'w|wo|wor|work', 'Types one letter every 140ms: ' + typing.join('|'));
		await tick(5999); expect(await text('typed') === 'work', 'Holds the whole phrase for the Phrase Interval');
		const erasing = []; await tick(1); for (let i = 0; i < 4; i++) { await tick(80); erasing.push(await text('typed')); }
		expect(erasing.join('|') === 'wor|wo|w|', 'Erases one letter every 80ms: ' + erasing.join('|'));
		await tick(80 + 299); expect(await text('typed') === '', 'Waits 300ms before the next phrase');
		await tick(1 + 140); expect(await text('typed') === 's', 'Types the next phrase from its first letter');
		const button = page.locator('#typed').locator('xpath=following-sibling::button');
		const box = await button.boundingBox(); const name = await button.textContent();
		expect(await button.evaluate((b) => b.classList.contains('mrn-rotation-toggle--icon')) && box.width === 28 && box.height === 28 && name === 'Pause rotating text', 'Icon control is 28px and keeps its accessible name');
		await button.click(); expect(await text('typed') === 'solutions' && await button.getAttribute('aria-pressed') === 'true', 'Pausing shows the whole current phrase');
		await tick(30000); expect(await text('typed') === 'solutions', 'A paused heading does not move');
		await button.click(); await tick(5999); expect(await text('typed') === 'solutions', 'Resuming holds the phrase before erasing');
		await tick(81); expect(await text('typed') === 'solution', 'Resuming then erases as before');
		await page.evaluate(() => { window.__hidden = true; document.dispatchEvent(new Event('visibilitychange')); });
		expect(await text('typed') === 'solution' || await text('typed') === 'solutions', 'Leaving the page stops typing');
		await tick(20000); const hiddenText = await text('typed'); expect(hiddenText === 'solutions', 'A hidden page shows the whole phrase and does not move: ' + hiddenText);
		await page.evaluate(() => { window.__hidden = false; document.dispatchEvent(new Event('visibilitychange')); });
		expect(await text('typed') === '', 'Returning starts again from the first phrase');
		await tick(560); expect(await text('typed') === 'work', 'and types it again');
		const swaps = []; for (let i = 0; i < 12; i++) { await tick(1000); swaps.push(await text('swapped')); }
		const changes = swaps.filter((value, i) => i && value !== swaps[i - 1]).length;
		expect(swaps.every((value) => value === 'one' || value === 'two') && changes === 2, 'Unset effect keeps the existing whole-phrase swap every interval: ' + swaps.join('|'));
		expect(await page.$$eval('#typed .mrn-rotate__size', (e) => e.length) === 0 && await page.$eval('#typed .mrn-rotate', (e) => getComputedStyle(e).display) === 'inline-block', 'A fitted phrase sits inline without reserved width');
		await page.evaluate(() => window.__restore());
		await page.addScriptTag({ content: axe.source });
		const result = await page.evaluate(() => axe.run(document.querySelector('main'), { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'] } }));
		expect(result.violations.length === 0, 'Rotation markup and controls pass WCAG A/AA: ' + result.violations.map((v) => v.id).join(','));
		await page.emulateMedia({ reducedMotion: 'reduce' });
		await page.waitForFunction(() => [...document.querySelectorAll('.mrn-rotation-toggle')].every((b) => b.hidden));
		expect(await text('typed') === 'work' && await text('swapped') === 'one', 'Reduced motion shows each first phrase and hides the controls');
		await page.waitForTimeout(1000); expect(await text('typed') === 'work', 'Reduced motion never types');
		console.log(JSON.stringify({ result: 'passed', assertions: checks.length, checks }, null, 2));
	} finally { await browser.close(); }
})().catch((error) => { console.error(error); process.exitCode = 1; });
