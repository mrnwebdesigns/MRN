const { chromium } = require('@playwright/test');

async function waitForLoadState(page, state, timeout) {
	const startedAt = Date.now();

	try {
		await page.waitForLoadState(state, { timeout });

		return {
			state,
			ok: true,
			elapsedMs: Date.now() - startedAt,
		};
	} catch (error) {
		return {
			state,
			ok: false,
			elapsedMs: Date.now() - startedAt,
			error: error instanceof Error ? error.message : String(error),
		};
	}
}

function summarizeConsoleMessage(message) {
	return {
		type: message.type(),
		text: message.text(),
	};
}

async function measureDropdownOpen(page) {
	const selectors = [
		'.acf-field .select2-selection',
		'.acf-field select',
		'#screen-options-wrap select',
	];

	for (const selector of selectors) {
		const target = page.locator(selector).first();
		const count = await target.count();

		if (!count) {
			continue;
		}

		const box = await target.boundingBox();
		if (!box) {
			continue;
		}

		const start = Date.now();

		try {
			await page.mouse.click(box.x + box.width / 2, box.y + box.height / 2);

			if (selector.includes('select2')) {
				await page.waitForFunction(
					() => !!document.querySelector('.select2-container--open, .select2-dropdown'),
					null,
					{ timeout: 5000 }
				);
			} else {
				await page.waitForTimeout(100);
			}

			const elapsedMs = Date.now() - start;
			const opened = await page.evaluate(() => {
				return !!document.querySelector('.select2-container--open, .select2-dropdown') || document.activeElement?.tagName === 'SELECT';
			});

			await page.keyboard.press('Escape').catch(() => {});

			return {
				selector,
				elapsedMs,
				opened,
			};
		} catch (error) {
			return {
				selector,
				elapsedMs: Date.now() - start,
				opened: false,
				error: error instanceof Error ? error.message : String(error),
			};
		}
	}

	return {
		error: 'No measurable dropdown target found',
	};
}

async function measureTitleInput(page, text) {
	const target = page.locator('#title');
	const count = await target.count();

	if (!count) {
		return {
			error: 'No title input found',
		};
	}

	const box = await target.boundingBox();
	if (!box) {
		return {
			error: 'Title input not visible',
		};
	}

	const startedAt = Date.now();

	try {
		await page.mouse.click(box.x + 20, box.y + box.height / 2);
		await page.waitForFunction(
			() => document.activeElement && document.activeElement.id === 'title',
			null,
			{ timeout: 5000 }
		);

		await page.keyboard.type(text, { delay: 10 });

		await page.waitForFunction(
			(expectedText) => {
				const input = document.querySelector('#title');
				return !!input && input.value.includes(expectedText);
			},
			text,
			{ timeout: 5000 }
		);

		return {
			elapsedMs: Date.now() - startedAt,
			valueLength: await target.inputValue().then((value) => value.length),
		};
	} catch (error) {
		return {
			elapsedMs: Date.now() - startedAt,
			error: error instanceof Error ? error.message : String(error),
		};
	}
}

async function run() {
	const postId = process.argv[2] || '802';
	const requestedMode = process.argv[3] || 'off';
	const diagnosticMode = requestedMode === 'on' ? 'on' : ( requestedMode === 'acf' ? 'acf' : 'off' );
	const isNewEditor = /^new:(post|page)$/.test(postId);
	const newEditorPostType = isNewEditor ? postId.split(':')[1] : '';
	const browser = await chromium.launch({ headless: true });
	const context = await browser.newContext({ baseURL: 'http://mrn-plugin-stack.local' });
	const page = await context.newPage();
	const consoleMessages = [];
	const pageErrors = [];
	const requestFailures = [];

	page.setDefaultTimeout(120000);
	page.setDefaultNavigationTimeout(120000);

	page.on('console', (message) => {
		consoleMessages.push(summarizeConsoleMessage(message));
	});

	page.on('pageerror', (error) => {
		pageErrors.push(error instanceof Error ? error.message : String(error));
	});

	page.on('requestfailed', (request) => {
		requestFailures.push({
			url: request.url(),
			method: request.method(),
			errorText: request.failure()?.errorText || 'Unknown failure',
		});
	});

	await page.addInitScript(() => {
		window.__mrnLagProfile = {
			readyStates: [],
			longTasks: [],
			loadEvents: [],
		};

		const pushReadyState = () => {
			window.__mrnLagProfile.readyStates.push({
				state: document.readyState,
				at: performance.now(),
			});
		};

		pushReadyState();
		document.addEventListener('readystatechange', pushReadyState);
		window.addEventListener('load', () => {
			window.__mrnLagProfile.loadEvents.push({
				type: 'load',
				at: performance.now(),
			});
		});

		if ('PerformanceObserver' in window) {
			try {
				const observer = new PerformanceObserver((list) => {
					list.getEntries().forEach((entry) => {
						window.__mrnLagProfile.longTasks.push({
							name: entry.name,
							startTime: entry.startTime,
							duration: entry.duration,
						});
					});
				});

				observer.observe({ type: 'longtask', buffered: true });
			} catch (error) {
				window.__mrnLagProfile.longTasks.push({
					name: 'observer-error',
					startTime: 0,
					duration: 0,
					error: String(error),
				});
			}
		}
	});

	await page.goto('/wp-login.php', { waitUntil: 'domcontentloaded' });
	await page.getByLabel(/username or email address/i).fill('codex_qa_admin');
	await page.locator('#user_pass').fill('CodexPlaywrightLocal123');
	await page.getByRole('button', { name: /log in/i }).click();
	await page.waitForLoadState('domcontentloaded');

	await context.addCookies([
		{
			name: 'mrn_post_page_editor_stack',
			value: diagnosticMode === 'off' || diagnosticMode === 'acf' ? 'off' : '',
			url: 'http://mrn-plugin-stack.local',
		},
		{
			name: 'mrn_post_page_editor_acf_only',
			value: diagnosticMode === 'acf' ? 'on' : '',
			url: 'http://mrn-plugin-stack.local',
		},
	]);

	const editorPath = isNewEditor
		? `/wp-admin/post-new.php?post_type=${newEditorPostType}`
		: `/wp-admin/post.php?post=${postId}&action=edit`;
	const navigationStartedAt = Date.now();
	const response = await page.goto(editorPath, { waitUntil: 'commit' });
	const committedMs = Date.now() - navigationStartedAt;
	const domContentLoaded = await waitForLoadState(page, 'domcontentloaded', 120000);
	const load = await waitForLoadState(page, 'load', 120000);

	await page.waitForTimeout(5000);

	const titleInputProbeImmediate = await measureTitleInput(page, 'Lag probe now');

	await page.waitForTimeout(5000);

	const titleInputProbeSettled = await measureTitleInput(page, ' Lag probe later');
	const dropdownProbe = await measureDropdownOpen(page);
	const diagnostics = await page.evaluate(() => {
		const interestingAsset = (value) => {
			if (!value) {
				return false;
			}

			return [
				'mrn',
				'acf',
				'select2',
				'smartcrawl',
				'folders',
				'tinymce',
				'heartbeat',
				'ame',
			].some((needle) => String(value).toLowerCase().includes(needle));
		};

		const navigationEntry = performance.getEntriesByType('navigation')[0];
		const resourceEntries = performance.getEntriesByType('resource') || [];
		const resources = resourceEntries
			.filter((entry) => interestingAsset(entry.name))
			.map((entry) => ({
				name: entry.name,
				initiatorType: entry.initiatorType,
				duration: Math.round(entry.duration),
				transferSize: entry.transferSize,
			}))
			.sort((left, right) => right.duration - left.duration)
			.slice(0, 40);

		const metaboxes = Array.from(document.querySelectorAll('#poststuff .postbox')).map((box) => ({
			id: box.id || null,
			title: box.querySelector('.hndle, .postbox-header h2, .acf-hndle-cog')?.textContent?.trim() || '',
		}));

		const scripts = Array.from(document.scripts)
			.map((script) => script.src)
			.filter((src) => interestingAsset(src));

		const stylesheets = Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
			.map((link) => link.href)
			.filter((href) => interestingAsset(href));

			const selectFields = Array.from(document.querySelectorAll('.acf-field select'));
			const select2Containers = document.querySelectorAll('.select2-container').length;
			const wysiwygIframes = document.querySelectorAll('iframe[id^="acf-editor"], .wp-editor-wrap iframe, .tox-edit-area iframe').length;
			const totalFields = document.querySelectorAll('.acf-field').length;
			const totalLayouts = document.querySelectorAll('.acf-field-flexible-content .layout:not(.acf-clone)').length;
			const cloneLayouts = document.querySelectorAll('.acf-field-flexible-content .layout.acf-clone').length;
			const totalRepeaters = document.querySelectorAll('.acf-field[data-type="repeater"]').length;
			const cloneRepeaterRows = document.querySelectorAll('.acf-field[data-type="repeater"] .acf-row.acf-clone').length;
			const domNodes = document.getElementsByTagName('*').length;
		const longTasks = (window.__mrnLagProfile?.longTasks || [])
			.filter((entry) => !entry.error)
			.sort((left, right) => right.duration - left.duration)
			.slice(0, 25);

		return {
			readyState: document.readyState,
			bodyClass: document.body.className,
			htmlSizeChars: document.documentElement.outerHTML.length,
			domNodes,
				totalFields,
				totalLayouts,
				cloneLayouts,
				totalRepeaters,
				cloneRepeaterRows,
				selectCount: selectFields.length,
			select2Containers,
			wysiwygIframes,
			metaboxes,
			scripts,
			stylesheets,
			navigation: navigationEntry
				? {
					domContentLoadedEventEnd: Math.round(navigationEntry.domContentLoadedEventEnd),
					loadEventEnd: Math.round(navigationEntry.loadEventEnd),
					responseEnd: Math.round(navigationEntry.responseEnd),
					transferSize: navigationEntry.transferSize,
				}
				: null,
			longTasks,
			readyStates: window.__mrnLagProfile?.readyStates || [],
			loadEvents: window.__mrnLagProfile?.loadEvents || [],
			resourceHotspots: resources,
			hasBuilderGlobal: typeof window.mrnBaseStackBuilderAdmin !== 'undefined',
			hasEditorToolsGlobal: typeof window.MRN_EDITOR_TOOLS_SETTINGS !== 'undefined',
			seoHelperPresent: !!document.getElementById('acf-group_69a1c0f3a1b01'),
		};
	});

	console.log(
		JSON.stringify(
				{
					postId,
					editorPath,
					diagnosticState: diagnosticMode,
					navigation: {
						url: response?.url() || null,
						status: response?.status() || null,
						committedMs,
						domContentLoaded,
						load,
					},
					titleInputProbeImmediate,
					titleInputProbeSettled,
					dropdownProbe,
				pageErrors,
				requestFailures,
				consoleMessages: consoleMessages.slice(-30),
				diagnostics,
			},
			null,
			2
		)
	);

	await browser.close();
}

run().catch((error) => {
	console.error(error);
	process.exitCode = 1;
});
