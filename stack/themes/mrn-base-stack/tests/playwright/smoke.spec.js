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

async function expectAnyVisible(page, selectorCsv, contextLabel) {
	const selectors = String(selectorCsv || '')
		.split(',')
		.map((selector) => selector.trim())
		.filter(Boolean);

	for (const selector of selectors) {
		const locator = page.locator(selector).first();

		if ((await locator.count()) > 0) {
			await expect(locator, `${contextLabel} (${selector})`).toBeVisible();
			return selector;
		}
	}

	throw new Error(`${contextLabel} not found for selectors: ${selectors.join(', ')}`);
}

function getMotionTargetCases() {
	const rawCases = process.env.MRN_MOTION_TARGET_CASES;

	if (! rawCases) {
		return [];
	}

	try {
		const parsedCases = JSON.parse(rawCases);

		if (! Array.isArray(parsedCases)) {
			return [];
		}

		return parsedCases
			.filter((testCase) => testCase && typeof testCase === 'object')
			.map((testCase) => ({
				label: typeof testCase.label === 'string' && testCase.label.trim()
					? testCase.label.trim()
					: 'configured motion target case',
				path: typeof testCase.path === 'string' && testCase.path.trim()
					? testCase.path.trim()
					: (process.env.MRN_SAMPLE_PAGE_PATH || '/sample-page/'),
				sectionSelector: typeof testCase.sectionSelector === 'string' ? testCase.sectionSelector.trim() : '',
				targetSelector: typeof testCase.targetSelector === 'string' ? testCase.targetSelector.trim() : '',
				nonTargetSelector: typeof testCase.nonTargetSelector === 'string' ? testCase.nonTargetSelector.trim() : '',
				expectedTarget: typeof testCase.expectedTarget === 'string' ? testCase.expectedTarget.trim() : '',
				activeClass: typeof testCase.activeClass === 'string' && testCase.activeClass.trim()
					? testCase.activeClass.trim()
					: 'is-mrn-in-view',
			}))
			.filter((testCase) => testCase.sectionSelector && testCase.targetSelector);
	} catch (error) {
		console.warn(`Unable to parse MRN_MOTION_TARGET_CASES: ${error.message}`);
		return [];
	}
}

async function expectMotionTargetClass(locator, activeClass, shouldBeApplied, contextLabel) {
	await expect.poll(
		async () => locator.evaluate((element, className) => element.classList.contains(className), activeClass),
		{
			message: `${contextLabel} expected class "${activeClass}" application state to be ${shouldBeApplied}`,
		}
	).toBe(shouldBeApplied);
}

async function expectMotionTargetCase(page, testCase) {
	const issues = await collectPageIssues(page);
	const section = page.locator(testCase.sectionSelector).first();

	await page.goto(testCase.path, { waitUntil: 'networkidle' });
	await expect(section, `${testCase.label} section`).toBeVisible();

	if (testCase.expectedTarget) {
		await expect(section, `${testCase.label} motion target attribute`).toHaveAttribute('data-mrn-motion-target', testCase.expectedTarget);
	}

	const target = section.locator(testCase.targetSelector).first();
	await expect(target, `${testCase.label} target`).toBeVisible();
	await target.scrollIntoViewIfNeeded();
	await page.waitForTimeout(250);
	await expectMotionTargetClass(target, testCase.activeClass, true, `${testCase.label} target`);

	const sectionMatchesTarget = await section.evaluate(
		(element, selector) => element.matches(selector),
		testCase.targetSelector
	);

	if (! sectionMatchesTarget) {
		await expectMotionTargetClass(section, testCase.activeClass, false, `${testCase.label} wrapper`);
	}

	if (testCase.nonTargetSelector) {
		const nonTarget = section.locator(testCase.nonTargetSelector).first();
		await expect(nonTarget, `${testCase.label} non-target`).toBeVisible();
		await expectMotionTargetClass(nonTarget, testCase.activeClass, false, `${testCase.label} non-target`);
	}

	expectNoPageIssues(issues, testCase.label);
}

async function loginToWordPressAdmin(page) {
	await page.goto('/wp-login.php', { waitUntil: 'domcontentloaded' });
	await page.getByLabel(/username or email address/i).fill(process.env.MRN_WP_ADMIN_USER);
	await page.locator('#user_pass').fill(process.env.MRN_WP_ADMIN_PASS);
	await page.getByRole('button', { name: /log in/i }).click();
	await page.waitForLoadState('domcontentloaded');
	await page.waitForTimeout(3000);

	const loginFormCount = await page.locator('#loginform').count();
	const loginErrorLocator = page.locator('#login_error, .message.error');
	const loginErrorCount = await loginErrorLocator.count();
	const loginErrors = loginErrorCount > 0 ? await loginErrorLocator.allInnerTexts() : [];
	const hasAdminShell = (await page.locator('body.wp-admin, #wpadminbar').count()) > 0;
	const finalUrl = page.url();

	expect.soft(loginErrorCount, `WordPress login errors at ${finalUrl}: ${loginErrors.join(' | ')}`).toBe(0);
	expect.soft(
		loginFormCount > 0 && ! hasAdminShell,
		`WordPress login stayed on wp-login.php at ${finalUrl}: ${loginErrors.join(' | ') || 'no login error text returned'}`
	).toBe(false);
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
	const motionTargetCases = getMotionTargetCases();
	const requireStructureChecks = process.env.MRN_SMOKE_REQUIRE_STRUCTURE === '1';
	const shellSelectors = process.env.MRN_PUBLIC_SHELL_SELECTORS || '#page, #content, .site, body';
	const primaryContentSelectors = process.env.MRN_PUBLIC_CONTENT_SELECTORS || 'main#primary, main.site-main, #primary, #content, .site-main, main, article';

	test('home page loads without browser/runtime errors', async ({ page, baseURL }) => {
		const issues = await collectPageIssues(page);

		await page.goto('/', { waitUntil: 'networkidle' });

		await expect(page).toHaveTitle(/.+/);
		await expect(page.locator('body')).toBeVisible();

		if (requireStructureChecks) {
			await expectAnyVisible(page, shellSelectors, 'Home page shell');
			await expectAnyVisible(page, primaryContentSelectors, 'Home page primary content');
		}

		expect(page.url()).toContain(baseURL || '');
		expectNoPageIssues(issues, 'Home page');
	});

	test('sample page loads without browser/runtime errors', async ({ page }) => {
		const samplePath = process.env.MRN_SAMPLE_PAGE_PATH || '/sample-page/';
		const issues = await collectPageIssues(page);

		await page.goto(samplePath, { waitUntil: 'networkidle' });

		await expect(page.locator('body')).toBeVisible();

		if (requireStructureChecks) {
			await expectAnyVisible(page, shellSelectors, 'Sample page shell');
			await expectAnyVisible(page, primaryContentSelectors, 'Sample page primary content');
		}

		expectNoPageIssues(issues, 'Sample page');
	});

	test('mobile drawer branding stays hidden at desktop width', async ({ page }) => {
		await page.setViewportSize({ width: 1280, height: 720 });
		await page.goto('/', { waitUntil: 'networkidle' });

		const navigation = page.locator('[data-mrn-mobile-navigation]').first();

		test.skip((await navigation.count()) === 0, 'Mobile navigation is not enabled in this runtime.');

		await expect(navigation).toHaveAttribute('data-mrn-mobile-active', 'false');
		await expect(navigation.locator(':scope > .menu-toggle')).toBeHidden();
		await expect(navigation.locator('.mrn-mobile-navigation__drawer-header')).toBeHidden();
	});

	test('subtle text reveal is prepared before entry and runs only once', async ({ page }) => {
		await page.setViewportSize({ width: 1280, height: 720 });
		await page.goto('/', { waitUntil: 'networkidle' });

		const section = page.locator('[data-mrn-motion-effect="text-reveal"]').first();
		test.skip((await section.count()) === 0, 'Subtle Text Reveal is not configured in this runtime.');

		await expect(section).toHaveClass(/is-mrn-text-reveal-prepared/);
		await expect(page.locator('html')).toHaveClass(/mrn-motion-effects-ready/);

		const targetType = await section.getAttribute('data-mrn-motion-target');
		const targetSelectors = {
			surface: '.mrn-layout-surface',
			content: '.mrn-layout-content--text, .mrn-reusable-block__content, .mrn-hero__content, .mrn-reusable-block__inner, .mrn-ui__body',
			media: '.mrn-ui__media, .mrn-reusable-block__media, .mrn-hero__media, .mrn-section-background-media',
			header: '.mrn-ui__head, .mrn-card-row__head, .mrn-content-list-row__header, .mrn-hero__content',
			items: '.mrn-ui__items, .mrn-card-row__grid, .mrn-content-list-row__items, .mrn-faq__items',
			'left-column': '.mrn-two-column-split__column--left > .mrn-content-builder__row, .mrn-two-column-split__column--left .mrn-content-builder__row, .mrn-two-column-split__column--left',
			'right-column': '.mrn-two-column-split__column--right > .mrn-content-builder__row, .mrn-two-column-split__column--right .mrn-content-builder__row, .mrn-two-column-split__column--right',
		};
		const targetSelector = targetSelectors[targetType] || '';
		const targetMatchesSection = targetSelector
			? await section.evaluate((element, selector) => element.matches(selector), targetSelector)
			: false;
		const target = ! targetSelector || targetMatchesSection
			? section
			: section.locator(targetSelector).first();

		await expect(target).toHaveClass(/is-mrn-text-reveal-target/);
		const initialBox = await target.boundingBox();
		if (initialBox && initialBox.y > 720) {
			await expect(target).toHaveCSS('opacity', '0');
		}

		await target.scrollIntoViewIfNeeded();
		await expect(target).toHaveClass(/has-mrn-text-revealed/);
		await expect(target).toHaveCSS('opacity', '1');
		await expect(target).toHaveCSS('transform', 'none');

		await page.evaluate(() => window.scrollTo(0, 0));
		await page.waitForTimeout(150);
		await target.scrollIntoViewIfNeeded();
		await page.waitForTimeout(150);
		await expect(target).not.toHaveClass(/is-mrn-text-reveal-active/);
		await expect(target).toHaveClass(/has-mrn-text-revealed/);
	});

	for (const testCase of motionTargetCases) {
		test(`motion target applies effect to configured target: ${testCase.label}`, async ({ page }) => {
			await expectMotionTargetCase(page, testCase);
		});
	}

	test.describe('admin smoke coverage', () => {
		test.describe.configure({ mode: 'serial' });

			test('page editor shows expected editing UI when admin credentials are provided', async ({ page }) => {
				test.skip(
					! process.env.MRN_WP_ADMIN_USER || ! process.env.MRN_WP_ADMIN_PASS || ! process.env.MRN_SAMPLE_PAGE_EDIT_PATH,
					'Set MRN_WP_ADMIN_USER, MRN_WP_ADMIN_PASS, and MRN_SAMPLE_PAGE_EDIT_PATH to run admin builder smoke coverage.'
				);

			const issues = await collectPageIssues(page);

			await loginToWordPressAdmin(page);

				await page.goto(process.env.MRN_SAMPLE_PAGE_EDIT_PATH, { waitUntil: 'networkidle' });

				await expect(page.locator('body.wp-admin')).toBeVisible();
				const flexibleFields = page.locator('.acf-field-flexible-content:visible');
				const hasBuilderUi = (await flexibleFields.count()) > 0;

				if (hasBuilderUi) {
					await expect(flexibleFields.first()).toBeVisible();
					await expect(page.locator('.acf-field-flexible-content .acf-actions [data-name="add-layout"]:visible').first()).toBeVisible();
				} else {
					await expect(page.locator('#wp-content-wrap, #content, #poststuff').first()).toBeVisible();
				}

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

		test('business information media-field changes enable sticky save state', async ({ page }) => {
			test.skip(
				! process.env.MRN_WP_ADMIN_USER ||
				! process.env.MRN_WP_ADMIN_PASS ||
				! process.env.MRN_BUSINESS_INFORMATION_PAGE_PATH,
				'Set MRN_WP_ADMIN_USER, MRN_WP_ADMIN_PASS, and MRN_BUSINESS_INFORMATION_PAGE_PATH to run business-information dirty-state coverage.'
			);

			await loginToWordPressAdmin(page);
			await page.goto(process.env.MRN_BUSINESS_INFORMATION_PAGE_PATH, { waitUntil: 'domcontentloaded' });

			const saveButton = page.locator('.mrn-sticky-save-bar .mrn-settings-tab--save').first();
			const footerLogoInput = page.locator(
				'.acf-field[data-key="field_mrn_business_logo_footer"] input[type="hidden"][name^="acf["]'
			).first();

			await expect(page.locator('body.wp-admin')).toBeVisible();
			await expect(saveButton).toBeVisible();
			await expect(footerLogoInput).toHaveCount(1);

			// Baseline: no unsaved changes, sticky save button is disabled.
			await expect(saveButton).toBeDisabled();

			// Simulate an ACF media-driven value update without dispatching input/change.
			await footerLogoInput.evaluate((input) => {
				const original = String(input.value || '');
				const nextValue = original === '' ? '12345' : original + '-qa';
				input.value = nextValue;
				input.setAttribute('value', nextValue);

				const uploader = input.closest('.acf-image-uploader');
				if (!uploader) {
					return;
				}

				const previewImage = uploader.querySelector('.image-wrap img');
				if (previewImage) {
					previewImage.setAttribute('src', 'https://example.com/qa-logo.png?v=' + Date.now());
				}
			});

			await expect(saveButton).toBeEnabled();
			await expect(saveButton).toHaveAttribute('aria-disabled', 'false');
		});
	});
});
