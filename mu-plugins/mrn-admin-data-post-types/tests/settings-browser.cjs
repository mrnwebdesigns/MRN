/* Native settings/discovery and scoped AA checks in the disposable loopback fixture. */
'use strict';
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { createRequire } = require('node:module');

(async () => {
  const input = JSON.parse(fs.readFileSync(0, 'utf8'));
  assert.equal(new URL(input.url).hostname, '127.0.0.1');
  const dependencies = createRequire(path.join(input.engine_root, 'package.json'));
  const { chromium, expect } = dependencies('@playwright/test');
  const AxeBuilder = dependencies('@axe-core/playwright').default;
  const browser = await chromium.launch({ headless: true });
  try {
    const context = await browser.newContext({ reducedMotion: 'reduce' });
    await context.route('**/*', route => new URL(route.request().url()).origin === input.url ? route.continue() : route.abort());
    const page = await context.newPage();
    await page.goto(input.login_url, { waitUntil: 'networkidle' });
    await page.locator('#user_login').fill(input.username);
    await page.locator('#user_pass').fill(input.password);
    await Promise.all([page.waitForURL(url => !url.pathname.includes('wp-login.php')), page.locator('#wp-submit').click()]);
    const config = input.url + '/wp-admin/options-general.php?page=mrn-config-helper';
    for (let attempt = 0; attempt < 5; attempt++) {
      await page.goto(config, { waitUntil: 'domcontentloaded' });
      if (await page.locator('#mrn-site-config-form').count()) break;
      if (new URL(page.url()).searchParams.get('page') === 'seopress-setup') {
        await page.getByRole('link', { name: 'Skip for now', exact: true }).click();
      }
    }
    await page.locator('[data-mrn-tab="admin"]').click();
    await page.locator('#mrn-config-admin-tab-content-types').click();
    const card = page.locator('.mrn-config-helper-card').filter({ has: page.getByRole('heading', { name: 'Disabled Content Types', exact: true }) });
    await expect(card).toBeVisible();
    const gallery = card.locator('input[value="gallery"]');
    await expect(gallery).toBeChecked();
    await gallery.focus();
    await page.keyboard.press('Space');
    await expect(gallery).not.toBeChecked();
    await page.keyboard.press('Space');
    await expect(gallery).toBeChecked();
    const axe = await new AxeBuilder({ page }).include('.mrn-config-helper-card:has(#mrn-disabled-types-help)').withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
    assert.deepEqual(axe.violations, []);
    await page.screenshot({ path: input.evidence + '/disabled-settings.png', fullPage: true });
    await Promise.all([page.waitForLoadState('load'), page.getByRole('button', { name: 'Save Configurations', exact: true }).click()]);
    await page.waitForURL(url => !url.pathname.includes('admin-post.php'));
    for (const section of ['seopress-titles', 'seopress-xml-sitemap']) {
      await page.goto(input.url + '/wp-admin/admin.php?page=' + section, { waitUntil: 'networkidle' });
      const data = await page.evaluate(() => ({ types: SEOPRESS_SETTINGS_DATA.POST_TYPES.map(x => x.name), taxonomies: SEOPRESS_SETTINGS_DATA.TAXONOMIES.map(x => x.name), sitemap: SEOPRESS_SETTINGS_DATA.SITEMAP_POST_TYPES.map(x => x.name) }));
      assert.equal(data.types.includes('gallery'), false);
      assert.equal(data.types.includes('case_study'), false);
      assert.equal(data.types.includes('team_member'), true);
      assert.equal(data.taxonomies.includes('gallery_category'), false);
      assert.equal(data.taxonomies.includes('gallery_tag'), false);
      assert.equal(data.taxonomies.includes('fixture_shared'), true);
      assert.equal(data.sitemap.includes('gallery'), false);
      assert.equal(data.sitemap.includes('team_member'), true);
    }
    fs.writeFileSync(input.evidence + '/browser.json', JSON.stringify({ status: 'pass', settingsSave: true, keyboard: 'space toggles focused control', axeViolations: axe.violations.length, nativeSettingsPages: ['titles', 'sitemaps'] }, null, 2));
    console.log('PASS native Config Helper save, keyboard, scoped axe and SEOPress settings discovery');
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error.message); process.exitCode = 1; });
