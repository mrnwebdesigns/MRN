/** Read-only local API and stale-page recovery checks; never edits saved options. */
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const { chromium, expect } = require('@playwright/test');
const input = JSON.parse(fs.readFileSync(0, 'utf8'));

(async () => {
	const origin = new URL(input.url).origin;
	assert.match(new URL(origin).hostname, /^[a-z0-9_-]+\.localhost$/);
	const endpoint = origin + '/wp-json/mrn/v1/header-utility-message-clock';
	const browser = await chromium.launch({ headless: true });
	const results = [];
	try {
		const api = await browser.newContext({ ignoreHTTPSErrors: true });
		for (const body of [{}, { token: [] }, { token: 'bad' }, { token: 'a'.repeat(32), refresh: true, version: [] }, { token: 'a'.repeat(32), refresh: true, version: 'bad' }]) {
			assert.equal((await api.request.post(endpoint, { data: body })).status(), 400);
		}
		const challenge = crypto.randomBytes(16).toString('hex');
		const response = await api.request.post(endpoint, { data: { token: challenge, refresh: true, version: '0'.repeat(64) } });
		assert.equal(response.status(), 200);
		assert(response.headers()['cache-control'].includes('no-store'));
		const current = await response.json();
		assert.equal(current.token, challenge);
		assert.match(current.version, /^[a-f0-9]{64}$/);
		assert(Math.abs(current.now - Date.now()) < 5000);
		assert.equal(typeof current.message.enabled, 'boolean');
		assert.equal(typeof current.message.html, 'string');
		if (!current.message.enabled) assert.equal(current.message.html, '');
		const unchanged = await api.request.post(endpoint, { data: { token: challenge, refresh: true, version: current.version } });
		assert.equal((await unchanged.json()).message, null);
		const legacy = await api.request.post(endpoint, { data: { token: challenge } });
		assert.deepEqual(Object.keys(await legacy.json()), ['token', 'version', 'received', 'now']);
		await api.close();
		for (const [width, zone, skew] of [[390, 'Asia/Tokyo', -946080000000], [1366, 'Pacific/Honolulu', 946080000000]]) {
			const context = await browser.newContext({ viewport: { width, height: 900 }, timezoneId: zone, ignoreHTTPSErrors: true });
			await context.addInitScript(offset => {
				const NativeDate = Date;
				window.Date = class extends NativeDate {
					constructor(...args) { super(...(args.length ? args : [NativeDate.now() + offset])); }
					static now() { return NativeDate.now() + offset; }
				};
			}, skew);
			await context.route('**/*', route => new URL(route.request().url()).origin === origin ? route.continue() : route.abort());
			const page = await context.newPage();
			let replies = 0;
			page.on('response', reply => { if (reply.url() === endpoint) replies++; });
			const source = fs.readFileSync(input.fixture_html, 'utf8').replaceAll('https://example.test', origin).replace(/data-mrn-utility-version="[a-f0-9]+"/, 'data-mrn-utility-version="' + '0'.repeat(64) + '"');
			await page.route(origin + '/__mrn-refresh-fixture', route => route.fulfill({ contentType: 'text/html', body: source }));
			await page.goto(origin + '/__mrn-refresh-fixture');
			const item = page.locator('[data-mrn-utility-refresh]');
			await expect(item).toHaveAttribute('data-mrn-utility-clock', 'synced');
			await expect(item).toHaveAttribute('data-mrn-utility-version', current.version);
			assert.equal(await item.innerHTML(), current.message.enabled ? current.message.html : '');
			const note = page.getByRole('textbox', { name: 'Your note' });
			await note.fill('Unfinished local test form');
			await page.evaluate(() => { window.pageSentinel = 'same page'; window.oldMessageNode = document.querySelector('[data-mrn-utility-refresh]').firstElementChild; });
			await page.evaluate(() => window.dispatchEvent(new Event('focus')));
			await expect.poll(() => replies).toBeGreaterThanOrEqual(2);
			await expect(item).toHaveAttribute('data-mrn-utility-clock', 'synced');
			await expect(note).toHaveValue('Unfinished local test form');
			await expect(note).toBeFocused();
			assert(await page.evaluate(() => window.pageSentinel === 'same page' && window.oldMessageNode === document.querySelector('[data-mrn-utility-refresh]').firstElementChild));
			await page.screenshot({ path: path.join(input.output, 'refresh-' + width + '.png') });
			results.push({ width, browser_zone: zone, device_clock_skew_ms: skew, stale_slot_recovered: true, unchanged_dom_preserved: true, form_preserved: true, clock_replies: replies });
			await context.close();
		}
		fs.writeFileSync(path.join(input.output, 'native-refresh-browser.json'), JSON.stringify({ status: 'pass', url: origin, fixture: 'synthetic stale inactive page; real WordPress API and saved message', invalid_api_cases: 5, changed_and_unchanged_api: 'pass', legacy_api: 'pass', saved_options_mutated: false, results }, null, 2) + '\n');
	} finally {
		await browser.close();
	}
})().catch(error => { console.error(error.message); process.exitCode = 1; });
