/* Real-browser contracts for the generated runtime and unchanged effect options. */
const assert = require('node:assert/strict');
const path = require('node:path');
const {chromium} = require('playwright');
const theme = path.resolve(__dirname, '../..');
const markup = `<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Motion runtime fixture</title>
<style>body{margin:0;padding:200px 20px 2000px}.stat{height:40px}.surface{height:200px}</style></head>
<body><h1>Motion fixture</h1><div class="stat" data-mrn-stat-animation="spin-in">1,234+</div>
<div class="stat" data-mrn-stat-animation="spin-in">96%</div>
<section class="surface" data-mrn-motion-effect="active-class" data-mrn-motion-margin="0px"><h2>Active surface</h2></section></body></html>`;
(async () => {
	const browser = await chromium.launch();
	const results = [];
	try {
		for (const mode of ['full', 'core', 'full-then-core', 'core-then-full', 'reduced-core', 'missing']) {
			const context = await browser.newContext({viewport: {width: 390, height: 844}, reducedMotion: mode === 'reduced-core' ? 'reduce' : 'no-preference'});
			const page = await context.newPage();
			const errors = [];
			page.on('pageerror', error => errors.push(error.message));
			try {
				await page.setContent(markup);
				const load = name => page.addScriptTag({path: path.join(theme, 'js/vendor', name)});
				if (['full', 'full-then-core'].includes(mode)) await load('motion.js');
				if (mode.includes('core')) await load('motion-core.js');
				if (mode === 'core-then-full') await load('motion.js');
				await page.addScriptTag({path: path.join(theme, 'js/front-end-effects.js')});
				await page.waitForFunction(() => document.documentElement.classList.contains('mrn-motion-effects-ready'));
				if (mode !== 'missing') {
					await page.waitForFunction(() => [...document.querySelectorAll('[data-mrn-stat-animation]')].every(e => e.dataset.mrnStatAnimated === 'true'));
					if (mode !== 'reduced-core') await page.waitForFunction(() => [...document.querySelectorAll('[data-mrn-stat-animation]')].every(e => e.classList.contains('is-mrn-stat-locked')));
					assert.deepEqual(await page.locator('.stat').allTextContents(), ['1,234+', '96%']);
					assert(await page.locator('.surface').evaluate(e => e.classList.contains('is-mrn-in-view')));
					const api = await page.evaluate(async () => {
						const runtime = window.mrnBaseStack.motion;
						const values = [];
						await new Promise((resolve, reject) => {
							const timeout = setTimeout(() => reject(new Error('animate did not finish')), 2000);
							runtime.animate(5, 9, {duration: .05, onUpdate: v => values.push(v), onComplete: () => {clearTimeout(timeout); resolve();}});
						});
						window.fixtureScrollValues = [];
						window.fixtureStopScroll = runtime.scroll(value => window.fixtureScrollValues.push(value));
						return {last: values.at(-1), fullSelected: !!window.Motion && runtime === window.Motion,
							coreSelected: !window.Motion && runtime === window.mrnBaseStackCoreMotion,
							coreNeverOverwritesFull: !window.Motion || typeof window.Motion.spring === 'function',
							stopIsFunction: typeof window.fixtureStopScroll === 'function'};
					});
					assert.equal(api.last, 9);
					assert(api.fullSelected || api.coreSelected);
					assert(api.coreNeverOverwritesFull && api.stopIsFunction);
					await page.evaluate(() => scrollTo(0, 1800));
					await page.waitForFunction(() => window.fixtureScrollValues.some(v => v > 0));
					await page.waitForFunction(() => !document.querySelector('.surface').classList.contains('is-mrn-in-view'));
					await page.evaluate(() => window.fixtureStopScroll());
					results.push({mode, ...api});
				} else {
					assert.deepEqual(await page.locator('.stat').allTextContents(), ['1,234+', '96%']);
					results.push({mode, readableWithoutRuntime: true});
				}
				assert.deepEqual(errors, []);
			} finally {
				await context.close();
			}
		}
	} finally {
		await browser.close();
	}
	console.log(JSON.stringify({cases: results.length, results}));
})().catch(error => { console.error(error); process.exitCode = 1; });
