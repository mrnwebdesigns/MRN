const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
const fs = require('node:fs');
const http = require('node:http');
const path = require('node:path');

const theme = path.resolve(__dirname, '../..');
const controller = fs.readFileSync(path.join(theme, 'js/mobile-navigation.js'), 'utf8');
const styles = fs.readFileSync(path.join(theme, 'css/mobile-navigation.css'), 'utf8');
let server;
let origin;

test.beforeAll(async () => {
	server = http.createServer((request, response) => {
		const url = new URL(request.url, 'http://localhost');
		const late = url.searchParams.has('late');
		const missing = url.searchParams.has('missing');
		response.writeHead(200, { 'content-type': 'text/html', 'cache-control': 'no-store' });
		response.write(`<!doctype html><html lang="en"><head><meta charset="utf-8">
			<meta name="viewport" content="width=device-width, initial-scale=1"><title>Navigation fixture</title>
			<style>${styles}body{margin:0;font:16px Arial}header{height:80px}.menu{margin:0;list-style:none;padding:0}.menu a{display:block;padding:16px}.sub-menu{display:none}main{background:#eee;min-height:600px}h1{margin:0;padding:20px}.mrn-mobile-navigation:not([data-mrn-mobile-active="true"]) .menu-toggle{display:none}</style>
			${late ? '' : `<script>${controller}</script>`}</head><body><header>Site header</header>
			<nav class="mrn-mobile-navigation" aria-label="Primary" data-mrn-mobile-navigation style="--mrn-mobile-menu-breakpoint:900px">
			<button class="menu-toggle" aria-expanded="false" aria-label="Open navigation" aria-controls="panel" data-close-label="Close navigation">Menu</button>
			<div class="mrn-mobile-navigation__panel" id="panel">${missing ? '' : `<ul class="menu"><li class="menu-item-has-children"><a href="#content">Products</a><ul class="sub-menu"><li><a href="#content">Gloves</a></li></ul></li><li><a href="#content">Contact</a></li></ul>`}</div></nav>
			<main id="content"><h1>Visible page content</h1></main>`);
		// A slow response tail reproduces the period when the old expanded
		// fallback could paint before footer scripts initialized the drawer.
		setTimeout(() => response.end(`${late ? `<script>${controller}</script>` : ''}</body></html>`), 700);
	});
	await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
	origin = `http://127.0.0.1:${server.address().port}`;
});

test.afterAll(async () => {
	await new Promise(resolve => server.close(resolve));
});

for (const width of [390, 900, 901, 1366]) {
	test(`navigation has its final geometry before the document finishes at ${width}px`, async ({ page }) => {
		await page.setViewportSize({ width, height: 844 });
		await page.addInitScript(() => {
			window.navigationShifts = [];
			new PerformanceObserver(list => {
				for (const entry of list.getEntries()) {
					if (!entry.hadRecentInput) window.navigationShifts.push(entry.value);
				}
			}).observe({ type: 'layout-shift', buffered: true });
		});
		await page.goto(origin, { waitUntil: 'commit' });
		await expect(page.locator('main')).toBeVisible();
		const before = await page.evaluate(() => ({ top: document.querySelector('main').getBoundingClientRect().top, state: document.readyState }));
		expect(before.state).toBe('loading');
		await expect(page.locator('nav')).toHaveAttribute('data-mrn-mobile-active', width <= 900 ? 'true' : 'false');
		await page.waitForLoadState('load');
		const after = await page.locator('main').boundingBox();
		expect(after.y).toBe(before.top);
		expect(await page.evaluate(() => window.navigationShifts.reduce((a, b) => a + b, 0))).toBeLessThan(0.001);
	});
}

test('drawer, nested links, keyboard focus, and resize still work', async ({ page }) => {
	await page.setViewportSize({ width: 390, height: 844 });
	await page.goto(origin);
	const nav = page.locator('nav');
	const toggle = nav.locator(':scope > .menu-toggle');
	await toggle.focus();
	await page.keyboard.press('Enter');
	await expect(toggle).toHaveAttribute('aria-expanded', 'true');
	const submenu = nav.getByRole('button', { name: 'Open Products submenu' });
	await submenu.click();
	await expect(nav.getByRole('link', { name: 'Gloves', exact: true })).toBeVisible();
	await toggle.focus();
	await page.keyboard.press('Shift+Tab');
	await expect(nav.getByRole('link', { name: 'Contact', exact: true })).toBeFocused();
	await page.keyboard.press('Tab');
	await expect(toggle).toBeFocused();
	await page.keyboard.press('Escape');
	await expect(toggle).toHaveAttribute('aria-expanded', 'false');
	await expect(toggle).toBeFocused();
	await toggle.click();
	await page.setViewportSize({ width: 901, height: 844 });
	await expect(nav).toHaveAttribute('data-mrn-mobile-active', 'false');
	await expect(nav).not.toHaveClass(/is-open/);
	await expect(page.locator('body')).not.toHaveClass(/mrn-mobile-navigation-locked/);
	await page.setViewportSize({ width: 390, height: 844 });
	await expect(nav).toHaveAttribute('data-mrn-mobile-active', 'true');
	const closed = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
	expect(closed.violations).toEqual([]);
	await toggle.click();
	const open = await new AxeBuilder({ page }).include('nav').withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
	expect(open.violations).toEqual([]);
});

test('navigation links remain visible when JavaScript is disabled', async ({ browser }) => {
	const context = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 844 } });
	try {
		const page = await context.newPage();
		await page.goto(origin);
		await expect(page.locator('nav')).not.toHaveAttribute('data-mrn-mobile-active');
		await expect(page.getByRole('link', { name: 'Products', exact: true })).toBeVisible();
		await expect(page.getByRole('link', { name: 'Contact', exact: true })).toBeVisible();
	} finally {
		await context.close();
	}
});

test('older footer placement remains functional', async ({ page }) => {
	await page.setViewportSize({ width: 390, height: 844 });
	await page.goto(`${origin}/?late=1`);
	await page.getByRole('button', { name: 'Open navigation', exact: true }).click();
	await expect(page.locator('nav > .menu-toggle')).toHaveAttribute('aria-expanded', 'true');
});

test('initial enhancement stays still and user-triggered drawer motion remains available', async ({ page }) => {
	await page.setViewportSize({ width: 390, height: 844 });
	await page.addInitScript(() => {
		window.initialDrawerTransitions = [];
		document.addEventListener('transitionrun', event => {
			if (event.target.classList.contains('mrn-mobile-navigation__panel')) window.initialDrawerTransitions.push(event.propertyName);
		});
	});
	await page.goto(`${origin}/?late=1`);
	const panel = page.locator('.mrn-mobile-navigation__panel');
	expect(await panel.evaluate(element => getComputedStyle(element).visibility)).toBe('hidden');
	expect(await panel.evaluate(element => getComputedStyle(element).transitionDuration)).toBe('0s');
	expect(await page.evaluate(() => window.initialDrawerTransitions)).toEqual([]);
	await page.getByRole('button', { name: 'Open navigation', exact: true }).click();
	expect(await panel.evaluate(element => getComputedStyle(element).transitionDuration)).not.toBe('0s');
	await expect(panel).toBeVisible();
	await page.keyboard.press('Escape');
	await expect(panel).toBeHidden();
});

test('incomplete navigation falls back to its unenhanced state', async ({ page }) => {
	await page.goto(`${origin}/?missing=1`);
	await expect(page.locator('nav')).not.toHaveAttribute('data-mrn-mobile-active');
});
