/* Native Classic Editor and frontend checks; explicit provider response fixture. */
'use strict';
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const {createRequire} = require('node:module');
(async () => {
  const input = JSON.parse(fs.readFileSync(0, 'utf8'));
  assert.equal(new URL(input.url).hostname, '127.0.0.1');
  const dependencies = createRequire(path.join(input.engine_root, 'package.json'));
  const {chromium, expect} = dependencies('@playwright/test');
  const AxeBuilder = dependencies('@axe-core/playwright').default;
  const browser = await chromium.launch({headless:true});
  const checks = [];
  try {
    const context = await browser.newContext({reducedMotion:'reduce'});
    const page = await context.newPage();
    await page.goto(input.login_url, {waitUntil:'domcontentloaded'});
    await page.locator('#user_login').fill(input.username);
    await page.locator('#user_pass').fill(input.password);
    await Promise.all([page.waitForURL(url => !url.pathname.includes('wp-login')), page.locator('#wp-submit').click()]);
    const editor = `${input.url}/wp-admin/post.php?post=${input.resource.posts.youtube}&action=edit`;
    for (let attempt=0; attempt<4; attempt++) {
      await page.goto(editor, {waitUntil:'domcontentloaded'});
      if (await page.locator('#post').count()) break;
      const skip = page.getByRole('link', {name:'Skip for now',exact:true});
      if (await skip.count()) await skip.click();
    }
    const field = page.locator('.acf-field[data-name="resource_use_provider_poster"]');
    const toggle = field.locator('input[type="checkbox"]');
    const source = page.locator('.acf-field[data-name="resource_source"]');
    const url = page.locator('.acf-field[data-name="resource_video_remote"] input[type="url"]');
    const thumbnail = page.locator('#_thumbnail_id');
    await expect(field).toBeVisible();
    await expect(toggle).not.toBeChecked();
    const manual = await thumbnail.inputValue();
    await source.locator('input[value="file"]').check();
    await expect(field).toBeHidden();
    await source.locator('input[value="remote_video"]').check();
    await expect(field).toBeVisible();
    await url.fill('https://youtu.be/bbbbbbbbbbb');
    await toggle.focus();
    await page.keyboard.press('Space');
    await expect(toggle).toBeChecked();
    const save = async () => {
      await Promise.all([
        page.waitForResponse(response => response.request().method()==='POST' && new URL(response.url()).pathname==='/wp-admin/post.php'),
        page.locator('#publish').click()
      ]);
      await page.waitForLoadState('networkidle');
      await page.goto(editor, {waitUntil:'networkidle'});
    };
    await save();
    await expect(toggle).toBeChecked();
    const automatic = await thumbnail.inputValue();
    assert.notEqual(automatic,manual);
    await expect(page.locator('#set-post-thumbnail img')).toBeVisible();
    const preview = await page.locator('#set-post-thumbnail img').getAttribute('src');
    checks.push('native editor conditional toggle and provider poster import after nonce-validated save');
    await save();
    await expect(thumbnail).toHaveValue(automatic);
    checks.push('repeat editor save reuses the same attachment');
    const adminAA = await new AxeBuilder({page}).include('.acf-field[data-name="resource_use_provider_poster"]').withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();
    assert.deepEqual(adminAA.violations.map(v=>v.id),[]);
    const guest = await browser.newContext({reducedMotion:'reduce'});
    const front = await guest.newPage();
    await front.goto(input.resource.url,{waitUntil:'networkidle'});
    const poster = front.locator('a.mrn-content-list-row__media[aria-label="Play video: Resource video youtube"] img');
    await expect(poster).toHaveClass(/wp-post-image/);
    const unsizedPath = url => new URL(url).pathname.replace(/-\d+x\d+(\.[a-z]+)$/i,'$1');
    assert.equal(unsizedPath(await poster.getAttribute('src')),unsizedPath(preview));
    await expect(poster).toHaveAttribute('alt','Resource video youtube');
    assert.equal(new URL(await poster.getAttribute('src')).origin,new URL(input.url).origin);
    for (const width of [1440,768,390]) {
      await front.setViewportSize({width,height:900});
      const aa = await new AxeBuilder({page:front}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();
      assert.deepEqual(aa.violations.map(v=>({id:v.id,nodes:v.nodes.length})),[]);
      assert(await front.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
      await front.screenshot({path:path.join(path.dirname(input.output),`resource-posters-${width}.png`),fullPage:true});
    }
    checks.push('local responsive featured image, descriptive alt text and desktop/tablet/mobile whole-page AA');
    await guest.close();
    await page.goto(editor,{waitUntil:'networkidle'});
    await toggle.uncheck();
    await save();
    await expect(toggle).not.toBeChecked();
    await expect(thumbnail).toHaveValue(manual);
    checks.push('turning provider poster off restores the original manual featured image');
    fs.writeFileSync(path.join(path.dirname(input.output),'resource-posters-browser.json'),JSON.stringify({status:'pass',checks,provider_responses:'explicit offline fixture'},null,2));
    process.stdout.write(JSON.stringify({status:'pass',checks})+'\n');
  } finally { await browser.close(); }
})().catch(error=>{console.error(error);process.exitCode=1;});
