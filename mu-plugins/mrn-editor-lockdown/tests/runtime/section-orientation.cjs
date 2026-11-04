'use strict';
// Use a private short-lived native QA session after the exact review-site backup gate.
const fs = require('fs'), assert = require('assert/strict'), path = require('path');
const req = require('module').createRequire('/Users/khofmeyer/Development/MRN-qa-engine/package.json');
const { chromium, expect: baseExpect } = req('@playwright/test'), Axe = req('@axe-core/playwright').default;
const expect = baseExpect.configure({ timeout: 20000 });
const base = 'https://admin-editor-pilot.mrndev.io', phase = process.argv[2] || 'candidate';
const out = path.resolve('docs/admin-editor-pilot/appearance/review/section-orientation-20261104');
const assets = ['mrn-admin-ui-css/mrn-admin-shell.css', 'mrn-admin-ui-css/mrn-editor-workspace.css', 'mrn-editor-lockdown/assets/section-navigator.js'];
assert(process.env.MRN_QA_AUTH_FILE, 'Set the private QA session file');
(async () => {
 const browser = await chromium.launch(), context = await browser.newContext({ viewport: { width: 1487, height: 1000 } });
 await context.addCookies(JSON.parse(fs.readFileSync(process.env.MRN_QA_AUTH_FILE)));
 if (phase === 'candidate') for (const file of assets) await context.route('**/mu-plugins/' + file + '*', route => route.fulfill({ status: 200, contentType: file.endsWith('.js') ? 'text/javascript' : 'text/css', body: fs.readFileSync('mu-plugins/' + file, 'utf8') }));
 const page = await context.newPage(), result = { phase, checks: [], rows: [], contrast: [], axe: [], errors: [] };
 page.setDefaultTimeout(20000); page.on('pageerror', e => result.errors.push(e.message));
 const row = '.mrn-editor-section:visible', heading = row + ' > .acf-fc-layout-actions-wrap';
 async function settle() { await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)))); }
 async function go(id) { const response = await page.goto(base + '/wp-admin/post.php?post=' + id + '&action=edit', { waitUntil: 'load', timeout: 120000 }); assert.equal(response.status(), 200); await expect(page.locator('body')).toHaveClass(/mrn-workspace/); }
 async function values() { return page.locator('#post').evaluate(form => Array.from(new FormData(form)).filter(([key]) => key.startsWith('acf[') || ['post_title', 'post_content', 'post_name', 'post_parent', 'page_template'].includes(key)).map(([key, value]) => [key, typeof value === 'string' ? value : 'file'])); }
 async function bands(label) {
  await settle(); const fields = await page.locator(row + ' > .acf-fields > .acf-field').evaluateAll(nodes => nodes.filter(e => e.getClientRects().length && e.getBoundingClientRect().height).map(e => ({ name: e.dataset.name, top: e.getBoundingClientRect().top, band: e.classList.contains('mrn-field-band'), background: getComputedStyle(e).backgroundColor })));
  let top, index = -1; for (const field of fields) { if (top === undefined || Math.abs(field.top - top) > 1) { index++; top = field.top; } if (phase !== 'baseline') assert.equal(field.band, index % 2 === 1, label + ': ' + field.name); }
  result.rows.push({ label, count: index + 1, fields });
 }
 function contrast(a, b) { const luminance = color => color.match(/[\d.]+/g).slice(0, 3).map(v => { const n = +v / 255; return n <= .04045 ? n / 12.92 : ((n + .055) / 1.055) ** 2.4; }).reduce((s, v, i) => s + v * [.2126, .7152, .0722][i], 0); const x = luminance(a), y = luminance(b); return (Math.max(x, y) + .05) / (Math.min(x, y) + .05); }
 try {
  await go(2523); await page.locator('.mrn-section-link').nth(1).click(); await settle(); const original = await values();
  for (const theme of ['light', 'dark']) {
   await page.locator('html').evaluate((e, mode) => e.dataset.mrnAppearance = mode, theme);
   await page.evaluate(() => { document.activeElement.blur(); scrollTo(0, 260); }); await page.mouse.move(10, 10); await bands(theme + ' content');
   await page.screenshot({ path: out + '/' + phase + '-' + theme + '-fields.png' });
   // Isolate ACF's active-row styling, the state shown in the owner's badge capture.
   await page.locator(row).evaluate(e => e.classList.add('active-layout'));
   const badge = await page.locator(heading + ' .acf-fc-layout-order').evaluate(e => ({ text: getComputedStyle(e).color, background: getComputedStyle(e).backgroundColor }));
   const ratio = contrast(badge.text, badge.background); result.contrast.push({ theme, ratio }); if (phase !== 'baseline') assert(ratio >= 4.5);
   await page.locator(heading).hover(); await expect(page.locator(heading + ' .acf-fc-layout-order')).toBeHidden(); await expect(page.locator(heading + ' .acf-fc-layout-draggable-icon')).toBeVisible();
   await page.mouse.move(10, 10); await page.locator(row).evaluate(e => e.classList.remove('active-layout'));
   await page.evaluate(selector => { const e = document.querySelector(selector); scrollTo(0, e.getBoundingClientRect().top + scrollY + 450); }, '.mrn-editor-section:not(.mrn-section-parked)'); await settle();
   const geometry = await page.locator(heading).evaluate(e => ({ top: e.getBoundingClientRect().top, bottom: e.getBoundingClientRect().bottom, chrome: document.querySelector('.mrn-workspace-header').getBoundingClientRect().bottom, position: getComputedStyle(e).position }));
   result.checks.push({ theme, geometry }); if (phase !== 'baseline') { assert.equal(geometry.position, 'sticky'); assert(Math.abs(geometry.top - geometry.chrome) <= 1); }
   await page.screenshot({ path: out + '/' + phase + '-' + theme + '-scrolled.png' });
   if (phase !== 'baseline') {
    const input = page.locator(row + ' > .acf-fields > .acf-field input[type=text]').first();
    await input.evaluate(e => { const header = e.closest('.layout').querySelector('.acf-fc-layout-actions-wrap'); scrollBy(0, e.getBoundingClientRect().top - header.getBoundingClientRect().top - 4); e.blur(); e.focus({ preventScroll: true }); }); await settle();
    assert(await input.evaluate(e => e.getBoundingClientRect().top >= e.closest('.layout').querySelector('.acf-fc-layout-actions-wrap').getBoundingClientRect().bottom + 10));
    const media = page.locator(row + ' .insert-media').first();
    await media.evaluate(e => { const header = e.closest('.layout').querySelector('.acf-fc-layout-actions-wrap'); scrollBy(0, e.getBoundingClientRect().top - header.getBoundingClientRect().top - 4); e.blur(); e.focus({ preventScroll: true }); }); await settle();
    assert(await media.evaluate(e => e.getBoundingClientRect().top >= e.closest('.layout').querySelector('.acf-fc-layout-actions-wrap').getBoundingClientRect().bottom + 10));
    const findings = (await new Axe({ page }).include('.mrn-editor-section:not(.mrn-section-parked) > .acf-fc-layout-actions-wrap').include('.mrn-editor-section:not(.mrn-section-parked) > .acf-fields > .acf-field-text').include('.mrn-editor-section:not(.mrn-section-parked) > .acf-fields > .acf-field-select').withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze()).violations;
    result.axe.push({ theme, violations: findings.map(v => ({ id: v.id, targets: v.nodes.map(n => n.target) })) }); assert.equal(findings.length, 0);
   }
  }
  const tabs = row + ' > .acf-fields > .acf-tab-wrap';
  await page.locator(tabs + ' a').filter({ hasText: /^Configs$/ }).click(); await bands('Configs visible rows');
  await page.locator(tabs + ' a').filter({ hasText: /^Content$/ }).click(); await bands('Return to Content');
  assert.deepEqual(await values(), original); result.checks.push('Native tabs preserve fields and values; bands follow actual visible rows and paired columns');
  await page.setViewportSize({ width: 390, height: 844 }); await page.locator('html').evaluate(e => e.dataset.mrnAppearance = 'light'); await settle(); await bands('390px reflow');
  if (phase !== 'baseline') assert.notEqual(await page.locator(heading).evaluate(e => getComputedStyle(e).position), 'sticky');
  await page.evaluate(() => scrollTo(0, 250)); await page.screenshot({ path: out + '/' + phase + '-mobile.png' });
  await page.setViewportSize({ width: 1487, height: 500 }); await settle(); if (phase !== 'baseline') assert.notEqual(await page.locator(heading).evaluate(e => getComputedStyle(e).position), 'sticky');
  await page.setViewportSize({ width: 1487, height: 1000 }); await page.locator('.mrn-view-mode').click(); await expect(page.locator('body')).toHaveClass(/mrn-workspace-overview/);
  if (phase !== 'baseline') assert.equal(await page.locator('.mrn-editor-section > .acf-fc-layout-actions-wrap').evaluateAll(es => es.filter(e => getComputedStyle(e).position === 'sticky').length), 0);
  await page.locator('.mrn-section-link').nth(0).click(); await bands('Nested card section');
  await page.locator('.mrn-mode-settings').click(); await expect(page.locator('#pageparentdiv')).toBeVisible(); await page.locator('.mrn-mode-content').click();
  assert.deepEqual(await values(), original); result.checks.push('Short/mobile viewports and arrangement retain normal scrolling; Page Settings remains available; native form values unchanged');
  await go(2580); await expect(page.locator('body')).toHaveClass(/mrn-workspace-empty/); await expect(page.locator('.mrn-region-add').first()).toBeVisible(); result.checks.push('Empty fixture retains native Add section controls without a sticky heading');
  assert.deepEqual(result.errors, []); result.passed = true;
 } catch (error) { result.failure = error.message; await page.screenshot({ path: out + '/' + phase + '-failure.png' }); throw error; }
 finally { fs.writeFileSync(out + '/' + phase + '-results.json', JSON.stringify(result, null, 2)); await browser.close(); }
})().catch(error => { console.error(error.message); process.exitCode = 1; });
