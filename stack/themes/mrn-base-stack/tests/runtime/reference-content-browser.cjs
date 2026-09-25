/* Local integration companion to reference-content.php; no credentials are logged. */
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium, expect } = require('@playwright/test');

const fixture = JSON.parse(fs.readFileSync(process.env.MRN_REFERENCE_FIXTURE, 'utf8'));
const auth = JSON.parse(fs.readFileSync(process.env.MRN_REFERENCE_AUTH, 'utf8'));
const evidence = process.env.MRN_REFERENCE_EVIDENCE;
assert(new URL(fixture.home).hostname.endsWith('.localhost'), 'Local fixture only');
assert(evidence, 'Set MRN_REFERENCE_EVIDENCE');

(async () => {
	const browser = await chromium.launch();
	const results = [];
	const errors = [];
	try {
		const context = await browser.newContext({ ignoreHTTPSErrors: true, reducedMotion: 'reduce', viewport: { width: 1440, height: 1100 } });
		await context.addCookies(auth.cookies);
		const page = await context.newPage();
		page.setDefaultTimeout(30000);
		page.setDefaultNavigationTimeout(120000);
		page.on('pageerror', error => errors.push({ name: error.name, message: error.message, stack: error.stack }));
		page.on('dialog', dialog => dialog.accept());
		const rowSelector = '.layout[data-layout="content_lists"]:not(.acf-clone)';
		const content = () => page.locator('.acf-field[data-name="page_content_rows"]').first();
		const mainRow = () => content().locator(rowSelector).filter({ has: page.locator('.acf-field[data-key="field_mrn_content_lists_post_type"]') }).first();
		const field = (row, name) => row.locator('.acf-field[data-name="' + name + '"]').first();
		const select = (row, name) => field(row, name).locator('select').first();
		const toggle = row => field(row, 'link_items').locator('input[type="checkbox"]').first();
		async function loadEditor(navigate = true) {
			if (navigate) await page.goto(fixture.edit_url, { waitUntil: 'domcontentloaded' });
			await expect(page.locator('#wpadminbar')).toBeVisible();
			await expect(page.locator('html')).not.toHaveClass(/mrn-editor-loading-indicator-live/, { timeout: 120000 });
			// Start a real editor interaction before manipulating the flexible rows.
			// This cancels the existing idle-collapse timer, including after a save.
			await page.locator('input[name="post_title"]').click();
			await expect(select(mainRow(), 'filter_taxonomy')).toHaveValue('category');
		}
		async function openRow(row) {
			const handle = row.locator('.acf-fc-layout-handle').first();
			const configTab = row.locator('> .acf-fields > .acf-tab-wrap .acf-tab-button').filter({ hasText: 'Configs' }).first();
			if (!(await configTab.isVisible())) await handle.click();
			if (await configTab.count()) {
				await expect(configTab).toBeVisible();
				await configTab.click();
			}
		}
		async function save() {
			await Promise.all([
				page.waitForNavigation({ timeout: 120000, waitUntil: 'domcontentloaded' }),
				page.locator('#publish').click({ force: true }),
			]);
			await loadEditor(false);
		}
		async function showLinks(row) {
			await openRow(row);
			const target = field(row, 'link_items');
			if (!(await target.isVisible())) {
				const accordion = target.locator('xpath=ancestor::*[contains(concat(" ", normalize-space(@class), " "), " acf-accordion ")][1]');
				await accordion.locator('> .acf-accordion-title').click();
			}
			await expect(target).toBeVisible();
			await expect(target.locator('.mrn-content-list-link-note')).toBeVisible();
			// Wait for ACF's accordion animation before capturing the explanation.
			await expect.poll(() => target.evaluate(element => {
				const note = element.querySelector('.mrn-content-list-link-note').getBoundingClientRect();
				const panel = element.closest('.acf-accordion-content').getBoundingClientRect();
				return note.bottom <= panel.bottom;
			})).toBe(true);
		}
		await loadEditor();
		await openRow(mainRow());
		const taxonomy = field(mainRow(), 'filter_taxonomy');
		await expect(toggle(mainRow())).toBeEnabled();
		await expect(toggle(mainRow())).toBeChecked();
		await expect(field(mainRow(), 'list_post_type').locator('.mrn-content-list-source-note')).toContainText('Content Only:');
		await expect(field(mainRow(), 'link_items').locator('.mrn-content-list-link-note')).toContainText('On: items link to their supported file');
		assert.deepEqual(await select(mainRow(), 'filter_taxonomy').locator('option').evaluateAll(options => options.map(o => o.value)), ['', 'category', 'post_tag']);
		// Select through the enhanced UI when present, proving Categories is clickable.
		const enhanced = taxonomy.locator('.select2-selection');
		if (await enhanced.count()) {
			await enhanced.click();
			await page.getByRole('option', { name: 'Categories', exact: true }).click();
		} else {
			await select(mainRow(), 'filter_taxonomy').selectOption('category');
		}
		await taxonomy.scrollIntoViewIfNeeded();
		await page.screenshot({ path: path.join(evidence, 'editor-categories-links-on.png') });
		results.push('Authenticated Categories selection and enabled Resource link control');
		// A source switch preserves compatible taxonomies and does not rewrite slugs/mode.
		await select(mainRow(), 'filter_taxonomy').selectOption('post_tag', { force: true });
		await select(mainRow(), 'list_post_type').selectOption('post', { force: true });
		await expect(select(mainRow(), 'filter_taxonomy')).toHaveValue('post_tag');
		await expect(toggle(mainRow())).toBeEnabled();
		await expect(field(mainRow(), 'list_post_type').locator('.mrn-content-list-source-note')).toContainText('Public content:');
		await select(mainRow(), 'list_post_type').selectOption('location', { force: true });
		await expect(toggle(mainRow())).toBeDisabled();
		await expect(field(mainRow(), 'link_items').locator('.mrn-content-list-link-note')).toContainText('Unavailable:');
		await expect(select(mainRow(), 'filter_taxonomy')).toHaveValue('');
		await expect(field(mainRow(), 'filter_taxonomy').locator('.mrn-content-list-taxonomy-note')).toContainText('no eligible filter taxonomies');
		await select(mainRow(), 'list_post_type').selectOption('resource', { force: true });
		await expect(select(mainRow(), 'filter_taxonomy')).toHaveValue('category');
		await expect(toggle(mainRow())).toBeEnabled();
		await expect(field(mainRow(), 'filter_term_slugs').locator('input[type="text"]')).toHaveValue('mrn-reference-qa-partner,mrn-reference-qa-customer');
		await expect(select(mainRow(), 'filter_match')).toHaveValue('any');
		results.push('Resource/public/Content Only source changes, compatible taxonomy retention and clear empty state');
		const afterRow = () => page.locator('.acf-field[data-name="page_after_content_rows"]').first().locator(rowSelector).first();
		const nestedRow = () => content().locator('.layout[data-layout="tabbed_layout"]:not(.acf-clone)').first().locator(rowSelector).first();
		for (const [name, row] of [['After Content', afterRow()], ['nested tab', nestedRow()]]) {
			await expect(select(row, 'list_post_type')).toHaveValue('resource');
			await expect(select(row, 'filter_taxonomy')).toHaveValue('category');
			await expect(toggle(row)).toBeEnabled();
			await expect(toggle(row)).not.toBeChecked();
			await expect(field(row, 'link_items').locator('.mrn-content-list-link-note')).toContainText('Off:');
			const descriptionId = await field(row, 'link_items').locator('.mrn-content-list-link-note').getAttribute('id');
			assert((await toggle(row).getAttribute('aria-describedby')).split(/\s+/).includes(descriptionId), name + ' toggle describes its own state');
			results.push(name + ' cloned keys load retained Resource settings with links off');
		}
		await showLinks(mainRow());
		await field(mainRow(), 'link_items').scrollIntoViewIfNeeded();
		await page.screenshot({ path: path.join(evidence, 'editor-resource-links-on.png') });
		await field(mainRow(), 'link_items').locator('.acf-switch').click();
		await expect(toggle(mainRow())).not.toBeChecked();
		await expect(field(mainRow(), 'link_items').locator('.mrn-content-list-link-note')).toContainText('Off:');
		await save();
		await expect(toggle(mainRow())).not.toBeChecked();
		await expect(field(mainRow(), 'link_items').locator('.mrn-content-list-link-note')).toContainText('Off:');
		await expect(toggle(afterRow())).not.toBeChecked();
		await expect(toggle(nestedRow())).not.toBeChecked();
		await showLinks(mainRow());
		await field(mainRow(), 'link_items').scrollIntoViewIfNeeded();
		await page.screenshot({ path: path.join(evidence, 'editor-links-off-reloaded.png') });
		results.push('Links off persists after actual editor save and reload in original and cloned rows');
		const publicContext = await browser.newContext({ ignoreHTTPSErrors: true, reducedMotion: 'reduce', viewport: { width: 1440, height: 1000 } });
		const publicPage = await publicContext.newPage();
		publicPage.setDefaultNavigationTimeout(120000);
		await publicPage.goto(fixture.url, { waitUntil: 'domcontentloaded' });
		const listing = () => publicPage.locator('.mrn-reference-qa-resources');
		await expect(listing()).toContainText('MRN Reference QA match');
		await expect(listing()).not.toContainText('MRN Reference QA nonmatch');
		await expect(listing().locator('a')).toHaveCount(0);
		results.push('Saved links-off frontend filters matching content and renders no item anchors');
		await field(mainRow(), 'link_items').locator('.acf-switch').click();
		await save();
		await expect(toggle(mainRow())).toBeChecked();
		await publicPage.reload({ waitUntil: 'domcontentloaded' });
		const pdf = listing().getByRole('link', { name: 'MRN Reference QA match', exact: true });
		await expect(pdf).toHaveAttribute('href', /mrn-reference-qa.*\.pdf$/);
		await expect(pdf).toBeVisible();
		await expect(pdf).toHaveAttribute('target', '_blank');
		await expect(pdf).toHaveAttribute('rel', /noopener/);
		await expect(listing().getByRole('link', { name: 'MRN Reference QA missing', exact: true })).toHaveCount(0);
		const response = await context.request.get(await pdf.getAttribute('href'));
		assert.equal(response.status(), 200);
		assert.match(response.headers()['content-type'], /application\/pdf/);
		await expect(publicPage.locator('.mrn-reference-qa-after a, .mrn-reference-qa-nested a, .mrn-reference-qa-private a')).toHaveCount(0);
		const rejectCookies = publicPage.locator('.stcm-reject-all').first();
		if (await rejectCookies.isVisible()) await rejectCookies.click();
		for (const viewport of [{ width: 1440, height: 1000 }, { width: 834, height: 1112 }, { width: 390, height: 844 }]) {
			await publicPage.setViewportSize(viewport);
			await listing().scrollIntoViewIfNeeded();
			await publicPage.screenshot({ path: path.join(evidence, 'frontend-' + viewport.width + '.png'), animations: 'disabled' });
		}
		results.push('Saved links-on frontend uses reachable PDF, preserves PDF attributes, and never links missing files or Content Only profiles');
		fs.writeFileSync(path.join(evidence, 'browser-results.json'), JSON.stringify({ passed: results, pageErrors: errors }, null, 2));
		assert.deepEqual(errors, [], 'Authenticated editor has no uncaught JavaScript errors');
		console.log(results.map(r => 'PASS: ' + r).join('\n'));
	} finally {
		await browser.close();
	}
})().catch(error => { console.error(error.message); process.exitCode = 1; });
