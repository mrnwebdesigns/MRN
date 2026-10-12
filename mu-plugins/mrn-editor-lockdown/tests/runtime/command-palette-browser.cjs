'use strict';
const fs=require('node:fs'),assert=require('node:assert/strict'),{createRequire}=require('node:module');
// Input must describe a temporary local draft with native ACF After Content rows.
const inputFile=JSON.parse(fs.readFileSync(process.argv[2],'utf8'));
const deps=createRequire(require('node:path').join(inputFile.engine_root,'package.json'));const {chromium,expect:baseExpect}=deps('@playwright/test'),AxeBuilder=deps('@axe-core/playwright').default;
const expect=baseExpect.configure({timeout:60000});
const input=JSON.parse(fs.readFileSync(process.argv[2],'utf8'));
const auth={base_url:input.url,cookies:input.cookies};
const output=process.argv[3];
(async()=>{const browser=await chromium.launch({headless:true});const checks=[];let page;
try {
 const context=await browser.newContext({ignoreHTTPSErrors:true,viewport:{width:1440,height:1100}});await context.addCookies(auth.cookies);
 await context.route('**/*',r=>new URL(r.request().url()).origin===auth.base_url?r.continue():r.abort());
 page=await context.newPage();page.setDefaultTimeout(40000);const errors=[],requests=[];page.on('pageerror',e=>errors.push(e.message));page.on('request',r=>requests.push(r.url().split('?')[0]));
 const url=auth.base_url+'/wp-admin/post.php?post='+input.ids.home+'&action=edit';await page.goto(url,{waitUntil:'load',timeout:120000});await page.waitForTimeout(1200);
 assert(!requests.some(u=>u.endsWith('/block-editor.min.js')));assert.equal(await page.locator('#mrn-editor-lockdown-loading-indicator-bootstrap').count(),0);
 assert(!await page.evaluate(()=>document.documentElement.classList.contains('mrn-editor-loading-indicator-live')));
 assert(!await page.locator('#mrn-editor-lockdown-js').textContent().then(s=>s.includes('loadingMessages')));
 checks.push('No initial block-editor download, spinner bootstrap or loading timer code');
 const mode=await page.evaluate(()=>window.postDuplicatorVars?.mode||'advanced');assert.notEqual(mode,'basic','Only the read-only advanced duplication dialog can be opened');
 // The plugin normally shows its trigger only for published posts. Exercise
 // the same installed dialog handler on this temporary draft without publishing.
 if (!await page.locator('.m4c-duplicate-post').count()) await page.evaluate(id=>{const trigger=document.createElement('button');trigger.type='button';trigger.className='m4c-duplicate-post';trigger.dataset.postid=id;trigger.dataset.posttype='page';trigger.textContent='Temporary duplication dialog trigger';document.querySelector('#misc-publishing-actions').appendChild(trigger);},String(input.ids.home));
 await page.locator('.m4c-duplicate-post').click();await expect(page.locator('.duplicate-post-modal.components-modal__frame')).toBeVisible();
 await expect(page.locator('.duplicate-post-modal input').first()).toBeVisible();
 assert(!requests.some(u=>u.endsWith('/block-editor.min.js')));
 await page.locator('.duplicate-post-modal').getByRole('button',{name:'Cancel',exact:true}).click();
 checks.push('Classic duplication dialog opens and cancels without block-editor or record creation');
 const after=page.locator('.acf-field[data-name="page_after_content_rows"]').first(),rows=after.locator(':scope > .acf-input > .acf-flexible-content > .values > .layout');
 await rows.first().locator('.acf-fc-layout-handle').first().click();
 await expect(rows.first().locator('.mce-tinymce').first()).toBeVisible({timeout:30000});
 await page.evaluate(()=>{window.mrnMediaTest=wp.media({title:'Native media regression check',multiple:false,library:{type:'image'}});window.mrnMediaTest.open();});
 await expect(page.locator('.media-modal:visible')).toBeVisible();await page.locator('.media-modal:visible .media-modal-close').click();
 checks.push('Native rich text starts on opening a saved row; native media opens/cancels');
 await page.keyboard.press('Control+k');const palette=page.getByRole('dialog',{name:'Command palette'});await expect(palette).toBeVisible();
 assert(requests.some(u=>u.endsWith('/block-editor.min.js')));
 const before=requests.filter(u=>u.endsWith('/block-editor.min.js')).length;
 await page.keyboard.press('Escape');await expect(palette).not.toBeVisible();
 await page.locator('#wp-admin-bar-command-palette .ab-item').click();await expect(palette).toBeVisible();
 assert.equal(requests.filter(u=>u.endsWith('/block-editor.min.js')).length,before,'Palette libraries load only once');
 const axe=await new AxeBuilder({page}).include('.commands-command-menu').withTags(['wcag2a','wcag2aa','wcag21a','wcag21aa']).analyze();
 assert.deepEqual(axe.violations.map(v=>v.id),[]);await page.keyboard.press('Escape');
 checks.push('Command palette first-use keyboard opening, toolbar reopening, single download and WCAG A/AA');
 assert.deepEqual(errors,[]);
 await context.close();
 const retry=await browser.newContext({ignoreHTTPSErrors:true,viewport:{width:1440,height:1100}});await retry.addCookies(auth.cookies);let fail=true;
 await retry.route('**/*',r=>{const u=new URL(r.request().url());if(u.origin!==auth.base_url)return r.abort();if(fail&&u.pathname.endsWith('/block-editor.min.js')){fail=false;return r.abort();}return r.continue();});
 page=await retry.newPage();page.setDefaultTimeout(40000);await page.goto(url,{waitUntil:'load',timeout:120000});
 await page.keyboard.press('Control+k');await expect(page.locator('#wp-admin-bar-command-palette .ab-item')).not.toHaveAttribute('aria-busy','true');
 await expect(page.getByRole('dialog',{name:'Command palette'})).not.toBeVisible();
 await page.keyboard.press('Control+k');await expect(page.getByRole('dialog',{name:'Command palette'})).toBeVisible();
 checks.push('A failed first-use download can be retried successfully');
 fs.writeFileSync(output,JSON.stringify({status:'passed',checks,errors,axeViolations:axe.violations.length},null,2));console.log(JSON.stringify({status:'passed',checks,errors}));
} catch(error){if(page)await page.screenshot({path:output.replace('.json','-failure.png')});throw error;}finally{await browser.close();}
})().catch(e=>{console.error(e.stack);process.exitCode=1;});
