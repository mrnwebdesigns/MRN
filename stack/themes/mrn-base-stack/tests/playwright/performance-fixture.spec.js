const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
const fs = require('node:fs');
const http = require('node:http');
const path = require('node:path');

const themeRoot = path.resolve(__dirname, '../..');
let fixtureServer;
let fixtureOrigin;

function assetTags(mode) {
	const shared = [
		'<link rel="stylesheet" href="/style.css">',
		'<link rel="stylesheet" href="/css/mobile-navigation.css">',
		'<link rel="stylesheet" href="/css/layouts/hero.css">',
	];
	const legacyStyles = [
		'<link rel="stylesheet" href="/css/vendor/splide.min.css">',
		'<link rel="stylesheet" href="/css/vendor/glightbox.min.css">',
	];
	const sharedScripts = [ '<script src="/js/mobile-navigation.js"></script>' ];
	const sharedComponentScripts = [
		'<script src="/js/vendor/motion.js"></script>',
		'<script src="/js/front-end-effects.js"></script>',
		'<script src="/js/vendor/splide.min.js"></script>',
		'<script src="/js/front-end-tabs.js"></script>',
		'<script src="/js/vendor/glightbox.min.js"></script>',
		'<script src="/js/front-end-video-modal.js"></script>',
	];
	const legacyScripts = sharedComponentScripts.concat([ '<script src="/fixture-legacy-runtime.js"></script>' ]);
	const completeScripts = sharedComponentScripts.concat([
		'<script src="/js/front-end-slider.js"></script>',
		'<script src="/js/front-end-deferred-media.js"></script>',
		'<script src="/js/front-end-faq.js"></script>',
		'<script src="/js/front-end-gallery.js"></script>',
	]);

	return {
		styles: shared.concat(mode === 'legacy' || mode === 'all' ? legacyStyles : []).join('\n'),
		scripts: sharedScripts.concat(mode === 'legacy' ? legacyScripts : mode === 'all' ? completeScripts : []).join('\n'),
	};
}

function componentMarkup(mode) {
	if (mode !== 'all') {
		return '<section class="mrn-layout-section"><div class="mrn-layout-container"><h2>Simple content</h2><p>No interactive component is rendered on this fixture.</p></div></section>';
	}

	return `
		<section class="mrn-content-builder__row mrn-motion-effect--active-class" data-mrn-motion-effect="active-class" data-mrn-motion-target="row">
			<div class="splide mrn-splide" aria-label="Fixture slider" data-per-page="1" data-arrows="true" data-pagination="true">
				<div class="splide__track"><ul class="splide__list"><li class="splide__slide">First slide</li><li class="splide__slide">Second slide</li></ul></div>
			</div>
		</section>
		<section data-mrn-tabbed-layout class="mrn-tabbed-layout mrn-tabbed-layout--transition-slide">
			<div role="tablist" aria-label="Fixture tabs"><button id="fixture-tab-1" role="tab" aria-selected="true" aria-controls="fixture-panel-1" tabindex="0" data-mrn-tab-button>One</button><button id="fixture-tab-2" role="tab" aria-selected="false" aria-controls="fixture-panel-2" tabindex="-1" data-mrn-tab-button>Two</button></div>
			<div class="mrn-tabbed-layout__panels mrn-tabbed-layout__panels--slider splide" data-mrn-tab-slider><div class="splide__track"><div class="splide__list"><section class="splide__slide is-active" data-mrn-tab-panel><div id="fixture-panel-1" role="tabpanel" aria-labelledby="fixture-tab-1" data-mrn-tab-panel-content>Panel one</div></section><section class="splide__slide" data-mrn-tab-panel><div id="fixture-panel-2" role="tabpanel" aria-labelledby="fixture-tab-2" data-mrn-tab-panel-content>Panel two</div></section></div></div></div>
		</section>
		<details class="mrn-faq__item"><summary class="mrn-faq__question">Fixture question</summary><div class="mrn-faq__answer">Fixture answer</div></details>
		<section class="mrn-video-row"><a class="mrn-video-row__trigger glightbox" href="/media.svg" data-type="image" aria-label="Open fixture video poster"><img src="/media.svg" alt="" loading="lazy" width="640" height="360"></a></section>
		<section data-gallery-root data-gallery-group="fixture-gallery" data-gallery-lightbox-loop="false" data-gallery-lightbox-autoplay-video="false" data-gallery-lightbox-animation="fade"><figure data-gallery-item data-gallery-filters="all"><a class="glightbox" data-gallery="fixture-gallery" href="/media.svg"><img src="/media.svg" alt="Gallery fixture" loading="lazy" width="640" height="360"></a></figure></section>
		<div data-video-src="/media.svg" data-video-kind="remote" data-video-title="Deferred fixture" data-video-background="false" data-video-delay="0"></div>
	`;
}

function fixtureHtml(mode) {
	const assets = assetTags(mode);
	return `<!doctype html>
	<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Simple Stack performance fixture</title>
	<link rel="preload" href="/wordmark.svg" as="image" type="image/svg+xml" fetchpriority="high">
	${assets.styles}</head><body>
	<a class="skip-link screen-reader-text" href="#primary">Skip to content</a>
	<header class="site-header"><img src="/wordmark.svg" alt="Fixture wordmark" width="240" height="60"><nav data-mrn-mobile-navigation data-submenu-open-label="Open %s submenu" data-submenu-close-label="Close %s submenu" aria-label="Primary menu"><button class="menu-toggle" aria-controls="fixture-menu" aria-expanded="false" aria-label="Open navigation" data-close-label="Close navigation">Menu</button><div class="mrn-mobile-navigation__panel"><ul id="fixture-menu" class="menu"><li><a href="#primary">Content</a></li></ul></div></nav></header>
	<nav class="mrn-breadcrumbs" aria-label="Breadcrumb"><a href="/">Home</a><span aria-hidden="true">/</span><span>Fixture</span></nav>
	<main id="primary"><section class="mrn-hero"><div class="mrn-hero__inner"><h1>Fast, reusable Stack fixture</h1><img src="/hero.svg" alt="Abstract performance bars" width="1200" height="675" loading="eager" fetchpriority="high" decoding="async"></div></section>${componentMarkup(mode)}
	<form aria-label="Fixture contact form" onsubmit="event.preventDefault()"><label for="fixture-email">Email</label><input id="fixture-email" type="email" autocomplete="email"><button type="submit">Send</button></form>
	<img src="/media.svg?one" alt="Below-fold fixture one" width="640" height="360" loading="lazy" decoding="async"><img src="/media.svg?two" alt="Below-fold fixture two" width="640" height="360" loading="lazy" decoding="async">
	</main><footer class="site-footer">Fixture footer</footer>${assets.scripts}</body></html>`;
}

function svg(width, height, label, color) {
	return `<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}" viewBox="0 0 ${width} ${height}"><rect width="100%" height="100%" fill="${color}"/><text x="50%" y="50%" text-anchor="middle" dominant-baseline="middle" font-family="system-ui" font-size="48" fill="white">${label}</text></svg>`;
}

test.beforeAll(async () => {
	fixtureServer = http.createServer((request, response) => {
		const requestUrl = new URL(request.url, 'http://127.0.0.1');
		if (requestUrl.pathname === '/fixture') {
			const body = fixtureHtml(requestUrl.searchParams.get('mode') || 'optimized');
			response.writeHead(200, { 'content-type': 'text/html; charset=utf-8', 'content-length': Buffer.byteLength(body), 'cache-control': 'no-store' });
			response.end(body);
			return;
		}

		if (requestUrl.pathname === '/hero.svg' || requestUrl.pathname === '/wordmark.svg' || requestUrl.pathname === '/media.svg') {
			const body = requestUrl.pathname === '/hero.svg' ? svg(1200, 675, 'Hero', '#17324d') : requestUrl.pathname === '/wordmark.svg' ? svg(240, 60, 'MRN', '#1c222b') : svg(640, 360, 'Media', '#385f71');
			response.writeHead(200, { 'content-type': 'image/svg+xml', 'content-length': Buffer.byteLength(body), 'cache-control': 'no-store' });
			response.end(body);
			return;
		}

		if (requestUrl.pathname === '/fixture-legacy-runtime.js') {
			const body = [ 'js/front-end-deferred-media.js', 'js/front-end-slider.js', 'js/front-end-faq.js' ]
				.map((relativePath) => fs.readFileSync(path.resolve(themeRoot, relativePath)))
				.join('\n');
			response.writeHead(200, { 'content-type': 'application/javascript', 'content-length': Buffer.byteLength(body), 'cache-control': 'no-store' });
			response.end(body);
			return;
		}

		const relativePath = requestUrl.pathname.replace(/^\//, '');
		const filePath = path.resolve(themeRoot, relativePath);
		if (!filePath.startsWith(themeRoot + path.sep) || !fs.existsSync(filePath) || !fs.statSync(filePath).isFile()) {
			response.writeHead(404);
			response.end('Not found');
			return;
		}

		const contentType = filePath.endsWith('.css') ? 'text/css' : 'application/javascript';
		const body = fs.readFileSync(filePath);
		response.writeHead(200, { 'content-type': contentType, 'content-length': body.length, 'cache-control': 'no-store' });
		response.end(body);
	});

	await new Promise((resolve) => fixtureServer.listen(0, '127.0.0.1', resolve));
	fixtureOrigin = `http://127.0.0.1:${fixtureServer.address().port}`;
});

test.afterAll(async () => {
	if (fixtureServer) {
		await new Promise((resolve) => fixtureServer.close(resolve));
	}
});

async function collectMobileMetrics(browser, mode) {
	const context = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 1, isMobile: true, hasTouch: true });
	const page = await context.newPage();
	const client = await context.newCDPSession(page);
	let transferredBytes = 0;
	let requestCount = 0;

	await client.send('Network.enable');
	await client.send('Network.setCacheDisabled', { cacheDisabled: true });
	await client.send('Network.emulateNetworkConditions', { offline: false, latency: 150, downloadThroughput: 1.6 * 1024 * 1024 / 8, uploadThroughput: 750 * 1024 / 8, connectionType: 'cellular3g' });
	await client.send('Emulation.setCPUThrottlingRate', { rate: 4 });
	client.on('Network.responseReceived', () => { requestCount += 1; });
	client.on('Network.loadingFinished', (event) => { transferredBytes += event.encodedDataLength || 0; });

	await page.addInitScript(() => {
		window.__mrnFixtureMetrics = { cls: 0, inp: 0, lcp: 0, longTasks: [] };
		new PerformanceObserver((list) => { const entries = list.getEntries(); const last = entries[entries.length - 1]; if (last) window.__mrnFixtureMetrics.lcp = last.startTime; }).observe({ type: 'largest-contentful-paint', buffered: true });
		new PerformanceObserver((list) => { list.getEntries().forEach((entry) => { if (!entry.hadRecentInput) window.__mrnFixtureMetrics.cls += entry.value; }); }).observe({ type: 'layout-shift', buffered: true });
		new PerformanceObserver((list) => { list.getEntries().forEach((entry) => window.__mrnFixtureMetrics.longTasks.push(entry.duration)); }).observe({ type: 'longtask', buffered: true });
		try {
			new PerformanceObserver((list) => { list.getEntries().forEach((entry) => { window.__mrnFixtureMetrics.inp = Math.max(window.__mrnFixtureMetrics.inp, entry.duration || 0); }); }).observe({ type: 'event', buffered: true, durationThreshold: 16 });
		} catch (error) {}
	});

	await page.goto(`${fixtureOrigin}/fixture?mode=${mode}`, { waitUntil: 'networkidle' });
	await page.getByRole('button', { name: 'Send' }).click();
	await page.waitForTimeout(500);
	const browserMetrics = await page.evaluate(() => {
		const fcp = performance.getEntriesByName('first-contentful-paint')[0];
		const metricState = window.__mrnFixtureMetrics;
		return {
			fcp: fcp ? fcp.startTime : 0,
			lcp: metricState.lcp,
			cls: metricState.cls,
			inp: metricState.inp,
			tbt: metricState.longTasks.reduce((total, duration) => total + Math.max(0, duration - 50), 0),
			renderBlockingResources: document.head.querySelectorAll('link[rel="stylesheet"], script:not([async]):not([defer]):not([type="module"])').length,
		};
	});

	await context.close();
	return { mode, requestCount, transferredBytes, ...browserMetrics };
}

test('simple Stack fixture removes the legacy component bundle under mobile throttling', async ({ browser }, testInfo) => {
	const legacy = await collectMobileMetrics(browser, 'legacy');
	const optimized = await collectMobileMetrics(browser, 'optimized');

	await testInfo.attach('mobile-performance-evidence.json', {
		body: JSON.stringify({ legacy, optimized }, null, 2),
		contentType: 'application/json',
	});
	expect(optimized.requestCount).toBeLessThan(legacy.requestCount);
	expect(optimized.transferredBytes).toBeLessThan(legacy.transferredBytes);
	expect(optimized.renderBlockingResources).toBeLessThan(legacy.renderBlockingResources);
	expect(optimized.cls).toBeLessThanOrEqual(0.1);
});

test('component fixture preserves navigation, tabs, sliders, gallery, forms, and image contracts', async ({ page }) => {
	await page.setViewportSize({ width: 390, height: 844 });
	await page.goto(`${fixtureOrigin}/fixture?mode=all`, { waitUntil: 'networkidle' });

	await expect(page.getByRole('banner')).toBeVisible();
	const slider = page.locator('.mrn-splide');
	await expect(slider).toHaveAttribute('data-mrn-slider-mounted', 'true');
	await slider.locator('.splide__arrow--next').click();
	await expect(slider.locator('.splide__slide').nth(1)).toHaveClass(/is-active/);

	await page.getByRole('tab', { name: 'One' }).focus();
	await page.keyboard.press('ArrowRight');
	await expect(page.getByRole('tab', { name: 'Two' })).toHaveAttribute('aria-selected', 'true');
	await expect(page.getByRole('tab', { name: 'Two' })).toBeFocused();

	const menuButton = page.getByRole('button', { name: 'Open navigation' });
	await menuButton.click();
	await expect(page.locator('[data-mrn-mobile-navigation]')).toHaveClass(/is-open/);
	await page.keyboard.press('Escape');
	await expect(page.locator('[data-mrn-mobile-navigation]')).not.toHaveClass(/is-open/);

	await expect(page.getByRole('navigation', { name: 'Breadcrumb' })).toBeVisible();
	await expect(page.locator('footer.site-footer')).toBeVisible();
	await expect(page.getByLabel('Email')).toBeVisible();
	await page.getByLabel('Email').focus();
	await expect(page.getByLabel('Email')).toBeFocused();
	await expect(page.getByRole('button', { name: 'Send' })).toBeVisible();
	await expect(page.locator('.mrn-hero img')).toHaveAttribute('loading', 'eager');
	await expect(page.locator('.mrn-hero img')).toHaveAttribute('fetchpriority', 'high');
	await expect(page.locator('main > img')).toHaveCount(2);
	await expect(page.locator('main > img').first()).toHaveAttribute('loading', 'lazy');
	await expect(page.locator('link[rel="preload"][href="/wordmark.svg"][type="image/svg+xml"]')).toHaveCount(1);
	await page.locator('[data-video-src]').scrollIntoViewIfNeeded();
	await expect(page.locator('[data-video-src] iframe')).toHaveAttribute('loading', 'lazy');
	await expect(page.locator('html')).toHaveClass(/mrn-motion-effects-ready/);

	await page.locator('[data-gallery-root] .glightbox').click();
	await expect(page.locator('.glightbox-container')).toBeVisible();
	await page.keyboard.press('Escape');
	await expect(page.locator('.glightbox-container')).toBeHidden();

	await page.getByRole('link', { name: 'Open fixture video poster' }).click();
	await expect(page.locator('.glightbox-container')).toBeVisible();
	await page.keyboard.press('Escape');
	await expect(page.locator('.glightbox-container')).toBeHidden();

	const accessibility = await new AxeBuilder({ page }).withTags([ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa' ]).analyze();
	expect(accessibility.violations).toEqual([]);
});

test('FAQ interaction remains keyboard-safe with reduced motion', async ({ page }) => {
	await page.emulateMedia({ reducedMotion: 'reduce' });
	await page.goto(`${fixtureOrigin}/fixture?mode=all`, { waitUntil: 'networkidle' });
	const question = page.getByText('Fixture question');
	await question.focus();
	await page.keyboard.press('Enter');
	await expect(page.locator('.mrn-faq__item')).toHaveAttribute('open', '');
	await expect(page.locator('.mrn-faq__answer')).toHaveAttribute('style', /height:\s*auto/);
	await expect(page.locator('.mrn-motion-effect--active-class')).toHaveClass(/is-mrn-in-view/);
});
