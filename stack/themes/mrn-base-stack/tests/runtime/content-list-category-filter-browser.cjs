/* Local-only editor save/reload and frontend integration checks. */
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium, expect } = require('@playwright/test');
const { AxeBuilder } = require('@axe-core/playwright');
const state = JSON.parse(fs.readFileSync(process.env.MRN_CATEGORY_FIXTURE, 'utf8'));
const login = JSON.parse(fs.readFileSync(process.env.MRN_CATEGORY_LOGIN, 'utf8'));
const evidence = process.env.MRN_CATEGORY_EVIDENCE;
assert(new URL(state.home).hostname.endsWith('.localhost'));
(async () => {
 const browser = await chromium.launch();
 const errors = [];
 const results = [];
 let page;
 try {
  const context = await browser.newContext({ reducedMotion: 'reduce', viewport: { width: 1440, height: 1000 } });
  page = await context.newPage();
  page.on('pageerror', error => errors.push(error.message));
  page.on('dialog', dialog => dialog.accept());
  await page.goto(state.home + '/wp-login.php');
  await page.locator('#user_login').fill(login.username);
  await page.locator('#user_pass').fill(login.password);
  await Promise.all([page.waitForURL('**/wp-admin/**', { waitUntil: 'domcontentloaded', timeout: 120000 }), page.locator('#wp-submit').click()]);
  const row = () => page.locator('.acf-field[data-name="page_content_rows"] .layout[data-layout="content_lists"]:not(.acf-clone)').first();
  const field = name => row().locator('.acf-field[data-name="' + name + '"]').first();
  const select = name => field(name).locator('select').first();
  async function openEditor() {
   await page.goto(state.edit_url, { waitUntil: 'domcontentloaded' });
   await expect(select('filter_taxonomy')).toHaveValue('post_tag');
   await expect(page.locator('html')).not.toHaveClass(/mrn-editor-loading-indicator-live/, {timeout: 120000});
   const config = row().locator('.acf-tab-button').filter({ hasText: 'Configs' }).first();
   if (!await config.isVisible()) await row().locator('.acf-fc-layout-handle').first().click();
   await config.click();
   const category = field('category_filter_source');
   if (!await category.isVisible()) {
    const accordion = category.locator('xpath=ancestor::*[contains(concat(" ", normalize-space(@class), " "), " acf-accordion ")][1]');
    await accordion.locator('> .acf-accordion-title').click();
   }
   await expect(category).toBeVisible();
  }
  async function save() {
   await Promise.all([page.waitForURL(url => url.searchParams.get('message') === '1'), page.locator('#publish').click()]);
   await openEditor();
  }
  await openEditor();
  await expect(select('category_filter_source')).toHaveValue('manual_terms');
  await expect(field('category_filter_term_slugs').locator('input[type="text"]')).toHaveValue('mrn-catqa-news');
  // Prove the new field's ACF visibility conditions and actual persisted edits.
  await select('category_filter_source').selectOption('none', { force: true });
  await expect(field('category_filter_term_slugs')).toBeHidden();
  await select('category_filter_source').selectOption('manual_terms', { force: true });
  await expect(field('category_filter_term_slugs')).toBeVisible();
  await field('category_filter_term_slugs').locator('input[type="text"]').fill('mrn-catqa-news,mrn-catqa-updates');
  await select('category_filter_match').selectOption('all', { force: true });
  await save();
  await expect(select('category_filter_match')).toHaveValue('all');
  await expect(field('category_filter_term_slugs').locator('input[type="text"]')).toHaveValue('mrn-catqa-news,mrn-catqa-updates');
  results.push('Real editor category changes save and reload alongside existing tags');
  const publicPage = await context.newPage();
  await publicPage.goto(state.url);
  const text = await publicPage.locator('.mrn-content-list-row__items').innerText();
  assert(text.includes('Category QA two_categories') && !text.includes('Category QA both'));
  results.push('Saved match-all category filter returns only the qualifying item on the frontend');
  await page.bringToFront();
  await openEditor();
  await field('category_filter_term_slugs').locator('input[type="text"]').fill('mrn-catqa-news');
  await select('category_filter_match').selectOption('any', { force: true });
  await save();
  // Page has no categories in the native registration: source changes must not discard values.
  await select('list_post_type').selectOption('page', { force: true });
  await expect(field('category_filter_source')).toBeHidden();
  await select('list_post_type').selectOption('post', { force: true });
  await expect(field('category_filter_source')).toBeVisible();
  await expect(select('category_filter_source')).toHaveValue('manual_terms');
  await expect(field('category_filter_term_slugs').locator('input[type="text"]')).toHaveValue('mrn-catqa-news');
  results.push('Unsupported sources hide category controls without discarding the saved selection');
  await openEditor();
  await field('category_filter_source').scrollIntoViewIfNeeded();
  await page.screenshot({ path: path.join(evidence, 'editor-category-filter.png') });
  const a11y = await new AxeBuilder({ page }).include('.acf-field[data-name="category_filter_source"]').include('.acf-field[data-name="category_filter_match"]').include('.acf-field[data-name="category_filter_term_slugs"]').withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();
  fs.writeFileSync(path.join(evidence, 'editor-accessibility.json'), JSON.stringify(a11y.violations, null, 2));
  assert.equal(a11y.violations.length, 0, 'New category controls pass axe');
  results.push('New category controls pass axe WCAG A/AA checks');
  const anonymous = await browser.newContext();
  const frontend = await anonymous.newPage();
  frontend.on('pageerror', error => errors.push(error.message));
  await frontend.goto(state.url);
  const items = frontend.locator('.mrn-content-list-row__items');
  await expect(items).toContainText('Category QA both');
  await expect(items).toContainText('Category QA child');
  await expect(items).not.toContainText('Category QA tag_only');
  await expect(items).not.toContainText('Category QA category_only');
  for (const [name, width] of [['desktop',1440],['tablet',768],['mobile',390]]) {
   await frontend.setViewportSize({width,height:1000});
   await frontend.screenshot({path:path.join(evidence, 'frontend-' + name + '.png')});
  }
  results.push('Anonymous desktop/tablet/mobile frontend retains only combined matches');
  assert.deepEqual(errors, []);
  fs.writeFileSync(path.join(evidence, 'browser-results.json'), JSON.stringify({results, errors}, null, 2));
  console.log(results.join('\n'));
 } catch (error) {
  if (page) {
   await page.screenshot({path: path.join(evidence, 'browser-failure.png')});
   console.log(await page.locator('.acf-field[data-name="category_filter_source"]').first().evaluate(el => { const result=[]; for(let p=el;p;p=p.parentElement) {result.push({tag:p.tagName,classes:p.className,display:getComputedStyle(p).display});} return result; }));
  }
  throw error;
 } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
