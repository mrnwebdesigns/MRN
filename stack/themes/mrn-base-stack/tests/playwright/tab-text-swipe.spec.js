const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
const path = require('node:path');
const themeRoot = path.resolve(__dirname, '../..');
const image = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="400" height="400"%3E%3Crect width="400" height="400" fill="%23a2cbcc"/%3E%3C/svg%3E';

function tabsMarkup(id = 'swipe', effect = 'text-swipe', mediaOnly = false) {
	return `<div id="${id}" class="mrn-tabbed-layout mrn-tabbed-layout--transition-${effect} mrn-tabbed-layout--equal-heights" data-mrn-tabbed-layout data-mrn-equal-panel-heights="true">
		<div role="tablist" aria-label="${id} examples">
			${['First', 'Second', 'Third'].map((label, index) => `<button id="${id}-tab-${index}" role="tab" type="button" aria-controls="${id}-panel-${index}" aria-selected="${index === 0}" tabindex="${index === 0 ? 0 : -1}" data-mrn-tab-button>${label}</button>`).join('')}
		</div>
		<div class="mrn-tabbed-layout__panels">
			${['First', 'Second', 'Third'].map((label, index) => `<div id="${id}-panel-${index}" class="mrn-tabbed-layout__panel ${index === 0 ? 'is-active' : ''}" role="tabpanel" aria-labelledby="${id}-tab-${index}" data-mrn-tab-panel ${index ? 'hidden' : ''}>
				<div class="example-card">
					${mediaOnly && index === 0 ? '' : `<div class="${index === 2 ? 'mrn-reusable-block__content' : 'mrn-layout-content--text'}">
						<div class="mrn-image-content-row__content-inner">
							<header class="mrn-ui__head"><h2>${label} heading</h2></header>
							<div class="mrn-ui__text"><p>${label} paragraph with readable content.</p></div>
							<a href="#${id}-tab-${index}">More about ${label.toLowerCase()}</a>
						</div>
					</div>`}
					<figure class="example-media"><img src='${image}' alt="Example utility image"></figure>
				</div>
			</div>`).join('')}
		</div>
	</div>`;
}

async function loadFixture(page, { mediaOnly = false, secondRoot = false } = {}) {
	await page.setContent(`<!doctype html><html lang="en"><head><title>Tab text animation fixture</title></head><body>
		<header id="site-header">Shared header</header><main><h1>Animation examples</h1>
		${tabsMarkup('swipe', 'text-swipe', mediaOnly)}${secondRoot ? tabsMarkup('other', 'instant') : ''}
		</main><footer id="site-footer">Shared footer</footer></body></html>`);
	await page.addStyleTag({ path: path.join(themeRoot, 'style.css') });
	await page.addStyleTag({ content: `
		body { margin: 0; font: 18px/1.5 sans-serif; color: #071c34; background: white; }
		main, #site-header, #site-footer { padding: 20px; }
		button { margin: 4px; padding: 10px; color: #071c34; background: #eee; }
		button[aria-selected="true"] { background: #071c34; color: white; }
		.example-card { display: grid; grid-template-columns: 2fr 1fr; background: #071c34; color: white; min-height: max(300px, var(--mrn-tabbed-layout-panel-height, 0px)); }
		.example-card .mrn-layout-content--text, .example-card .mrn-reusable-block__content { padding: 48px; display: grid; align-items: center; }
		.example-card h2 { color: white; } .example-card a { color: white; }
		.example-media { margin: 0; } .example-media img { width: 100%; height: 100%; object-fit: cover; }
		@media(max-width:600px) { .example-card { grid-template-columns: 1fr; } .example-media { height: 150px; } }
	` });
	await page.addScriptTag({ path: path.join(themeRoot, 'js/front-end-tabs.js') });
}

async function settled(page) {
	await expect.poll(() => page.evaluate(() => !!document.querySelector('#swipe').mrnTabTextSwipe)).toBe(false);
	await expect(page.locator('#swipe [data-mrn-tab-panel]:visible')).toHaveCount(1);
	await expect(page.locator('#swipe .is-mrn-tab-text-swiping')).toHaveCount(0);
}

async function transitionState(page) {
	return page.evaluate(() => {
		const root = document.querySelector('#swipe');
		const state = root.mrnTabTextSwipe;
		return state ? state.animations.filter(animation => animation.playState === 'running').map(animation => ({
			classes: animation.effect.target.className,
			frames: animation.effect.getKeyframes().map(({ translate, opacity }) => ({ translate, opacity })),
			mask: getComputedStyle(animation.effect.target.closest('.is-mrn-tab-text-swiping')).maskImage,
		})) : [];
	});
}

for (const viewport of [{ width: 1280, height: 900 }, { width: 390, height: 844 }]) {
	test(`only heading and body move; card, image and shared chrome stay fixed at ${viewport.width}px`, async ({ page }) => {
		await page.setViewportSize(viewport);
		await loadFixture(page);
		const before = await page.locator('#swipe-panel-0 .example-card').boundingBox();
		const header = await page.locator('#site-header').boundingBox();
		const footer = await page.locator('#site-footer').boundingBox();
		await page.getByRole('tab', { name: 'Second', exact: true }).click();
		const state = await transitionState(page);
		expect(state).toHaveLength(2);
		expect(state.every(item => /mrn-ui__(head|text)/.test(item.classes))).toBe(true);
		for (const item of state) expect(item.frames[1].translate).toMatch(/^(-4rem|calc\(-)/);
		expect(state.every(item => item.mask.includes('linear-gradient'))).toBe(true);
		expect(await page.evaluate(() => [...document.querySelectorAll('.example-card, .example-media, img, a')].every(el => el.getAnimations().length === 0))).toBe(true);
		expect(await page.locator('#swipe-panel-0 .example-card').boundingBox()).toEqual(before);
		await settled(page);
		expect(await page.locator('#swipe-panel-1 .example-card').boundingBox()).toEqual(before);
		expect(await page.locator('#site-header').boundingBox()).toEqual(header);
		expect(await page.locator('#site-footer').boundingBox()).toEqual(footer);
		await expect(page.getByRole('tabpanel')).toContainText('Second heading');
		expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
	});
}

test('reverse direction and incoming fade; keyboard focus and wrap direction remain correct', async ({ page }) => {
	await loadFixture(page);
	await page.getByRole('tab', { name: 'Third', exact: true }).click();
	await settled(page);
	await page.getByRole('tab', { name: 'Third', exact: true }).press('ArrowLeft');
	await expect(page.getByRole('tab', { name: 'Second', exact: true })).toBeFocused();
	let state = await transitionState(page);
	for (const item of state) expect(item.frames[1].translate).toMatch(/^4rem(?:\s0)?$/);
	await page.evaluate(() => document.querySelector('#swipe').mrnTabTextSwipe.animations.forEach(animation => animation.finish()));
	state = await transitionState(page);
	expect(state).toHaveLength(2);
	for (const item of state) {
		expect(item.frames[0].translate).toMatch(/^(-4rem|calc\(-)/);
		expect(Number(item.frames[0].opacity)).toBe(0);
	}
	await settled(page);
	await page.getByRole('tab', { name: 'Second', exact: true }).press('End');
	await settled(page);
	await page.getByRole('tab', { name: 'Third', exact: true }).press('ArrowRight');
	state = await transitionState(page);
	for (const item of state) expect(item.frames[1].translate).toMatch(/^(-4rem|calc\(-)/);
	await settled(page);
	await expect(page.getByRole('tab', { name: 'First', exact: true })).toBeFocused();
});

test('rapid clicks, same-tab clicks and interrupted transitions leave the requested text visible', async ({ page }) => {
	await loadFixture(page, { secondRoot: true });
	const tabs = page.locator('#swipe');
	await tabs.getByRole('tab', { name: 'Second', exact: true }).click();
	await tabs.getByRole('tab', { name: 'Third', exact: true }).click();
	await tabs.getByRole('tab', { name: 'First', exact: true }).click();
	await tabs.getByRole('tab', { name: 'First', exact: true }).click();
	await settled(page);
	await expect(tabs.getByRole('tabpanel')).toContainText('First heading');
	await tabs.getByRole('tab', { name: 'Third', exact: true }).click();
	await page.setViewportSize({ width: 700, height: 900 });
	await settled(page);
	await expect(tabs.getByRole('tabpanel')).toContainText('Third heading');
	await expect(page.locator('#other').getByRole('tab', { name: 'First', exact: true })).toHaveAttribute('aria-selected', 'true');
	expect(await page.locator('#other').evaluate(root => root.getAnimations({ subtree: true }).length)).toBe(0);
});

test('reduced motion, preference changes and panels without text switch instantly', async ({ page }) => {
	await page.emulateMedia({ reducedMotion: 'reduce' });
	await loadFixture(page);
	await page.getByRole('tab', { name: 'Second', exact: true }).click();
	await expect(page.getByRole('tabpanel')).toContainText('Second heading');
	expect(await transitionState(page)).toEqual([]);
	await page.emulateMedia({ reducedMotion: 'no-preference' });
	await page.getByRole('tab', { name: 'Third', exact: true }).click();
	await page.emulateMedia({ reducedMotion: 'reduce' });
	await settled(page);
	await expect(page.getByRole('tabpanel')).toContainText('Third heading');
	await page.emulateMedia({ reducedMotion: 'no-preference' });
	await loadFixture(page, { mediaOnly: true });
	await page.getByRole('tab', { name: 'Second', exact: true }).click();
	await expect(page.getByRole('tabpanel')).toContainText('Second heading');
	expect(await transitionState(page)).toEqual([]);
});

test('tab roles and text retain WCAG A/AA semantics after animation', async ({ page }) => {
	await loadFixture(page);
	await page.getByRole('tab', { name: 'Second', exact: true }).click();
	await settled(page);
	const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
	expect(results.violations).toEqual([]);
});
