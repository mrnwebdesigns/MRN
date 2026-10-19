/** Verify real server-clock boundaries on an explicitly prepared local test site. */
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium, expect } = require('@playwright/test');
const input = JSON.parse(fs.readFileSync(0, 'utf8'));

(async () => {
	const origin = new URL(input.url);
	assert.match(origin.hostname, /^[a-z0-9_-]+\.localhost$/);
	assert(input.end > input.start && input.start * 1000 > Date.now(), 'Prepare a future, short local test window.');
	const browser = await chromium.launch({ headless: true });
	const cases = [];
	try {
		for (const [width, skew, zone] of [[390, -946080000000, 'Asia/Tokyo'], [1366, 946080000000, 'Pacific/Honolulu']]) {
			const context = await browser.newContext({ viewport: { width, height: 900 }, timezoneId: zone });
			await context.route('**/*', route => new URL(route.request().url()).origin === origin.origin ? route.continue() : route.abort());
			await context.addInitScript(offset => {
				const NativeDate = Date;
				window.Date = class extends NativeDate {
					constructor(...args) { super(...(args.length ? args : [NativeDate.now() + offset])); }
					static now() { return NativeDate.now() + offset; }
				};
				window.__mrnClockTransitions = [];
				document.addEventListener('DOMContentLoaded', () => {
					const item = document.querySelector('[data-mrn-utility-schedule]');
					if (!item) return;
					const record = () => window.__mrnClockTransitions.push({ hidden: item.hidden, observed: NativeDate.now(), clock: item.dataset.mrnUtilityClock });
					new MutationObserver(record).observe(item, { attributes: true, attributeFilter: ['hidden'] });
					record();
				});
			}, skew);
			const page = await context.newPage();
			if (input.fixture_html) {
				await page.route(origin.origin + '/__mrn-clock-fixture', route => route.fulfill({ contentType: 'text/html', body: fs.readFileSync(input.fixture_html, 'utf8') }));
			}
			const replies = [];
			page.on('response', response => {
				if (response.url().includes('/header-utility-message-clock')) {
					response.json().then(data => replies.push({ status: response.status(), headers: response.headers(), data })).catch(() => {});
				}
			});
			await page.goto(input.fixture_html ? origin.origin + '/__mrn-clock-fixture' : origin.origin + '/?mrn-clock-test=' + input.start, { waitUntil: 'domcontentloaded' });
			const item = page.locator('[data-mrn-utility-schedule]');
			await expect(item).toHaveAttribute('data-mrn-utility-clock', 'synced');
			await expect(item).toBeHidden();
			cases.push({ page, width, skew, zone, replies, item });
		}
		await Promise.all(cases.map(async test => {
			await expect(test.item).toBeVisible({ timeout: 40000 });
			await test.page.screenshot({ path: path.join(input.output, 'server-clock-' + test.width + '.png') });
			await expect(test.item).toBeHidden({ timeout: 40000 });
		}));
		const results = [];
		for (const test of cases) {
			const transitions = await test.page.evaluate(() => window.__mrnClockTransitions);
			const on = transitions.find(entry => !entry.hidden);
			const off = transitions.find(entry => entry.hidden && on && entry.observed > on.observed);
			assert(on && off, 'Both boundaries must occur on the same cached document.');
			assert(Math.abs(on.observed - input.start * 1000) < 2000, 'On boundary must follow real server time within browser/network scheduling tolerance.');
			assert(Math.abs(off.observed - input.end * 1000) < 2000, 'Off boundary must follow real server time within browser/network scheduling tolerance.');
			assert(test.replies.length > 0 && test.replies.every(reply => reply.headers['cache-control'].includes('no-store')));
			results.push({ width: test.width, device_clock_skew_ms: test.skew, browser_zone: test.zone, start_error_ms: on.observed - input.start * 1000, end_error_ms: off.observed - input.end * 1000, transitions, clock_replies: test.replies.length });
		}
		fs.writeFileSync(path.join(input.output, 'native-clock-browser.json'), JSON.stringify({ status: 'pass', url: origin.origin, fixture: input.fixture_html ? 'synthetic header, real server clock' : 'site homepage', results }, null, 2) + '\n');
	} finally {
		await browser.close();
	}
})().catch(error => { console.error(error.message); process.exitCode = 1; });
