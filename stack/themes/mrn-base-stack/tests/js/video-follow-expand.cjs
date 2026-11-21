/* Video follow color after an expanding video: full-row paint, fade in step with the video, small screens and reduced motion. */
const fs = require('node:fs'), path = require('node:path'), assert = require('node:assert/strict');
const { chromium } = require(process.env.MRN_PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname, '../..'), css = fs.readFileSync(root + '/css/presentation.css', 'utf8'), controller = fs.readFileSync(root + '/js/presentation.js', 'utf8');
const pair = (id, expand) => `<section id="${id}-video" class="mrn-content-builder__row" data-mrn-video-expand="true"${expand ? ' data-mrn-video-expand-from="600"' : ''} data-mrn-follow-color="--follow" data-mrn-follow-release="release">
<div class="mrn-layout-section"><div class="mrn-layout-container mrn-layout-surface"><div class="mrn-video-row__media" style="height:400px"></div></div></div></section>
<section id="${id}-next" class="mrn-content-builder__row"><div class="mrn-layout-section"><div class="mrn-layout-container mrn-layout-surface" style="max-width:600px;margin:0 auto;padding:40px 0"><h2>Heading</h2><p>Body copy.</p></div></div></section>
<div style="height:1600px"></div>`;
const shell = `<!doctype html><html lang="en"><head><title>Follow</title><style>${css}</style><style>:root{--follow:rgb(15,17,26)}body{margin:0;background:#fff;font:16px/1.5 Arial,sans-serif}</style></head><body><main>
<div style="height:1200px"></div>${pair('expand', true)}${pair('plain', false)}</main></body></html>`;
const frames = (page) => page.evaluate(() => new Promise((done) => requestAnimationFrame(() => requestAnimationFrame(done))));
// Scroll so the video media's top sits at the given viewport offset, then let the controller update.
const placeMedia = async (page, id, top) => {
	await page.evaluate(([i, t]) => { const s = document.getElementById(i + '-video'); scrollTo(0, s.getBoundingClientRect().top + scrollY - t); }, [id, top]);
	await frames(page); await page.evaluate(() => dispatchEvent(new Event('scroll'))); await frames(page);
};
const state = (page, id) => page.evaluate((i) => {
	const next = document.getElementById(i + '-next'), surface = next.querySelector('.mrn-layout-surface');
	const share = (value) => { const m = value.match(/var\(--follow\) ([0-9.]+)%/); return m ? Math.round(parseFloat(m[1])) : null; };
	return { row: share(next.style.backgroundColor), surface: share(surface.style.backgroundColor), light: next.classList.contains('is-mrn-follow-dark'), away: parseFloat(document.getElementById(i + '-video').style.getPropertyValue('--mrn-video-after-progress') || '0') };
}, id);
(async () => {
	const browser = await chromium.launch({ headless: true }); const checks = []; const expect = (ok, label) => { assert(ok, label); checks.push(label); };
	const open = async (options) => { const page = await browser.newPage(options); await page.route('**/*', (route) => route.abort()); await page.setContent(shell); await page.addScriptTag({ content: controller }); await frames(page); return page; };
	try {
		let page = await open({ viewport: { width: 1200, height: 800 } });
		await placeMedia(page, 'expand', 120); let s = await state(page, 'expand');
		expect(s.row === 100 && s.surface === null && s.light, 'Before the video passes the top, the full following row carries the color with light text: ' + JSON.stringify(s));
		await placeMedia(page, 'expand', -160); s = await state(page, 'expand');
		expect(Math.abs(s.row - Math.round((1 - s.away) * 100)) <= 1 && s.away > 0.4 && s.away < 0.6 && ! s.light, 'Halfway past the top, the row fades in step with the video row and text turns dark: ' + JSON.stringify(s));
		await placeMedia(page, 'expand', -500); s = await state(page, 'expand');
		expect(s.row === 0 && s.away === 1 && ! s.light, 'Once the video is past, the row has its own color back: ' + JSON.stringify(s));
		await placeMedia(page, 'plain', 120); s = await state(page, 'plain');
		expect(s.surface !== null && s.row === null, 'A video without a start width still colors only the following section surface: ' + JSON.stringify(s));
		await page.close();
		page = await open({ viewport: { width: 390, height: 844 } });
		await placeMedia(page, 'expand', 10); s = await state(page, 'expand');
		expect(s.row === 100 && s.light, 'At 767px and below the row keeps the color while the video top is in view: ' + JSON.stringify(s));
		await placeMedia(page, 'expand', -10); s = await state(page, 'expand');
		expect(s.row === 0 && ! s.light, 'and switches back as soon as the video top passes the top: ' + JSON.stringify(s));
		await page.close();
		page = await open({ viewport: { width: 1200, height: 800 }, reducedMotion: 'reduce' });
		await placeMedia(page, 'expand', 120); s = await state(page, 'expand');
		expect(s.row === null && ! s.light && s.away === 1, 'Reduced motion shows the settled colors without a transition color: ' + JSON.stringify(s));
		await page.close();
		console.log(JSON.stringify({ result: 'passed', assertions: checks.length, checks }, null, 2));
	} finally { await browser.close(); }
})().catch((error) => { console.error(error); process.exitCode = 1; });
