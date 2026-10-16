const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
const { execFileSync } = require('node:child_process');
const path = require('node:path');

const harness = path.resolve(__dirname, '../php/header-utility-message-schedule.php');
const fixture = mode => execFileSync('php', [harness, '--fixture', mode], { encoding: 'utf8' });
const start = Date.parse('2026-10-16T13:00:00Z');
const end = Date.parse('2026-10-16T21:00:00Z');

async function freezeClock(page, time) {
	await page.clock.install({ time: time - 1000 });
	await page.clock.pauseAt(time);
}

for (const width of [390, 1366]) {
	test(`cached content switches at both boundaries at ${width}px`, async ({ page }) => {
		await page.setViewportSize({ width, height: 844 });
		await freezeClock(page, start - 1000);
		await page.setContent(fixture('schedule'));
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
		await page.setContent(fixture('schedule'));
		await expect(page.locator('.mrn-site-header__utility-message-item')).toBeVisible({ visible });
	});
}

test('start-only and end-only schedules support open boundaries', async ({ page }) => {
	await freezeClock(page, start);
	await page.setContent(fixture('start-only'));
	await expect(page.getByText('Scheduled message')).toBeVisible();
	await page.clock.setSystemTime(start + 86400000 * 30);
	await page.evaluate(() => window.dispatchEvent(new Event('pageshow')));
	await expect(page.getByText('Scheduled message')).toBeVisible();
	await page.setContent(fixture('end-only'));
	await expect(page.getByText('Scheduled message')).toBeHidden();
});

test('restored tabs and clock changes recheck the schedule', async ({ page }) => {
	await freezeClock(page, start);
	await page.setContent(fixture('schedule'));
	await expect(page.getByText('Scheduled message')).toBeVisible();
	await page.clock.setSystemTime(end);
	await page.evaluate(() => window.dispatchEvent(new Event('pageshow')));
	await expect(page.getByText('Scheduled message')).toBeHidden();
	await page.clock.setSystemTime(start);
	await page.evaluate(() => document.dispatchEvent(new Event('visibilitychange')));
	await expect(page.getByText('Scheduled message')).toBeVisible();
});

test('long schedule delays survive the browser timeout limit', async ({ page }) => {
	await freezeClock(page, start - 86400000 * 30);
	await page.setContent(fixture('schedule'));
	await expect(page.getByText('Scheduled message')).toBeHidden();
	await page.clock.fastForward(2147483647);
	await expect(page.getByText('Scheduled message')).toBeHidden();
	await page.clock.fastForward(86400000 * 30 - 2147483647 + 1);
	await expect(page.getByText('Scheduled message')).toBeVisible();
});

test('manual behavior has no scheduling script and invalid or inactive messages do not render', async ({ page }) => {
	const manual = fixture('manual');
	expect(manual).not.toContain('data-mrn-utility-schedule');
	expect(manual).not.toContain('<script>');
	await page.setContent(manual);
	await expect(page.getByText('Scheduled message')).toBeVisible();
	for (const mode of ['inactive', 'invalid']) {
		await page.setContent(fixture(mode));
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
		await page.setContent(fixture(mode));
		await expect(page.locator('.mrn-site-header__utility-message-item')).toBeVisible({ visible });
	});
}

test('weekly windows cross their boundaries on an open cached page', async ({ page }) => {
	await freezeClock(page, start - 1000);
	await page.setContent(fixture('weekly'));
	await expect(page.getByText('Scheduled message')).toBeHidden();
	await page.clock.runFor(1001);
	await expect(page.getByText('Scheduled message')).toBeVisible();
	await page.clock.fastForward(end - start);
	await expect(page.getByText('Scheduled message')).toBeHidden();
});

test('spring-forward recurrence follows the selected local clock', async ({ page }) => {
	await freezeClock(page, Date.parse('2026-03-08T06:59:59Z'));
	await page.setContent(fixture('dst'));
	await expect(page.getByText('Scheduled message')).toBeVisible();
	await page.clock.runFor(1001);
	await expect(page.getByText('Scheduled message')).toBeHidden();
});
