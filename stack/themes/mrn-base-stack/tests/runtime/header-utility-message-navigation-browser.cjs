/** Verify navigation continuity without trusting the visitor's clock. */
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium } = require('@playwright/test');
const controller = fs.readFileSync(path.join(__dirname, '../../js/header-utility-message-schedule.js'), 'utf8');
const origin = 'http://header-message.localhost';
const endpoint = origin + '/wp-json/mrn/v1/header-utility-message-clock';
const key = 'mrn-header-utility-started:' + endpoint;
const version = 'a'.repeat(64);
const nextVersion = 'b'.repeat(64);
const serverTime = Date.UTC(2026, 9, 20, 12);
const rules = {weekdays: [], holidays: [], custom_days: [], daily_start: null, daily_end: null, timezone: 'America/New_York', offset: null};

function html(config) {
	const attributes = `hidden data-mrn-utility-refresh data-mrn-utility-enabled="1" ${config.scheduled === false ? '' : 'data-mrn-utility-schedule'} data-mrn-utility-clock-url="${endpoint}" data-mrn-utility-version="${version}" data-mrn-utility-start="${config.start ?? ''}" data-mrn-utility-end="${config.end ?? ''}" data-mrn-utility-rules='${JSON.stringify(config.rules || rules)}'`;
	return `<!doctype html><html><head><style>[hidden]{display:none!important}</style></head><body><div ${attributes}><a href="/two">Message</a></div><script>${controller}</script><script>window.initialHidden=document.querySelector('[data-mrn-utility-refresh]').hidden;</script><input aria-label="Unfinished form"></body></html>`;
}

(async () => {
	const browser = await chromium.launch({headless: true});
	const results = [];
	try {
		for (const skew of [-946080000000, 946080000000]) {
			const config = {start: serverTime / 1000 - 3600, end: null};
			const context = await browser.newContext({viewport: {width: skew < 0 ? 390 : 1366, height: 900}});
			await context.addInitScript(offset => {
				const NativeDate = Date;
				window.Date = class extends NativeDate {
					constructor(...args) { super(...(args.length ? args : [NativeDate.now() + offset])); }
					static now() { return NativeDate.now() + offset; }
				};
			}, skew);
			let pending = [];
			await context.route(origin + '/**', async route => {
				if (route.request().url() !== endpoint) return route.fulfill({contentType: 'text/html', body: html(config)});
				const request = route.request().postDataJSON();
				await new Promise(resolve => pending.push(async change => {
					try { await route.fulfill({contentType: 'application/json', headers: {'Cache-Control': 'no-store'}, body: JSON.stringify({token: request.token, version, now: serverTime, received: serverTime, message: null, ...change})}); } finally { resolve(); }
				}));
			});
			const page = await context.newPage();
			async function reply(change) {
				await page.waitForFunction(() => document.querySelector('[data-mrn-utility-refresh]').dataset.mrnUtilityClock === 'waiting');
				for (let i = 0; !pending.length && i < 100; i++) await page.waitForTimeout(10);
				assert(pending.length, 'Clock request must still run in the background');
				await pending.shift()(change);
				await page.waitForFunction(() => document.querySelector('[data-mrn-utility-refresh]').dataset.mrnUtilityClock === 'synced');
			}
			await page.goto(origin + '/one', {waitUntil: 'domcontentloaded'});
			assert.equal(await page.evaluate(() => window.initialHidden), true, 'Cold visit must verify the scheduled start');
			await reply();
			assert.equal(await page.locator('[data-mrn-utility-refresh]').evaluate(e => e.hidden), false);
			for (const navigation of ['link', 'reload']) {
				if (navigation === 'link') await page.goto(origin + '/two', {waitUntil: 'domcontentloaded'});
				else await page.reload({waitUntil: 'domcontentloaded'});
				assert.equal(await page.evaluate(() => window.initialHidden), false, navigation + ' must show the confirmed open-ended message immediately');
				await reply();
			}
			await page.getByRole('textbox').fill('Keep this unfinished form');
			await page.evaluate(() => window.dispatchEvent(new Event('focus')));
			assert.equal(await page.locator('[data-mrn-utility-refresh]').evaluate(e => e.hidden), false);
			await reply();
			assert.equal(await page.getByRole('textbox').inputValue(), 'Keep this unfinished form');
			await page.evaluate(() => window.dispatchEvent(new Event('focus')));
			await reply({version: nextVersion, message: {enabled: false, scheduled: false, html: '', styles: [], start: null, end: null, rules}});
			assert.equal(await page.locator('[data-mrn-utility-refresh]').evaluate(e => e.hidden), true, 'Fresh deactivation must clear the carried state');
			assert.equal(await page.evaluate(key => sessionStorage.getItem(key), key), null);
			await context.close();
			results.push({device_clock_skew_ms: skew, cold_visit: 'verified', navigation: 'steady', reload: 'steady', focus: 'steady', form_preserved: true, deactivation: 'applied'});
		}
		for (const [name, config, saved] of [
			['future-start', {start: serverTime / 1000 + 3600}, {version, serverTime}],
			['scheduled-end', {start: serverTime / 1000 - 3600, end: serverTime / 1000 + 3600}, {version, serverTime}],
			['recurring-hours', {start: serverTime / 1000 - 3600, rules: {...rules, daily_start: 0, daily_end: 86399}}, {version, serverTime}],
			['different-version', {start: serverTime / 1000 - 3600}, {version: nextVersion, serverTime}],
			['invalid-storage', {start: serverTime / 1000 - 3600}, 'invalid json'],
			['manual-active', {scheduled: false}, null],
		]) {
			const context = await browser.newContext();
			await context.addInitScript(({key, saved}) => {if (saved !== null) sessionStorage.setItem(key, typeof saved === 'string' ? saved : JSON.stringify(saved));}, {key, saved});
			await context.route(origin + '/**', route => route.request().url() === endpoint ? route.abort() : route.fulfill({contentType: 'text/html', body: html(config)}));
			const page = await context.newPage();
			await page.goto(origin + '/one', {waitUntil: 'domcontentloaded'});
			assert.equal(await page.evaluate(() => window.initialHidden), name !== 'manual-active', name);
			await context.close();
			results.push({name, initial_state: name === 'manual-active' ? 'visible without clock dependency' : 'waits for fresh server time'});
		}
		const report = {status: 'pass', cases: results};
		if (process.env.MRN_NAVIGATION_TEST_OUTPUT) fs.writeFileSync(process.env.MRN_NAVIGATION_TEST_OUTPUT, JSON.stringify(report, null, 2) + '\n');
		console.log(JSON.stringify(report));
	} finally { await browser.close(); }
})().catch(error => {console.error(error.stack);process.exitCode = 1;});
