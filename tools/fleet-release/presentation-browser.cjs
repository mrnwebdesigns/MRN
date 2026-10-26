/* Installed optional presentations: whole-page AA, keyboard, motion and persistence. */
'use strict';
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const {createRequire} = require('node:module');

(async () => {
  const input = JSON.parse(fs.readFileSync(0, 'utf8'));
  const origin = new URL(input.url);
  assert.equal(origin.hostname, '127.0.0.1');
  assert.equal(new URL(input.presentation.url).origin, origin.origin);
  const dependencies = createRequire(path.join(input.engine_root, 'package.json'));
  const {chromium, expect} = dependencies('@playwright/test');
  const AxeBuilder = dependencies('@axe-core/playwright').default;
  const browser = await chromium.launch();
  const checks = [], scans = [], errors = [];
  let page;
  async function context(options = {}) {
    const value = await browser.newContext(options);
    await value.route('**/*', route => new URL(route.request().url()).origin === origin.origin ? route.continue() : route.abort());
    return value;
  }
  try {
    for (const reducedMotion of ['reduce', 'no-preference']) {
      const ctx = await context({reducedMotion});
      page = await ctx.newPage();
      page.on('pageerror', error => errors.push(error.message));
      for (const width of [1440, 768, 390]) {
        await page.setViewportSize({width, height: 1000});
        await page.goto(input.presentation.url, {waitUntil: 'networkidle'});
        const responsive = page.locator('[data-mrn-tab-accordion]');
        await expect(responsive).toHaveCount(1);
        if (width > 767) {
          const tabs = responsive.getByRole('tab');
          await expect(tabs).toHaveCount(4);
          await tabs.first().focus();
          await page.keyboard.press('ArrowRight');
          await expect(tabs.nth(1)).toBeFocused();
          await expect(tabs.nth(1)).toHaveAttribute('aria-selected', 'true');
          await expect(responsive.getByRole('tabpanel', {name: 'Panel 2', exact: true})).toBeVisible();
        } else {
          const summaries = responsive.locator('summary');
          await summaries.nth(2).focus();
          await page.keyboard.press('Enter');
          await expect(responsive.locator('[data-mrn-tab-drawer][open]')).toHaveCount(1);
          await expect(responsive.locator('[data-mrn-tab-drawer][open]')).toContainText('Unique panel copy 3');
        }
        const faq = page.locator('.has-faq-frame .mrn-faq__question');
        await expect(faq).toHaveCount(2);
        await faq.first().focus();
        await page.keyboard.press('Enter');
        await expect(page.locator('.has-faq-frame details[open]')).toHaveCount(1);
        await faq.nth(1).focus();
        await page.keyboard.press('Enter');
        await expect(page.locator('.has-faq-frame details[open]')).toHaveCount(1);
        await expect(page.locator('.has-faq-frame details[open]')).toContainText('Second answer');
        await expect(page.locator('.has-auto-grid-logos img')).toHaveCount(8);
        await expect(page.locator('.has-auto-grid-logos .splide')).toHaveCount(0);
        await expect(page.locator('[data-mrn-scroll-words]')).toContainText('Keep emphasis, strong text and this link.');
        await expect(page.locator('[data-mrn-scroll-words] em')).toHaveText('emphasis');
        await expect(page.locator('[data-mrn-scroll-words] strong')).toHaveText('strong text');
        await expect(page.locator('[data-mrn-scroll-words] a')).toHaveAttribute('href', /\/about\/\?one=1&two=2$/);
        await expect(page.locator('blockquote')).toContainText('Preserve the authored quotation');
        await expect(page.getByRole('link', {name: 'Continue to About', exact: true})).toBeVisible();
        const swipe = page.locator('[data-mrn-tabbed-layout]:not([data-mrn-tab-accordion])');
        const swipeTabs = swipe.getByRole('tab');
        await expect(swipeTabs).toHaveCount(6);
        await swipeTabs.first().focus();
        for (let index = 1; index < 6; index++) {
          await page.keyboard.press('ArrowRight');
          await expect(swipeTabs.nth(index)).toBeFocused();
          await expect(swipeTabs.nth(index)).toHaveAttribute('aria-selected', 'true');
          await expect(swipe.getByRole('tabpanel', {name: `Swipe ${index + 1}`, exact: true})).toBeVisible();
          await expect(swipe.locator('.is-mrn-tab-text-swiping')).toHaveCount(0);
        }
        const words = page.locator('[data-mrn-scroll-words]');
        await words.scrollIntoViewIfNeeded();
        if (reducedMotion === 'reduce') {
          assert(await words.locator('.mrn-scroll-word').evaluateAll(nodes => nodes.length > 0 && nodes.every(node => getComputedStyle(node).color === 'rgb(34, 34, 34)')));
          assert(await page.locator('[data-mrn-image-drift] img').evaluateAll(nodes => nodes.every(node => !node.style.translate)));
        }
        assert(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1), 'No horizontal page overflow');
        const axe = await new AxeBuilder({page}).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
        scans.push({width, reducedMotion, violations: axe.violations});
        assert.equal(axe.violations.length, 0, JSON.stringify(axe.violations.map(v => ({id: v.id, nodes: v.nodes.map(n => n.target)}))));
        await page.screenshot({path: path.join(path.dirname(input.output), `presentations-${width}-${reducedMotion}.png`), fullPage: true});
        checks.push(`responsive/keyboard/content/AA ${width} ${reducedMotion}`);
      }
      await ctx.close();
    }
    const fallback = await context({javaScriptEnabled: false, viewport: {width: 390, height: 1000}});
    page = await fallback.newPage();
    await page.goto(input.presentation.url);
    const native = page.locator('[data-mrn-tab-drawer]');
    await expect(native).toHaveCount(4);
    await native.nth(3).locator('summary').click();
    await expect(native.nth(3)).toHaveAttribute('open', '');
    await expect(native.nth(3)).toContainText('Unique panel copy 4');
    checks.push('native disclosure and authored content remain usable without JavaScript');
    await fallback.close();

    const admin = await context({reducedMotion: 'reduce', viewport: {width: 1440, height: 1100}});
    page = await admin.newPage();
    await page.goto(input.login_url, {waitUntil: 'networkidle'});
    await page.locator('#user_login').fill(input.username);
    await page.locator('#user_pass').fill(input.password);
    await Promise.all([page.waitForURL(url => url.pathname !== new URL(input.login_url).pathname), page.locator('#wp-submit').click()]);
    const editor = `${input.url}/wp-admin/post.php?post=${input.presentation.page}&action=edit`;
    for (let attempt = 0; attempt < 4; attempt++) {
      await page.goto(editor, {waitUntil: 'networkidle'});
      const onboarding = new URL(page.url()).searchParams.get('page');
      if (onboarding === 'wpforms-getting-started') continue;
      if (onboarding === 'seopress-setup') { await page.getByRole('link', {name: 'Skip for now', exact: true}).click(); continue; }
      break;
    }
    await expect(page.locator('#post')).toBeVisible();
    const row = page.locator('[data-key="field_mrn_page_content_rows"] > .acf-input > .acf-flexible-content > .values > .layout:not(.acf-clone)').first();
    async function openRow() {
      if ((await row.getAttribute('class')).split(/\s+/).includes('-collapsed')) await row.locator('.acf-fc-layout-handle').first().click();
    }
    await openRow();
    const heading = row.locator('.acf-field[data-name="heading"] input').first();
    await expect(heading).toHaveValue('Optional presentations');
    await heading.fill('Presentation settings saved through Classic Editor');
    const [saved] = await Promise.all([
      page.waitForResponse(response => response.request().method() === 'POST'
        && new URL(response.url()).pathname === '/wp-admin/post.php'),
      page.locator('#publish').click(),
    ]);
    assert.equal(saved.status(), 302, 'Native save must redirect successfully');
    await expect(page.locator('#message')).toContainText('Page updated.');
    await page.reload({waitUntil: 'networkidle'});
    await openRow();
    await expect(heading).toHaveValue('Presentation settings saved through Classic Editor');
    await expect(row.locator('.acf-field[data-name="text_presentation"] select').first()).toHaveValue('scroll-words');
    await expect(row.locator('.acf-field[data-name="word_reveal_from"] select').first()).toHaveValue('medium-gray');
    await page.goto(input.presentation.url, {waitUntil: 'networkidle'});
    await expect(page.locator('[data-mrn-tabbed-layout]:not([data-mrn-tab-accordion])').getByRole('tab')).toHaveCount(6);
    await expect(page.locator('[data-mrn-tab-accordion]')).toHaveCount(1);
    await expect(page.locator('[data-mrn-scroll-words] em')).toHaveText('emphasis');
    checks.push('Classic Editor save/reload preserves presentation settings and authored heading');
    await admin.close();
    assert.deepEqual(errors, [], 'No browser runtime errors');
    fs.writeFileSync(input.output, JSON.stringify({status: 'pass', checks, scans, errors}, null, 2));
  } catch (error) {
    if (page && !page.isClosed()) {
      await page.screenshot({path: path.join(path.dirname(input.output), 'failure.png'), fullPage: true}).catch(() => {});
      fs.writeFileSync(path.join(path.dirname(input.output), 'failure.html'), await page.content());
    }
    fs.writeFileSync(input.output, JSON.stringify({status: 'fail', error: error.message, checks, scans, errors}, null, 2));
    throw error;
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
