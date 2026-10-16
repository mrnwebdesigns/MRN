/** Native ACF settings save on a disposable, loopback-only WordPress fixture. */
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium } = require('@playwright/test');
const input = JSON.parse(fs.readFileSync(0, 'utf8'));

(async () => {
	assert.match(input.url, /^http:\/\/127\.0\.0\.1:\d+$/);
	const browser = await chromium.launch({ headless: true });
	try {
		const page = await browser.newPage({ timezoneId: 'Asia/Tokyo', viewport: { width: 1366, height: 1000 } });
		await page.goto(input.login_url || input.url + '/wp-login.php', { waitUntil: 'domcontentloaded' });
		await page.waitForLoadState('networkidle');
		await page.locator('#user_login').fill(input.username);
		await page.locator('#user_pass').fill(input.password);
		assert.equal(await page.locator('#user_login').inputValue(), input.username);
		assert.equal(await page.locator('#user_pass').inputValue() === input.password, true);
		assert.equal(await page.locator('#loginform').evaluate(form => form.checkValidity()), true, 'Native login form must be valid.');
		const loginPath = new URL(page.url()).pathname;
		await Promise.all([page.waitForURL(url => url.pathname !== loginPath, { waitUntil: 'domcontentloaded' }), page.locator('#wp-submit').click()]);
		for (let attempt = 0; attempt < 4; attempt++) {
			await page.goto(input.url + '/wp-admin/admin.php?page=mrn-header-utility-message', { waitUntil: 'domcontentloaded' });
			const screen = new URL(page.url()).searchParams.get('page');
			if (screen === 'wpforms-getting-started') continue;
			if (screen === 'seopress-setup') {
				await page.getByRole('link', { name: 'Skip for now', exact: true }).click();
				continue;
			}
			break;
		}
		const field = name => page.locator('[data-name="header_utility_message_' + name + '"]');
		for (const name of ['enabled', 'schedule_enabled']) {
			if (!await field(name).locator('input[type=checkbox]').isChecked()) {
				await field(name).locator('.acf-switch').click();
			}
		}
		await field('text').locator('input[type=text]').fill('Native saved scheduled message');
		await field('schedule_timezone').locator('select').selectOption('America/Los_Angeles');
		await field('schedule_weekdays').locator('input[value="5"]').check();
		await field('schedule_custom_days').locator('textarea').fill('12-24\n2026-12-31');
		for (const [name, value, expected] of [
			['schedule_start', [2026, 9, 16, 9, 0, 0], '2026-10-16 09:00:00'],
			['schedule_end', [2026, 9, 30, 17, 0, 0], '2026-10-30 17:00:00'],
			['schedule_daily_start', [2026, 9, 16, 9, 0, 0], '09:00:00'],
			['schedule_daily_end', [2026, 9, 16, 17, 0, 0], '17:00:00'],
		]) {
			// Use ACF's native picker API so its hidden storage field is updated,
			// just as it is when choosing a date/time in the widget.
			await field(name).locator('input[type=text]').evaluate((element, parts) => {
				window.jQuery(element).datetimepicker('setDate', new Date(...parts));
			}, value);
			assert.equal(await field(name).locator('input[type=hidden]').inputValue(), expected);
		}
		await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), page.getByRole('button', { name: 'Save Settings', exact: true }).click()]);
		assert.equal(await field('schedule_timezone').locator('select').inputValue(), 'America/Los_Angeles');
		assert.equal(await field('schedule_start').locator('input[type=hidden]').inputValue(), '2026-10-16 09:00:00');
		assert.equal(await field('schedule_end').locator('input[type=hidden]').inputValue(), '2026-10-30 17:00:00');
		assert.equal(await field('schedule_daily_start').locator('input[type=hidden]').inputValue(), '09:00:00');
		assert.equal(await field('schedule_daily_end').locator('input[type=hidden]').inputValue(), '17:00:00');
		assert.equal(await field('schedule_custom_days').locator('textarea').inputValue(), '12-24\n2026-12-31');
		await page.screenshot({ path: path.join(input.output, 'native-settings.png'), fullPage: true });
		await field('schedule_daily_end').locator('input[type=text]').evaluate(element => {
			window.jQuery(element).datetimepicker('setDate', null);
		});
		await page.getByRole('button', { name: 'Save Settings', exact: true }).click();
		await page.getByText('Enter both daily times, or leave both blank for the whole day.').waitFor();
		fs.writeFileSync(path.join(input.output, 'native-browser.json'), JSON.stringify({
			status: 'pass', settings_save: true, native_date_time_pickers: true,
			custom_date_newlines_preserved: true, invalid_daily_pair_rejected: true,
			browser_time_zone: 'Asia/Tokyo', selected_time_zone: 'America/Los_Angeles',
		}, null, 2) + '\n');
	} finally {
		await browser.close();
	}
})().catch(error => { console.error(error.message); process.exitCode = 1; });
