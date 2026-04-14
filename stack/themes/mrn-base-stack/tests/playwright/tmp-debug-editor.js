const { chromium } = require('@playwright/test');

async function run() {
	const postId = process.argv[2] || '805';
	const browser = await chromium.launch({ headless: true });
	const page = await browser.newPage({ baseURL: 'http://mrn-plugin-stack.local' });

	await page.goto('/wp-login.php', { waitUntil: 'domcontentloaded' });
	await page.getByLabel(/username or email address/i).fill('codex_qa_admin');
	await page.locator('#user_pass').fill('CodexPlaywrightLocal123');
	await page.getByRole('button', { name: /log in/i }).click();
	await page.waitForLoadState('domcontentloaded');
	await page.context().addCookies([
		{
			name: 'mrn_post_page_editor_stack',
			value: 'off',
			url: 'http://mrn-plugin-stack.local',
		},
	]);
	await page.goto(`/wp-admin/post.php?post=${ postId }&action=edit`, { waitUntil: 'domcontentloaded' });
	await page.waitForTimeout(3000);

	const data = await page.evaluate(() => {
		const interestingAsset = (value) => {
			if (!value) {
				return false;
			}

			return [
				'mrn',
				'content-builder-admin',
				'editor-toolbar',
				'toolbar-icons',
				'admin-repeater-controls',
				'admin-icon-choosers',
				'smartcrawl',
			].some((needle) => String(value).toLowerCase().includes(needle));
		};

		const metaboxes = Array.from(document.querySelectorAll('#poststuff .postbox')).map((box) => {
			const title = box.querySelector('.hndle, .postbox-header h2, .acf-hndle-cog')?.textContent?.trim() || '';
			return {
				id: box.id || null,
				title,
			};
		}).filter((box) => box.id || box.title);

		const scripts = Array.from(document.scripts)
			.map((script) => script.src)
			.filter((src) => interestingAsset(src));

		const stylesheets = Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
			.map((link) => link.href)
			.filter((href) => interestingAsset(href));

		return {
			bodyClass: document.body.className,
			noticeText: Array.from(document.querySelectorAll('.notice p')).map((node) => node.textContent.trim()),
			toggleButtonText: document.querySelector('.notice a.button')?.textContent?.trim() || null,
			rootMarkers: {
				precollapseClass: document.documentElement.classList.contains('mrn-base-stack-admin-precollapse'),
				builderPrecollapse: document.documentElement.getAttribute('data-mrn-builder-precollapse'),
				repeaterPrecollapse: document.documentElement.getAttribute('data-mrn-repeater-precollapse'),
				hasBuilderGlobal: typeof window.mrnBaseStackBuilderAdmin !== 'undefined',
				hasEditorToolsGlobal: typeof window.MRN_EDITOR_TOOLS_SETTINGS !== 'undefined',
			},
			metaboxes,
			scripts,
			stylesheets,
			builderActionCount: document.querySelectorAll('.mrn-convert-reusable-block-action').length,
			seoHelperBoxes: metaboxes.filter((box) => box.id === 'acf-group_69a1c0f3a1b01' || /seo helper|seo must have/i.test(box.title)),
			flexibleLayouts: document.querySelectorAll('.acf-field-flexible-content .layout:not(.acf-clone)').length,
		};
	});

	console.log(JSON.stringify({ postId, diagnostics: data }, null, 2));
	await browser.close();
}

run().catch((error) => {
	console.error(error);
	process.exitCode = 1;
});
