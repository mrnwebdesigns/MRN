/* Native editor regression test for a disposable loopback WordPress fixture. */
'use strict';
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { createRequire } = require('node:module');

(async () => {
	const input = JSON.parse(fs.readFileSync(0, 'utf8'));
	const hostname = new URL(input.url).hostname;
	assert(hostname === '127.0.0.1' || (hostname.endsWith('.localhost') && input.temporary_draft === true && input.save_selector === '#save-post'), 'Disposable loopback fixture or explicit Local Hub test draft required');
	const dependencies = createRequire(path.join(input.engine_root, 'package.json'));
	const { chromium, expect } = dependencies('@playwright/test');
	const AxeBuilder = dependencies('@axe-core/playwright').default;
	const browser = await chromium.launch({ headless: true });
	const checks = [];
	const errors = [];
	let page;
	try {
		const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce', ignoreHTTPSErrors: input.url.endsWith('.localhost') });
		if (input.cookies) await context.addCookies(input.cookies);
		await context.route('**/*', route => new URL(route.request().url()).origin === input.url ? route.continue() : route.abort());
		await context.addInitScript(() => localStorage.clear());
		page = await context.newPage();
		page.setDefaultTimeout(30000);
		page.setDefaultNavigationTimeout(90000);
		page.on('pageerror', error => errors.push(error.message));
		if (!input.cookies) {
			await page.goto(input.login_url);
			await page.waitForLoadState('networkidle');
			await page.locator('#user_login').fill(input.username);
			await page.locator('#user_pass').fill(input.password);
			await Promise.all([page.waitForURL(url => url.pathname !== '/wp-login.php'), page.locator('#wp-submit').click()]);
		}
		const editor = `${input.url}/wp-admin/post.php?post=${input.ids.home}&action=edit`;
		for (let attempt = 0; attempt < 4; attempt++) {
			await page.goto(editor);
			if (new URL(page.url()).searchParams.get('page') !== 'wpforms-getting-started') break;
		}
		if (input.temporary_draft) await expect(page.locator('#original_post_status')).toHaveValue('draft');
		const after = () => page.locator('.acf-field[data-name="page_after_content_rows"]').first();
		const rows = () => after().locator(':scope > .acf-input > .acf-flexible-content > .values > .layout');
		await expect(rows()).toHaveCount(2);
		assert.equal(await page.locator('#mrn-builder-layout-allowlist').count(), 0);
		for (const row of await rows().all()) await expect(row).toHaveClass(/-collapsed/);
		const textValues = await rows().locator('textarea').evaluateAll(elements => elements.map(element => element.value));
		assert(textValues.some(value => value.includes('Native saved ACF content.')));
		assert(textValues.some(value => value.includes('Second native saved ACF content.')));
		assert.equal(await rows().locator('.mce-tinymce,.select2-container').count(), 0, 'Closed rows have no expensive widgets');
		checks.push('Metabox removed, saved rows start closed, native values retained without widgets');
		async function save() {
			await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.locator(input.save_selector || '#publish').click()]);
			await expect(page.locator('#message')).toContainText(/updated|saved/i);
		}
		await save();
		assert.deepEqual(await rows().locator('textarea').evaluateAll(elements => elements.map(element => element.value)), textValues);
		checks.push('Saving unopened rows preserves both saved rich-text values');
		const first = rows().first();
		await first.locator('.acf-fc-layout-handle').first().click();
		await expect(first).not.toHaveClass(/-collapsed/);
		const editorField = first.locator('.acf-field[data-type="wysiwyg"]').first();
		await expect(editorField.locator('.mce-tinymce')).toBeVisible();
		const editorId = await editorField.locator('textarea').getAttribute('id');
		await page.evaluate(id => tinymce.get(id).setContent('<p>Editor startup native save verified.</p>'), editorId);
		await save();
		await expect(rows().first().locator('textarea').first()).toHaveValue(/Editor startup native save verified\./);
		checks.push('Opening a row initializes native TinyMCE and edited text survives save/reload');
		const add = after().locator('a[data-name="add-layout"][data-context="bottom-actions"]');
		await add.focus();
		await add.press('Enter');
		const modal = page.getByRole('dialog', { name: 'Add a Section' });
		await expect(modal).toBeVisible();
		await expect(modal.locator('input[type="search"]')).toBeFocused();
		assert(await modal.locator('.mrn-acf-layout-picker__card').count() > 2, 'All eligible choices appear despite the legacy two-layout post allowlist');
		const axe = await new AxeBuilder({ page }).include('.mrn-acf-layout-picker').withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
		assert.deepEqual(axe.violations, [], 'Add Row dialog WCAG A/AA');
		await modal.press('Escape');
		await expect(add).toBeFocused();
		checks.push('Full eligible modal catalog, keyboard entry/escape/focus return and dialog WCAG A/AA');
		await add.click();
		await page.locator('.mrn-acf-layout-picker__card[data-search^="basic_block "]').click();
		await expect(rows()).toHaveCount(3);
		const added = rows().last();
		await expect(added.locator('.acf-field')).not.toHaveCount(0);
		await expect(added.locator('.mce-tinymce')).toBeVisible();
		const newId = await added.locator('textarea').first().getAttribute('id');
		await page.evaluate(id => tinymce.get(id).setContent('<p>Selected layout fields arrived on demand.</p>'), newId);
		await save();
		await expect(rows()).toHaveCount(3);
		await expect(rows().last().locator('textarea').first()).toHaveValue(/Selected layout fields arrived on demand\./);
		checks.push('Selecting a layout fetches real native controls and the new row saves/reloads');
		const denied = await page.evaluate(async () => {
			const response = await fetch(ajaxurl, { method: 'POST', body: new URLSearchParams({ action: 'mrn_base_stack_builder_layout', field_key: 'field_mrn_page_after_content_rows', post_id: document.querySelector('#post_ID').value, nonce: 'invalid', layout: 'basic_block', input_name: 'acf[field_mrn_page_after_content_rows]' }) });
			return response.status;
		});
		assert.equal(denied, 403);
		checks.push('Actual layout endpoint rejects an invalid nonce');
		assert.deepEqual(errors, [], 'No browser exceptions');
		console.log(JSON.stringify({ status: 'passed', checks, browserErrors: errors, axeViolations: axe.violations.length }, null, 2));
	} catch (error) {
		if (page && input.output) await page.screenshot({ path: path.join(input.output, 'editor-startup-failure.png') });
		throw error;
	} finally {
		await browser.close();
	}
})().catch(error => { console.error(error.stack); process.exitCode = 1; });
