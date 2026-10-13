/* Run with the disposable fixture's private browser input on stdin. */
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
  const browser = await chromium.launch({headless: true});
  const checks = [];
  const output = path.dirname(input.output);
  try {
    const context = await browser.newContext({reducedMotion: 'reduce'});
    const page = await context.newPage();
    const mediaRequests = [];
    page.on('request', request => {
      if (/\.mp4(?:\?|$)|youtube|vimeo|drive\.google/.test(request.url())) mediaRequests.push(request.url());
    });
    await page.goto(input.resource.url, {waitUntil: 'networkidle'});
    const local = page.locator('a.mrn-content-list-row__media[aria-label="Play video: Resource video local"]');
    const remote = page.locator('a.mrn-content-list-row__media[aria-label="Play video: Resource video remote"]');
    await expect(local).toHaveCount(1);
    await expect(local.locator('.mrn-video-row__play')).toBeVisible();
    assert.equal(mediaRequests.length, 0, 'video providers and media load only after opening');
    checks.push('poster play overlay and no video/provider requests before interaction');
    for (const width of [1440, 768, 390]) {
      await page.setViewportSize({width, height: 900});
      await expect(local).toBeVisible();
      assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'no horizontal overflow');
      const result = await new AxeBuilder({page}).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
      assert.deepEqual(result.violations.map(v => ({id:v.id, nodes:v.nodes.length})), [], 'whole-page AA');
      await page.screenshot({path:path.join(output, `resources-${width}.png`), fullPage:true});
    }
    checks.push('desktop/tablet/mobile layouts and whole-page WCAG AA');
    await page.setViewportSize({width: 1440, height: 900});
    for (const trigger of [local, remote]) {
      await trigger.focus();
      await page.keyboard.press('Enter');
      const dialog = page.locator('.glightbox-container');
      await expect(dialog).toBeVisible();
      await expect(dialog).toHaveAttribute('aria-modal', 'true');
      await expect(dialog).toHaveAttribute('aria-label', /Play video: Resource video/);
      await expect(page.locator('.gvideo-local')).toHaveCount(1, {timeout: 30000});
      await expect.poll(() => page.locator('.gvideo-local').evaluate(video => video.readyState), {timeout: 30000}).toBeGreaterThan(1);
      await expect.poll(() => page.locator('.gvideo-local').evaluate(video => video.currentTime), {timeout: 15000}).toBeGreaterThan(0);
      const result = await new AxeBuilder({page}).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
      assert.deepEqual(result.violations.map(v => ({id:v.id, nodes:v.nodes.length})), [], 'modal AA');
      for (let index = 0; index < 10; index++) {
        await page.keyboard.press('Tab');
        assert(await page.evaluate(() => Boolean(document.activeElement.closest('.glightbox-container'))), 'focus stays in modal');
      }
      await page.keyboard.press('Escape');
      await expect(dialog).toHaveCount(0);
      await expect(trigger).toBeFocused();
      await expect(page.locator('video')).toHaveCount(0);
    }
    checks.push('uploaded and remote direct video playback; keyboard open, focus containment, Escape, focus return and player teardown');
    const original = page.locator('.mrn-video-row__trigger.glightbox');
    await original.click();
    await expect(page.locator('.glightbox-container .gslide')).toHaveCount(1);
    await expect(page.locator('.gvideo-local')).toHaveCount(1, {timeout:30000});
    await page.keyboard.press('Escape');
    await expect(page.locator('.glightbox-container')).toHaveCount(0);
    await expect(original).toBeFocused();
    checks.push('existing Stack Video row stays playable and excludes Resource slides');
    for (const [key, provider] of [['youtube', /youtube(?:-nocookie)?\.com/], ['vimeo', /vimeo\.com/]]) {
      await page.locator(`a.mrn-content-list-row__media[aria-label="Play video: Resource video ${key}"]`).click();
      await expect(page.locator('.glightbox-container iframe')).toHaveCount(1, {timeout: 30000});
      await expect(page.locator('.glightbox-container iframe')).toHaveAttribute('src', provider);
      await page.keyboard.press('Escape');
      await expect(page.locator('.glightbox-container')).toHaveCount(0);
    }
    checks.push('YouTube and Vimeo provider iframe construction (provider delivery is external)');
    await page.route('https://drive.google.com/file/d/resourceVideoFixture/preview', route => route.fulfill({contentType:'text/html', body:'<!doctype html><html lang="en"><title>Drive fixture</title><body><main><p>Drive preview fixture</p></main></body></html>'}));
    await page.locator('a.mrn-content-list-row__media[aria-label="Play video: Resource video drive"]').click();
    const drive = page.locator('.glightbox-container iframe');
    await expect(drive).toHaveAttribute('src', 'https://drive.google.com/file/d/resourceVideoFixture/preview');
    await expect(drive).toHaveAttribute('title', /Resource video drive/);
    const driveAA = await new AxeBuilder({page}).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
    assert.deepEqual(driveAA.violations.map(v => ({id:v.id, nodes:v.nodes.length})), [], 'Drive preview modal AA');
    await page.keyboard.press('Escape');
    await expect(page.locator('.glightbox-container')).toHaveCount(0);
    checks.push('Google Drive preview iframe contract and AA using an explicit provider fixture');
    await page.goto(input.login_url, {waitUntil:'domcontentloaded'});
    await page.locator('#user_login').fill(input.username);
    await page.locator('#user_pass').fill(input.password);
    await Promise.all([page.waitForURL(url => !url.pathname.includes('wp-login'), {timeout:60000}), page.locator('#wp-submit').click()]);
    const editor = `${input.url}/wp-admin/post.php?post=${input.resource.posts.remote}&action=edit`;
    for (let attempt = 0; attempt < 4; attempt++) {
      await page.goto(editor, {waitUntil:'domcontentloaded'});
      if (await page.locator('#post').count()) break;
      const skip = page.getByRole('link', {name:'Skip for now', exact:true});
      if (await skip.count()) await skip.click();
    }
    await expect(page.locator('#post')).toBeVisible();
    const source = page.locator('.acf-field[data-name="resource_source"]');
    const file = page.locator('.acf-field[data-name="resource_file"]');
    const url = page.locator('.acf-field[data-name="resource_video_remote"] input[type="url"]');
    await expect(source.locator('input[value="remote_video"]')).toBeChecked();
    await expect(file).toBeHidden();
    await url.fill('https://example.invalid/unsupported');
    await page.locator('#publish').click();
    await expect(page.locator('.acf-error-message').filter({hasText:'supported'})).toBeVisible();
    await url.fill(input.resource.remote_url);
    await Promise.all([
      page.waitForResponse(response => response.request().method() === 'POST' && new URL(response.url()).pathname === '/wp-admin/post.php', {timeout:60000}),
      page.locator('#publish').click()
    ]);
    await page.goto(editor, {waitUntil:'networkidle'});
    await expect(url).toHaveValue(input.resource.remote_url);
    await expect(file).toBeHidden();
    await source.locator('input[value="file"]').check();
    await expect(file).toBeVisible();
    await expect(url).toBeHidden();
    checks.push('Classic Editor conditional fields, unsupported URL rejection, remote save/reload and retained uploaded file');
    fs.writeFileSync(path.join(output, 'resource-video-browser.json'), JSON.stringify({status:'pass',checks}, null, 2));
    process.stdout.write(JSON.stringify({status:'pass',checks}) + '\n');
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
