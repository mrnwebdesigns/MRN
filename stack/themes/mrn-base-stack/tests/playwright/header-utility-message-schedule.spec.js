const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
const { execFileSync } = require('node:child_process');
const path = require('node:path');

const harness = path.resolve(__dirname, '../php/header-utility-message-schedule.php');
const fixture = mode => execFileSync('php', [harness, '--fixture', mode], { encoding: 'utf8' });
const start = Date.parse('2026-10-16T13:00:00Z');
const end = Date.parse('2026-10-16T21:00:00Z');

const serverTimes = new WeakMap();
async function freezeClock(page, time) {
	serverTimes.set(page, time);
	await page.clock.install({ time: time - 1000 });
	await page.clock.pauseAt(time);
}

async function loadFixture(page, mode, options = {}) {
	const html = fixture(mode);
	const version = html.match(/data-mrn-utility-version="([a-f0-9]+)"/)?.[1];
	const state = { time: options.time ?? serverTimes.get(page), anchor: null, requests: 0, fail: false };
	await page.unroute('https://example.test/wp-json/mrn/v1/header-utility-message-clock');
	await page.route('https://example.test/wp-json/mrn/v1/header-utility-message-clock', async route => {
		state.requests++;
		if (options.stall) return;
		if (state.fail || options.fail) return route.abort('failed');
		const monotonic = await page.evaluate(() => performance.now());
		state.anchor ??= monotonic;
		const token = route.request().postDataJSON().token;
		await route.fulfill({
			status: options.status || 200, contentType: 'application/json',
			headers: { 'Cache-Control': 'no-store', ...(options.age ? { Age: '60' } : {}) },
			body: JSON.stringify({ token: options.replay ? 'replayed' : token,
				version: options.stale ? 'old settings' : version,
				received: state.time + monotonic - state.anchor,
				now: options.badTime ? 'invalid' : state.time + monotonic - state.anchor }),
		}).catch(() => {}); // A pageshow recheck may cancel the initial request.
	});
	await page.unroute('https://example.test/fixture');
	await page.route('https://example.test/fixture', route => route.fulfill({ contentType: 'text/html', body: html }));
	await page.goto('https://example.test/fixture');
	if (version && !options.fail && !options.replay && !options.stale && !options.age && !options.badTime && !options.stall && !options.status) {
		await expect(page.locator('[data-mrn-utility-schedule]')).toHaveAttribute('data-mrn-utility-clock', 'synced');
	}
	return state;
}

for (const width of [390, 1366]) {
	test(`cached content switches at both boundaries at ${width}px`, async ({ page }) => {
		await page.setViewportSize({ width, height: 844 });
		await freezeClock(page, start - 1000);
		await loadFixture(page, 'schedule');
		const item = page.locator('.mrn-site-header__utility-message-item');
		await expect(item).toBeHidden();
		await page.clock.runFor(1001);
		await expect(item).toBeVisible();
		await page.getByRole('link', { name: 'Shop now' }).focus();
		await expect(page.getByRole('link', { name: 'Shop now' })).toBeFocused();
		await page.clock.resume();
		const a11y = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
		expect(a11y.violations).toEqual([]);
		await page.clock.fastForward(end - start);
		await expect(item).toBeHidden();
		await page.getByRole('link', { name: 'Next link' }).focus();
		await page.keyboard.press('Shift+Tab');
		await expect(page.getByRole('link', { name: 'Shop now', includeHidden: true })).not.toBeFocused();
	});
}

for (const [time, visible] of [[start - 1, false], [start, true], [end - 1, true], [end, false], [end + 86400000, false]]) {
	test(`the same cached HTML has correct visibility at ${new Date(time).toISOString()}`, async ({ page }) => {
		await freezeClock(page, time);
		await loadFixture(page, 'schedule');
		await expect(page.locator('.mrn-site-header__utility-message-item')).toBeVisible({ visible });
	});
}

test('start-only and end-only schedules support open boundaries', async ({ page }) => {
	await freezeClock(page, start);
	await loadFixture(page, 'start-only');
	await expect(page.getByText('Scheduled message')).toBeVisible();
	await page.clock.setSystemTime(start + 86400000 * 30);
	await page.evaluate(() => window.dispatchEvent(new PageTransitionEvent('pageshow', { persisted: true })));
	await expect(page.getByText('Scheduled message')).toBeVisible();
	await loadFixture(page, 'end-only', { time: start + 86400000 * 30 });
	await expect(page.getByText('Scheduled message')).toBeHidden();
});

test('restored tabs revalidate server UTC instead of trusting device clock changes', async ({ page }) => {
	await freezeClock(page, start);
	const server = await loadFixture(page, 'schedule');
	await expect(page.getByText('Scheduled message')).toBeVisible();
	await page.clock.setSystemTime(end);
	await page.evaluate(() => window.dispatchEvent(new PageTransitionEvent('pageshow', { persisted: true })));
	await expect(page.locator('[data-mrn-utility-schedule]')).toHaveAttribute('data-mrn-utility-clock', 'synced');
	await expect(page.getByText('Scheduled message')).toBeVisible();
	server.time = end;
	await page.evaluate(() => document.dispatchEvent(new Event('visibilitychange')));
	await expect(page.getByText('Scheduled message')).toBeHidden();
});

test('long schedule delays survive the browser timeout limit', async ({ page }) => {
	await freezeClock(page, start - 86400000 * 30);
	await loadFixture(page, 'schedule');
	await expect(page.getByText('Scheduled message')).toBeHidden();
	await page.clock.fastForward(2147483647);
	await expect(page.locator('[data-mrn-utility-schedule]')).toHaveAttribute('data-mrn-utility-clock', 'synced');
	await expect(page.getByText('Scheduled message')).toBeHidden();
	await page.clock.fastForward(86400000 * 30 - 2147483647 + 1);
	await expect(page.locator('[data-mrn-utility-schedule]')).toHaveAttribute('data-mrn-utility-clock', 'synced');
	await expect(page.getByText('Scheduled message')).toBeVisible();
});

test('manual behavior has no scheduling script and invalid or inactive messages do not render', async ({ page }) => {
	const manual = fixture('manual');
	expect(manual).not.toContain('data-mrn-utility-schedule');
	expect(manual).not.toContain('<script>');
	await loadFixture(page, 'manual');
	await expect(page.getByText('Scheduled message')).toBeVisible();
	for (const mode of ['inactive', 'invalid']) {
		await loadFixture(page, mode);
		await expect(page.getByText('Scheduled message')).toHaveCount(0);
	}
});

for (const [mode, time, visible] of [
	['weekly', '2026-10-16T12:59:59Z', false],
	['weekly', '2026-10-16T13:00:00Z', true],
	['weekly', '2026-10-16T21:00:00Z', false],
	['weekly', '2026-10-17T13:00:00Z', false],
	['weekly', '2026-10-23T13:00:00Z', true],
	['weekly', '2026-01-16T13:30:00Z', false],
	['weekly', '2026-01-16T14:00:00Z', true],
	['weekly', '2026-07-17T13:00:00Z', true],
	['bounded-weekly', '2026-10-23T13:00:00Z', false],
	['overnight', '2026-10-17T01:59:59Z', false],
	['overnight', '2026-10-17T02:00:00Z', true],
	['overnight', '2026-10-17T05:59:59Z', true],
	['overnight', '2026-10-17T06:00:00Z', false],
	['overnight', '2026-10-18T05:00:00Z', false],
	['holiday', '2026-07-03T04:00:00Z', true],
	['holiday', '2026-07-04T04:00:00Z', true],
	['holiday', '2026-07-05T04:00:00Z', false],
	['holiday', '2026-11-26T05:00:00Z', true],
	['holiday', '2026-11-27T05:00:00Z', false],
	['holiday', '2026-05-25T04:00:00Z', true],
	['holiday', '2027-12-31T05:00:00Z', true],
	['annual', '2026-12-24T05:00:00Z', true],
	['annual', '2027-12-24T05:00:00Z', true],
	['annual', '2027-12-25T05:00:00Z', false],
	['exact-day', '2026-12-24T05:00:00Z', true],
	['exact-day', '2027-12-24T05:00:00Z', false],
	['fixed-offset', '2026-10-15T18:29:59Z', false],
	['fixed-offset', '2026-10-15T18:30:00Z', true],
	['daily', '2026-10-17T13:00:00Z', true],
	['every-day', '2026-10-17T13:00:00Z', true],
	['dst', '2026-11-01T05:30:00Z', true],
	['dst', '2026-11-01T06:30:00Z', true],
	['dst', '2026-11-01T07:00:00Z', false],
]) {
	test(`${mode} rules in the selected zone at ${time}`, async ({ page }) => {
		await freezeClock(page, Date.parse(time));
		await loadFixture(page, mode);
		await expect(page.locator('.mrn-site-header__utility-message-item')).toBeVisible({ visible });
	});
}

test('weekly windows cross their boundaries on an open cached page', async ({ page }) => {
	await freezeClock(page, start - 1000);
	await loadFixture(page, 'weekly');
	await expect(page.getByText('Scheduled message')).toBeHidden();
	await page.clock.runFor(1001);
	await expect(page.getByText('Scheduled message')).toBeVisible();
	await page.clock.fastForward(end - start);
	await expect(page.getByText('Scheduled message')).toBeHidden();
});

test('spring-forward recurrence follows the selected local clock', async ({ page }) => {
	await freezeClock(page, Date.parse('2026-03-08T06:59:59Z'));
	await loadFixture(page, 'dst');
	await expect(page.getByText('Scheduled message')).toBeVisible();
	await page.clock.runFor(1001);
	await expect(page.getByText('Scheduled message')).toBeHidden();
});

for (const wrongTime of ['2000-01-01T00:00:00Z', '2099-12-31T00:00:00Z']) {
	test(`server UTC controls both boundaries with device clock ${wrongTime}`, async ({ page }) => {
		await freezeClock(page, Date.parse(wrongTime));
		await loadFixture(page, 'schedule', { time: start - 1000 });
		await expect(page.getByText('Scheduled message')).toBeHidden();
		await page.clock.runFor(1001);
		await expect(page.getByText('Scheduled message')).toBeVisible();
		await page.clock.fastForward(end - start);
		await expect(page.getByText('Scheduled message')).toBeHidden();
	});
}

for (const failure of ['fail', 'replay', 'stale', 'age', 'badTime', 'status']) {
	test(`unverified clock stays hidden: ${failure}`, async ({ page }) => {
		await freezeClock(page, start);
		await loadFixture(page, 'schedule', { [failure]: failure === 'status' ? 503 : true });
		await expect(page.locator('[data-mrn-utility-schedule]')).toHaveAttribute('data-mrn-utility-clock', 'unavailable');
		await expect(page.getByText('Scheduled message')).toBeHidden();
	});
}

test('clock request times out and stays hidden', async ({ page }) => {
	await freezeClock(page, start);
	const state = await loadFixture(page, 'schedule', { stall: true });
	await expect.poll(() => state.requests).toBeGreaterThan(0);
	await page.clock.runFor(5001);
	await expect(page.locator('[data-mrn-utility-schedule]')).toHaveAttribute('data-mrn-utility-clock', 'unavailable');
	await expect(page.getByText('Scheduled message')).toBeHidden();
});

test('a failed periodic synchronization hides the message and retries', async ({ page }) => {
	await freezeClock(page, start);
	const state = await loadFixture(page, 'schedule');
	await expect(page.getByText('Scheduled message')).toBeVisible();
	state.fail = true;
	await page.clock.runFor(60001);
	await expect(page.locator('[data-mrn-utility-schedule]')).toHaveAttribute('data-mrn-utility-clock', 'unavailable');
	await expect(page.getByText('Scheduled message')).toBeHidden();
	state.fail = false;
	await page.clock.runFor(15001);
	await expect(page.getByText('Scheduled message')).toBeVisible();
});

test('a device wall-clock jump can only request server revalidation', async ({ page }) => {
	await freezeClock(page, start - 3600000);
	const state = await loadFixture(page, 'schedule');
	await page.clock.setSystemTime(end - 1000);
	await page.clock.runFor(1001);
	await expect.poll(() => state.requests).toBeGreaterThan(1);
	await expect(page.locator('[data-mrn-utility-schedule]')).toHaveAttribute('data-mrn-utility-clock', 'synced');
	await expect(page.getByText('Scheduled message')).toBeHidden();
});

test('slow WordPress processing is excluded from network compensation', async ({ page }) => {
	await freezeClock(page, start - 2000);
	await loadFixture(page, 'schedule');
	const item = page.locator('[data-mrn-utility-schedule]');
	const version = await item.getAttribute('data-mrn-utility-version');
	let pending;
	await page.unroute('https://example.test/wp-json/mrn/v1/header-utility-message-clock');
	await page.route('https://example.test/wp-json/mrn/v1/header-utility-message-clock', route => { pending = route; });
	await page.evaluate(() => window.dispatchEvent(new Event('focus')));
	await expect.poll(() => !!pending).toBe(true);
	await page.clock.runFor(1000);
	await pending.fulfill({ contentType: 'application/json', body: JSON.stringify({
		token: pending.request().postDataJSON().token, version, received: start - 2000, now: start - 1100,
	}) });
	await expect(item).toHaveAttribute('data-mrn-utility-clock', 'synced');
	await page.clock.runFor(700);
	await expect(item).toBeHidden();
	await page.clock.runFor(351);
	await expect(item).toBeVisible();
});

test('initial navigation makes one clock request without cancelling it at pageshow', async ({ page }) => {
	await freezeClock(page, start);
	const state = await loadFixture(page, 'schedule');
	expect(state.requests).toBe(1);
	await expect(page.getByText('Scheduled message')).toBeVisible();
});
