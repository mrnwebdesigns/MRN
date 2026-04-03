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

		failedRequests.push(
			`${request.method()} ${request.url()}${failure && failure.errorText ? ` (${failure.errorText})` : ''}`
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

	test('page editor shows builder UI when admin credentials are provided', async ({ page }) => {
		test.skip(
			! process.env.MRN_WP_ADMIN_USER || ! process.env.MRN_WP_ADMIN_PASS || ! process.env.MRN_SAMPLE_PAGE_EDIT_PATH,
			'Set MRN_WP_ADMIN_USER, MRN_WP_ADMIN_PASS, and MRN_SAMPLE_PAGE_EDIT_PATH to run admin builder smoke coverage.'
		);

		const issues = await collectPageIssues(page);

		await page.goto('/wp-login.php', { waitUntil: 'domcontentloaded' });
		await page.getByLabel(/username or email address/i).fill(process.env.MRN_WP_ADMIN_USER);
		await page.locator('#user_pass').fill(process.env.MRN_WP_ADMIN_PASS);
		await page.getByRole('button', { name: /log in/i }).click();
		await page.waitForLoadState('networkidle');

		await page.goto(process.env.MRN_SAMPLE_PAGE_EDIT_PATH, { waitUntil: 'networkidle' });

		await expect(page.locator('body.wp-admin')).toBeVisible();
		await expect(page.locator('.acf-field-flexible-content:visible').first()).toBeVisible();
		await expect(page.locator('.acf-field-flexible-content .acf-actions [data-name="add-layout"]:visible').first()).toBeVisible();

		expectNoPageIssues(issues, 'Admin builder editor');
	});
});
