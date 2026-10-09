/* Native installed editor/forms and whole-page AA checks on disposable loopback. */
'use strict';
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { createRequire } = require('node:module');

(async () => {
  const input = JSON.parse(fs.readFileSync(0, 'utf8'));
  const url = new URL(input.url);
  assert.equal(url.hostname, '127.0.0.1', 'qualification must be loopback');
  const dependencies = createRequire(path.join(input.engine_root, 'package.json'));
  const { chromium, expect } = dependencies('@playwright/test');
  const AxeBuilder = dependencies('@axe-core/playwright').default;
  const browser = await chromium.launch({ headless: true });
  const checks = [];
  const scans = [];
  const requests = [];
  let loginState;
  let activePage;
  try {
    const context = await browser.newContext({ reducedMotion: 'reduce' });
    await context.route('**/*', async route => {
      const destination = new URL(route.request().url());
      if (destination.origin === url.origin) await route.continue();
      else await route.abort();
    });
    const page = await context.newPage();
    activePage = page;
    page.on('request', request => {
      if (request.method() === 'POST') requests.push({method: 'POST', path: new URL(request.url()).pathname});
    });
    page.on('response', response => {
      if (response.request().method() === 'POST') requests.push({status: response.status(), path: new URL(response.url()).pathname});
    });
    page.setDefaultTimeout(30000);
    page.setDefaultNavigationTimeout(120000);
    await page.goto(input.login_url || `${input.url}/wp-login.php`, { waitUntil: 'domcontentloaded' });
    // WordPress schedules a delayed username autofocus. Settle its native
    // login page before entering credentials, then verify the submitted fields.
    await page.waitForLoadState('networkidle');
    await page.locator('#user_login').fill(input.username);
    await page.locator('#user_pass').fill(input.password);
    assert.equal(await page.locator('#user_login').inputValue(), input.username);
    assert.equal(await page.locator('#user_pass').inputValue() === input.password, true);
    loginState = await page.locator('#loginform').evaluate(form => ({
      action: form.action, valid: form.checkValidity(),
      fields: Array.from(form.elements).map(field => ({name: field.name, type: field.type,
        valid: field.validity?.valid, message: field.validationMessage, disabled: field.disabled,
        length: ['log', 'pwd'].includes(field.name) ? field.value.length : undefined})),
    }));
    const loginPath = new URL(page.url()).pathname;
    await Promise.all([
      page.waitForURL(value => value.pathname !== loginPath, { waitUntil: 'domcontentloaded', timeout: 30000 }),
      page.locator('#wp-submit').click(),
    ]);
    const editorUrl = `${input.url}/wp-admin/post.php?post=${input.ids.home}&action=edit`;
    // Native WPForms consumes a one-time welcome redirect on the first admin
    // request. Follow that workflow, then require the actual privileged editor.
    for (let attempt = 0; attempt < 2; attempt++) {
      await page.goto(editorUrl, { waitUntil: 'domcontentloaded' });
      if (new URL(page.url()).searchParams.get('page') !== 'wpforms-getting-started') break;
    }
    await expect(page.locator('#post')).toBeVisible();
    checks.push('native administrator login and privileged editor access');
    await expect(page.locator('#title')).toHaveCount(1);
    await expect(page.locator('.block-editor')).toHaveCount(0);
    await expect(page.locator('.acf-field[data-name="page_after_content_rows"]').first()).toBeVisible();
    await expect(page.locator('.acf-field[data-name="page_after_content_rows"] .layout:not(.acf-clone)')).toHaveCount(2);
    const heading = page.locator('.acf-field[data-name="page_after_content_rows"] .layout:not(.acf-clone)')
      .first().locator('.acf-field[data-name="heading"] input').first();
    await expect(heading).toHaveValue('Fleet After Content');
    await heading.fill('Fleet editor saved After Content');
    const [saved] = await Promise.all([
      page.waitForResponse(response => response.request().method() === 'POST'
        && new URL(response.url()).pathname === '/wp-admin/post.php', {timeout: 120000}),
      page.locator('#publish').click(),
    ]);
    assert.equal(saved.status(), 302, 'native editor save must redirect successfully');
    await page.waitForURL(value => value.searchParams.get('message') === '1',
      {waitUntil: 'domcontentloaded', timeout: 120000});
    await page.reload({ waitUntil: 'domcontentloaded' });
    await expect(page.locator('.acf-field[data-name="page_after_content_rows"] .layout:not(.acf-clone)')).toHaveCount(2);
    await expect(heading).toHaveValue('Fleet editor saved After Content');
    checks.push('Classic Editor save/reload persists edited heading and both native After Content rows');
    await context.close();
    for (const width of [1440, 768, 390]) {
      const publicContext = await browser.newContext({ viewport: { width, height: 1000 }, reducedMotion: 'reduce' });
      await publicContext.route('**/*', async route => {
        if (new URL(route.request().url()).origin === url.origin) await route.continue();
        else await route.abort();
      });
      const publicPage = await publicContext.newPage();
      activePage = publicPage;
      const failures = [];
      publicPage.on('pageerror', error => failures.push(error.message));
      publicPage.on('response', response => {
        if (response.status() >= 400 && new URL(response.url()).origin === url.origin) {
          failures.push(`${response.status()} ${new URL(response.url()).pathname}`);
        }
      });
      for (const route of ['/', '/contact/']) {
        const response = await publicPage.goto(input.url + route, { waitUntil: 'networkidle' });
        assert.equal(response.status(), 200);
        assert.equal(await publicPage.locator('h1').count(), 1, 'one page heading');
        const result = await new AxeBuilder({ page: publicPage }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
        scans.push({ route, width, violations: result.violations });
        assert.equal(result.violations.length, 0, 'whole-page WCAG A/AA must pass');
        const size = await publicPage.evaluate(() => ({ scroll: document.documentElement.scrollWidth,
          viewport: window.innerWidth }));
        assert(size.scroll <= size.viewport + 1, 'no horizontal overflow');
        if (route === '/contact/') {
          await expect(publicPage.locator(`#wpforms-form-${input.ids.form}`)).toBeVisible();
          await expect(publicPage.getByLabel('Fixture message', { exact: false })).toBeVisible();
          await publicPage.getByLabel('Fixture message', { exact: false }).focus();
          await publicPage.keyboard.press('Tab');
          assert(await publicPage.evaluate(() => document.activeElement !== document.body));
        }
      }
      assert.deepEqual(failures, [], 'no browser errors or missing local assets');
      checks.push(`public native pages/form, keyboard and reduced motion at ${width}px`);
      await publicContext.close();
    }
    fs.writeFileSync(input.output, JSON.stringify({ status: 'pass', checks, scans,
      woocommerce: false, delivery_tested: false, provider_adoption: false }, null, 2) + '\n');
  } catch (error) {
    let visible = '', location = '';
    if (activePage && !activePage.isClosed()) {
      await activePage.screenshot({ path: input.output + '.png', fullPage: true });
      visible = await activePage.locator('body').innerText();
      location = activePage.url();
    }
    fs.writeFileSync(input.output, JSON.stringify({status: 'fail', error: error.message,
      url: location, visible, loginState, requests, checks, scans}, null, 2) + '\n');
    throw error;
  } finally {
    await browser.close();
  }
})().catch(error => { process.stderr.write(error.stack + '\n'); process.exitCode = 1; });
