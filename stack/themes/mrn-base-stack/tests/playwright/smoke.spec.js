const { test, expect } = require('@playwright/test');

function getConsoleIssueFilter() {
	return (messageText) => {
		const normalized = messageText.toLowerCase();

		return (
			normalized.includes('favicon.ico') ||
			normalized.includes('preloaded using link preload') ||
			normalized.includes('source map')
		);
	};
}

function shouldIgnoreFailedRequest(url, errorText) {
	const normalizedUrl = String(url || '').toLowerCase();
	const normalizedError = String(errorText || '').toLowerCase();

	return (
		(normalizedUrl.includes('mrn-login-logo.png') && normalizedError.includes('aborted'))
	);
}

async function collectPageIssues(page) {
	const consoleMessages = [];
	const pageErrors = [];
	const failedRequests = [];
	const shouldIgnoreConsoleIssue = getConsoleIssueFilter();

	page.on('console', (message) => {
		if (message.type() === 'error' && ! shouldIgnoreConsoleIssue(message.text())) {
			consoleMessages.push(message.text());
		}
	});

	page.on('pageerror', (error) => {
		pageErrors.push(error.message);
	});

	page.on('requestfailed', (request) => {
		const failure = request.failure();
		const errorText = failure && failure.errorText ? failure.errorText : '';

		if (shouldIgnoreFailedRequest(request.url(), errorText)) {
			return;
		}

		failedRequests.push(
			`${request.method()} ${request.url()}${errorText ? ` (${errorText})` : ''}`
		);
	});

	return {
		consoleMessages,
		pageErrors,
		failedRequests,
	};
}

function expectNoPageIssues(issues, contextLabel) {
	expect.soft(issues.consoleMessages, `${contextLabel} console errors`).toEqual([]);
	expect.soft(issues.pageErrors, `${contextLabel} runtime errors`).toEqual([]);
	expect.soft(issues.failedRequests, `${contextLabel} failed requests`).toEqual([]);
}

async function loginToWordPressAdmin(page) {
	await page.goto('/wp-login.php', { waitUntil: 'domcontentloaded' });
	await page.getByLabel(/username or email address/i).fill(process.env.MRN_WP_ADMIN_USER);
	await page.locator('#user_pass').fill(process.env.MRN_WP_ADMIN_PASS);
	await page.getByRole('button', { name: /log in/i }).click();
	await page.waitForLoadState('domcontentloaded');
	await page.waitForTimeout(3000);

	const loginFormCount = await page.locator('#loginform').count();
	const loginErrorCount = await page.locator('#login_error, .message.error').count();
	const hasAdminShell = (await page.locator('body.wp-admin, #wpadminbar').count()) > 0;

	expect.soft(loginErrorCount, 'WordPress login errors').toBe(0);
	expect.soft(loginFormCount > 0 && ! hasAdminShell, 'WordPress login stayed on wp-login.php').toBe(false);
}

async function expectNoLeakedStyleText(page, contextLabel) {
	const bodyText = await page.locator('body').innerText();
	const bodyPreview = bodyText.slice(0, 1500);
	const cssLeakPatterns = [
		/\.[a-z0-9_-]+\s*\{/i,
		/(display|position|padding|margin|grid-template-columns|box-shadow)\s*:\s*[^;]+;/i,
	];
	const hasLeakedText = cssLeakPatterns.some((pattern) => pattern.test(bodyPreview));

	expect.soft(
		hasLeakedText,
		`${contextLabel} leaked CSS-like text near top of page`
	).toBe(false);
}

async function expectStickyToolbarLayout(page, toolbarSelector, contentSelector, contextLabel) {
	const toolbar = page.locator(toolbarSelector).first();
	const content = page.locator(contentSelector).first();
	const toolbarBox = await toolbar.boundingBox();
	const contentBox = await content.boundingBox();

	expect.soft(toolbarBox, `${contextLabel} toolbar bounding box`).not.toBeNull();
	expect.soft(contentBox, `${contextLabel} content bounding box`).not.toBeNull();

	if (!toolbarBox || !contentBox) {
		return;
	}

	expect.soft(toolbarBox.width, `${contextLabel} toolbar width`).toBeGreaterThan(contentBox.width * 0.8);
	expect.soft(toolbarBox.width, `${contextLabel} toolbar not wider than content area`).toBeLessThanOrEqual(contentBox.width + 48);
	expect.soft(toolbarBox.x, `${contextLabel} toolbar aligns with content area`).toBeGreaterThanOrEqual(contentBox.x - 24);
	expect.soft(toolbarBox.y, `${contextLabel} toolbar renders near top of admin shell`).toBeLessThanOrEqual(90);
}

test.describe('MRN stack site smoke QA', () => {
	test('home page loads without browser/runtime errors', async ({ page, baseURL }) => {
		const issues = await collectPageIssues(page);

		await page.goto('/', { waitUntil: 'networkidle' });

		await expect(page).toHaveTitle(/.+/);
		await expect(page.locator('body')).toBeVisible();
		await expect(page.locator('#page')).toBeVisible();
		await expect(page.locator('header.site-header')).toBeVisible();
		await expect(page.locator('main#primary, main.site-main').first()).toBeVisible();

		expect(page.url()).toContain(baseURL || '');
		expectNoPageIssues(issues, 'Home page');
	});

	test('sample page loads without browser/runtime errors', async ({ page }) => {
		const samplePath = process.env.MRN_SAMPLE_PAGE_PATH || '/sample-page/';
		const issues = await collectPageIssues(page);

		await page.goto(samplePath, { waitUntil: 'networkidle' });

		await expect(page.locator('body')).toBeVisible();
		await expect(page.locator('#page')).toBeVisible();
		await expect(page.locator('main#primary, main.site-main').first()).toBeVisible();

		expectNoPageIssues(issues, 'Sample page');
	});

	test.describe('admin smoke coverage', () => {
		test.describe.configure({ mode: 'serial' });

		test('page editor shows builder UI when admin credentials are provided', async ({ page }) => {
			test.skip(
				! process.env.MRN_WP_ADMIN_USER || ! process.env.MRN_WP_ADMIN_PASS || ! process.env.MRN_SAMPLE_PAGE_EDIT_PATH,
				'Set MRN_WP_ADMIN_USER, MRN_WP_ADMIN_PASS, and MRN_SAMPLE_PAGE_EDIT_PATH to run admin builder smoke coverage.'
			);

			const issues = await collectPageIssues(page);

			await loginToWordPressAdmin(page);

			await page.goto(process.env.MRN_SAMPLE_PAGE_EDIT_PATH, { waitUntil: 'networkidle' });

			await expect(page.locator('body.wp-admin')).toBeVisible();
			await expect(page.locator('.acf-field-flexible-content:visible').first()).toBeVisible();
			await expect(page.locator('.acf-field-flexible-content .acf-actions [data-name="add-layout"]:visible').first()).toBeVisible();

			expectNoPageIssues(issues, 'Admin builder editor');
		});

		test('site configurations page renders without leaked CSS text when configured', async ({ page }) => {
			test.skip(
				! process.env.MRN_WP_ADMIN_USER ||
				! process.env.MRN_WP_ADMIN_PASS ||
				! process.env.MRN_SETTINGS_PAGE_PATH,
				'Set MRN_WP_ADMIN_USER, MRN_WP_ADMIN_PASS, and MRN_SETTINGS_PAGE_PATH to run settings-page smoke coverage.'
			);

			const issues = await collectPageIssues(page);
			const toolbarSelector = process.env.MRN_SETTINGS_TOOLBAR_SELECTOR || '.mrn-sticky-save-bar';
			const contentSelector = process.env.MRN_SETTINGS_CONTENT_SELECTOR || '#wpcontent .wrap';

			await loginToWordPressAdmin(page);
			await page.goto(process.env.MRN_SETTINGS_PAGE_PATH, { waitUntil: 'domcontentloaded' });

			await expect(page.locator('body.wp-admin')).toBeVisible();
			await expect(page.locator(contentSelector).first()).toBeVisible();
			await expect(page.locator(toolbarSelector).first()).toBeVisible();

			await expectNoLeakedStyleText(page, 'Site Configurations page');
			await expectStickyToolbarLayout(page, toolbarSelector, contentSelector, 'Site Configurations page');
			expectNoPageIssues(issues, 'Site Configurations page');
		});

		test('editor enhancements page renders a full-width sticky toolbar when configured', async ({ page }) => {
			test.skip(
				! process.env.MRN_WP_ADMIN_USER ||
				! process.env.MRN_WP_ADMIN_PASS ||
				! process.env.MRN_EDITOR_TOOLS_PAGE_PATH,
				'Set MRN_WP_ADMIN_USER, MRN_WP_ADMIN_PASS, and MRN_EDITOR_TOOLS_PAGE_PATH to run editor-tools admin smoke coverage.'
			);

			const issues = await collectPageIssues(page);
			const toolbarSelector = process.env.MRN_EDITOR_TOOLS_TOOLBAR_SELECTOR || '.mrn-sticky-save-bar';
			const contentSelector = process.env.MRN_EDITOR_TOOLS_CONTENT_SELECTOR || '#wpcontent .wrap';

			await loginToWordPressAdmin(page);
			await page.goto(process.env.MRN_EDITOR_TOOLS_PAGE_PATH, { waitUntil: 'domcontentloaded' });

			await expect(page.locator('body.wp-admin')).toBeVisible();
			await expect(page.locator(contentSelector).first()).toBeVisible();
			await expect(page.locator(toolbarSelector).first()).toBeVisible();

			await expectNoLeakedStyleText(page, 'Editor Enhancements page');
			await expectStickyToolbarLayout(page, toolbarSelector, contentSelector, 'Editor Enhancements page');
			expectNoPageIssues(issues, 'Editor Enhancements page');
		});

		test('theme header/footer page renders a full-width sticky toolbar when configured', async ({ page }) => {
			test.skip(
				! process.env.MRN_WP_ADMIN_USER ||
				! process.env.MRN_WP_ADMIN_PASS ||
				! process.env.MRN_THEME_HEADER_FOOTER_PAGE_PATH,
				'Set MRN_WP_ADMIN_USER, MRN_WP_ADMIN_PASS, and MRN_THEME_HEADER_FOOTER_PAGE_PATH to run theme-options admin smoke coverage.'
			);

			const issues = await collectPageIssues(page);
			const toolbarSelector = process.env.MRN_THEME_OPTIONS_TOOLBAR_SELECTOR || '.mrn-sticky-save-bar';
			const contentSelector = process.env.MRN_THEME_OPTIONS_CONTENT_SELECTOR || '#wpcontent .wrap';

			await loginToWordPressAdmin(page);
			await page.goto(process.env.MRN_THEME_HEADER_FOOTER_PAGE_PATH, { waitUntil: 'domcontentloaded' });

			await expect(page.locator('body.wp-admin')).toBeVisible();
			await expect(page.locator(contentSelector).first()).toBeVisible();
			await expect(page.locator(toolbarSelector).first()).toBeVisible();

			await expectNoLeakedStyleText(page, 'Theme Header/Footer page');
			await expectStickyToolbarLayout(page, toolbarSelector, contentSelector, 'Theme Header/Footer page');
			expectNoPageIssues(issues, 'Theme Header/Footer page');
		});

		test('business information page renders a full-width sticky toolbar when configured', async ({ page }) => {
			test.skip(
				! process.env.MRN_WP_ADMIN_USER ||
				! process.env.MRN_WP_ADMIN_PASS ||
				! process.env.MRN_BUSINESS_INFORMATION_PAGE_PATH,
				'Set MRN_WP_ADMIN_USER, MRN_WP_ADMIN_PASS, and MRN_BUSINESS_INFORMATION_PAGE_PATH to run business-information admin smoke coverage.'
			);

			const issues = await collectPageIssues(page);
			const toolbarSelector = process.env.MRN_THEME_OPTIONS_TOOLBAR_SELECTOR || '.mrn-sticky-save-bar';
			const contentSelector = process.env.MRN_THEME_OPTIONS_CONTENT_SELECTOR || '#wpcontent .wrap';

			await loginToWordPressAdmin(page);
			await page.goto(process.env.MRN_BUSINESS_INFORMATION_PAGE_PATH, { waitUntil: 'domcontentloaded' });

			await expect(page.locator('body.wp-admin')).toBeVisible();
			await expect(page.locator(contentSelector).first()).toBeVisible();
			await expect(page.locator(toolbarSelector).first()).toBeVisible();

			await expectNoLeakedStyleText(page, 'Business Information page');
			await expectStickyToolbarLayout(page, toolbarSelector, contentSelector, 'Business Information page');
			expectNoPageIssues(issues, 'Business Information page');
		});
	});
});
