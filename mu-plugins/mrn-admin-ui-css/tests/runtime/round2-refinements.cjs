'use strict';
// Approved Local Hub only. The owned empty draft is the only content mutation.
const fs = require('node:fs');
const assert = require('node:assert/strict');
const req = require('node:module').createRequire('/Users/khofmeyer/Development/MRN-qa-engine/package.json');
const {chromium, expect:baseExpect} = req('@playwright/test');
const Axe = req('@axe-core/playwright').default;
const expect = baseExpect.configure({timeout:30000});
const base = 'https://admin-editor-pilot.localhost';
const out = 'outputs/admin-refinements/round2';
const content = '.acf-field[data-name="page_content_rows"]';
const root = content + ' > .acf-input > .acf-flexible-content > .values > .layout:not(.acf-clone)';
const fixture = JSON.parse(fs.readFileSync(out + '/empty-fixture.json'));
assert(Number.isInteger(fixture.id) && fixture.status === 'draft');
assert(Object.values(fixture.counts).every(count => count === 0), 'Expected owned empty fixture; do not reset a changed draft');
(async () => {
 const browser = await chromium.launch();
 const context = await browser.newContext({ignoreHTTPSErrors:true,viewport:{width:1487,height:1100}});
 await context.addCookies(JSON.parse(fs.readFileSync('/Users/khofmeyer/Development/MRN-sites/admin-editor-pilot/logs/administrator-auth.json')));
 const page = await context.newPage(), cdp = await context.newCDPSession(page);
 await cdp.send('Network.enable');
 await cdp.send('Network.setBlockedURLs',{urlPatterns:[{urlPattern:base+'/*',block:false},{urlPattern:'*://*:*/*',block:true}],urls:['http://*','https://*']});
 const result = {checks:[],axe:{},errors:[]}; let preference;
 page.on('pageerror', error => result.errors.push(error.message));
 page.on('dialog', dialog => dialog.accept());
 async function go(path) {
  await page.goto(base+'/wp-admin/'+path,{waitUntil:'domcontentloaded',timeout:90000});
  await page.waitForFunction(()=>document.readyState==='complete',null,{timeout:90000});
 }
 async function ready(count) {
  await expect(page.locator('.mrn-section-link')).toHaveCount(count);
  await page.waitForFunction(()=>window.tinymce && tinymce.editors.every(editor=>editor.initialized),null,{timeout:60000});
 }
 async function shot(name) { await page.screenshot({path:out+'/after/'+name+'.png',fullPage:true}); }
 async function appearance(value) {
  const previous=await page.locator('html').getAttribute('data-mrn-appearance');
  await page.locator('#wp-admin-bar-my-account > a').hover();
  const choice=page.locator('.mrn-appearance-choices button[value="'+value+'"]');
  await choice.focus(); await choice.press('Enter');
  if (previous!==value) await expect(page.locator('.mrn-appearance-status')).toContainText('saved for your account');
  await expect(choice).toHaveAttribute('aria-pressed','true');
  await expect(choice).toBeEnabled();
  await expect(page.locator('html')).toHaveAttribute('data-mrn-appearance',value);
 }
 async function saveDraft() {
  await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded',timeout:90000}),page.locator('#mrn-usb-draft').click()]);
  await expect(page.locator('#message')).toContainText('draft updated');
  await page.reload({waitUntil:'domcontentloaded',timeout:90000});
 }
 try {
  await go('index.php'); preference=await page.locator('html').getAttribute('data-mrn-appearance');
  assert.equal(await page.locator('#wp-admin-bar-mrn-appearance').count(),0);
  await expect(page.locator('#wp-admin-bar-user-actions .mrn-appearance-choices button')).toHaveCount(3);
  for (const mode of ['light','dark','system']) {
   await appearance(mode); await go('edit.php?post_type=page');
   await expect(page.locator('html')).toHaveAttribute('data-mrn-appearance',mode);
  }
  result.checks.push('Account Light/Dark/System buttons activate by keyboard and persist across navigation');
  for (const mode of ['light','dark']) {
   await appearance(mode); await go('index.php');
   await page.evaluate(()=>document.body.classList.remove('folded'));
   for (const id of ['wpforms-overview','seopress-option']) {
    const icon=page.locator('#toplevel_page_'+id+' .wp-menu-image');
    assert.equal(await icon.evaluate(el=>getComputedStyle(el).backgroundImage),'none');
    assert(await icon.evaluate(el=>getComputedStyle(el,'::after').maskImage!=='none'));
   }
   await shot(mode+'-dashboard');
   await page.locator('#wp-admin-bar-my-account > a').hover(); await shot(mode+'-account');
   result.axe[mode+'-toolbar']=(await new Axe({page}).include('#wpadminbar').include('#adminmenu').withTags(['wcag2a','wcag2aa','wcag21aa']).analyze()).violations;
   for (const [button,panel] of [['#show-settings-link','#screen-options-wrap'],['#contextual-help-link','#contextual-help-wrap']]) {
    await page.locator('#wp-admin-bar-mrn-screen > a').focus(); await page.keyboard.press('Enter');
    await expect(page.locator(button)).toBeVisible(); await page.locator(button).click();
    await expect(page.locator(panel)).toBeVisible();
    await expect(page.locator(panel)).toBeFocused();
    await shot(mode+'-'+panel.slice(1));
    await page.keyboard.press('Escape'); await expect(page.locator(panel)).toBeHidden();
    await expect(page.locator('#wp-admin-bar-mrn-screen > a')).toBeFocused();
   }
   result.checks.push(mode+': reported icons visible; native Screen Options/Help open, focus and close');
  }
  await go('post.php?post='+fixture.id+'&action=edit'); await ready(0);
  for (const mode of ['light','dark']) {
   await appearance(mode);
   await expect(page.locator('body')).toHaveClass(/mrn-workspace-empty/);
   await expect(page.locator('.mrn-region-add:visible')).toHaveCount(3);
   await expect(page.locator('.mrn-view-mode')).toBeHidden();
   await expect(page.locator('.mrn-section-steps')).toBeHidden();
   await expect(page.locator('.mrn-outline-empty')).toContainText('No sections yet');
   await shot(mode+'-empty');
   await page.setViewportSize({width:390,height:844});
   assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
   await shot(mode+'-empty-mobile'); await page.setViewportSize({width:1487,height:1100});
  }
  // Open the installed picker through the visible native route, then exercise
  // ACF's own append/remove APIs, which own editor initialization and teardown.
  await page.locator('.mrn-region-add[aria-label="Add section: Content"]').click();
  assert(await page.locator('.acf-tooltip:visible, [role="dialog"]:visible').count()>0);
  await page.keyboard.press('Escape');
  await page.evaluate(()=>acf.getField(jQuery('.acf-field[data-name="page_content_rows"]').first()).add({layout:'body_text'}));
  await ready(1); await expect(page.locator('body')).not.toHaveClass(/mrn-workspace-overview/);
  await expect(page.locator(root)).toBeVisible();
  const name=page.locator(root+' .acf-field[data-name="internal_name"] input[type=text]').first();
  await name.fill('Round 2 native section'); await saveDraft(); await ready(1);
  await expect(page.locator('.mrn-section-link')).toContainText('Round 2 native section');
  await page.evaluate(()=>{const field=acf.getField(jQuery('.acf-field[data-name="page_content_rows"]').first());field.removeLayout(field.$layouts().first());});
  await ready(0); await expect(page.locator('.mrn-region-add:visible')).toHaveCount(3);
  await saveDraft(); await ready(0);
  result.checks.push('Owned empty draft: native picker reachable; 0→1 append selects row; Save Draft/reload retains row; removal/save/reload restores usable zero state');
  for(const [id,count] of [[2523,19],[2524,32]]) {
   await go('post.php?post='+id+'&action=edit'); await ready(count);
   assert(await page.evaluate(()=>[...document.querySelectorAll('.mrn-workspace-field')].every(field=>field.closest('form')?.id==='post')));
   await page.locator('#mrn-section-search').fill('round2-no-match-unique');
   await expect(page.locator('.mrn-outline-empty')).toContainText('No matching sections');
   await page.locator('#mrn-section-search').fill('');
   result.checks.push(count+' sections: native form ancestry and nonempty/no-match distinction preserved');
  }
  for(const [name,violations] of Object.entries(result.axe)) assert.deepEqual(violations.map(v=>v.id),[],name);
  assert.deepEqual(result.errors,[]);
 } catch(error) { result.failure=error.message; throw error; }
 finally {
  if(preference && page.url().startsWith(base)) { try { await appearance(preference); } catch(error) { result.preferenceRestoreFailure=error.message; process.exitCode=1; } }
  fs.writeFileSync(out+'/runtime-results.json',JSON.stringify(result,null,2)+'\n'); await browser.close();
 }
})().catch(error=>{console.error(error);process.exit(1)});
