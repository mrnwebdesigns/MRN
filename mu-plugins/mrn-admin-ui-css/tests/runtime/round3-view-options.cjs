'use strict';
// Exact review fixtures; native preference writes are restored. No publishing.
const fs=require('node:fs'),assert=require('node:assert/strict');
const req=require('node:module').createRequire('/Users/khofmeyer/Development/MRN-qa-engine/package.json');
const {chromium,expect:baseExpect}=req('@playwright/test'),Axe=req('@axe-core/playwright').default;
const expect=baseExpect.configure({timeout:30000});
const base='https://admin-editor-pilot.mrndev.io',phase=process.env.MRN_PILOT_PHASE||'after';
assert(['before','after'].includes(phase));
const out=process.env.MRN_PILOT_OUTPUT||'outputs/admin-review-round3/'+phase;fs.mkdirSync(out,{recursive:true});
function contrast(a,b){const lum=s=>s.match(/[\d.]+/g).slice(0,3).map(Number).map(v=>{v/=255;return v<=.04045?v/12.92:((v+.055)/1.055)**2.4;}).reduce((n,v,i)=>n+v*[.2126,.7152,.0722][i],0);const x=lum(a),y=lum(b);return (Math.max(x,y)+.05)/(Math.min(x,y)+.05);}
(async()=>{
 const browser=await chromium.launch(),context=await browser.newContext({viewport:{width:1487,height:1100}});
 await context.addCookies(JSON.parse(fs.readFileSync(process.env.MRN_PILOT_AUTH)));
 await context.route('**/*',r=>new URL(r.request().url()).origin===base?r.continue():r.abort());
 const p=await context.newPage(),cdp=await context.newCDPSession(p),result={phase,at:new Date().toISOString(),checks:[],geometry:[],contrast:[],timings:[],axe:{},errors:[]};let original;
 p.on('pageerror',e=>result.errors.push(e.message));
 await cdp.send('Performance.enable');
 async function go(path,count){console.log(phase,'load',path);const t=performance.now();const r=await p.goto(base+'/wp-admin/'+path,{waitUntil:'load',timeout:120000});assert.equal(r.status(),200);assert(p.url().startsWith(base+'/wp-admin/'),'Expected native admin session');if(count!==undefined){await expect(p.locator('.mrn-section-link')).toHaveCount(count);await p.waitForFunction(()=>window.tinymce&&tinymce.editors.every(e=>e.initialized),null,{timeout:60000});}const m=Object.fromEntries((await cdp.send('Performance.getMetrics')).metrics.map(x=>[x.name,x.value]));result.timings.push({path,mode:await p.locator('html').getAttribute('data-mrn-appearance'),wall_ms:performance.now()-t,cpu_ms:m.TaskDuration*1000,heap_mb:m.JSHeapUsedSize/1024/1024,dom_nodes:await p.locator('*').count()});}
 async function shot(name,selector){await (selector?p.locator(selector):p).screenshot({path:out+'/'+name+'.png',...(!selector?{fullPage:true}:{})});}
 async function account(){await p.locator('#wp-admin-bar-my-account > a').focus();await p.keyboard.press('Enter');await expect(p.locator('.mrn-appearance-form')).toBeVisible();}
 async function appearance(mode){if(await p.locator('html').getAttribute('data-mrn-appearance')===mode)return;await account();await p.locator('.mrn-appearance-choices button[value="'+mode+'"]').click();await expect(p.locator('.mrn-appearance-status')).toContainText('saved for your account');await expect(p.locator('.mrn-appearance-choices button[value="'+mode+'"]').first()).toBeEnabled();await p.keyboard.press('Escape');await p.mouse.move(1000,250);}
 async function openMenu(){await p.locator('#wp-admin-bar-mrn-screen > a').focus();await p.keyboard.press('Enter');await expect(p.locator('#screen-meta-links .show-settings').first()).toBeVisible();}
 async function openOptions(){if(await p.locator('#screen-options-wrap').isVisible())return;await openMenu();await p.locator('#show-settings-link').click();await expect(p.locator('#screen-options-wrap')).toBeVisible();}
 async function closePanel(){if(phase==='before'){await p.locator('#screen-options-wrap:visible,#contextual-help-wrap:visible').focus();await p.keyboard.press('Escape');}else{await p.locator('.mrn-screen-close').click();}await expect(p.locator('#screen-meta')).toBeHidden();}
 async function scan(name,selectors){let axe=new Axe({page:p}).withTags(['wcag2a','wcag2aa','wcag21aa']);for(const s of selectors)axe=axe.include(s);result.axe[name]=(await axe.analyze()).violations.map(v=>({id:v.id,impact:v.impact,nodes:v.nodes.map(n=>({target:n.target,summary:n.failureSummary}))}));}
 try{
  await go('index.php');original=await p.locator('html').getAttribute('data-mrn-appearance');
  for(const mode of ['light','dark']){
   await appearance(mode);await go('index.php');await shot(mode+'-dashboard');
   await account();await shot(mode+'-account-full');await shot(mode+'-account','#wp-admin-bar-my-account > .ab-sub-wrapper');
   const accountData=await p.evaluate(()=>{const menu=document.querySelector('#wp-admin-bar-my-account > .ab-sub-wrapper'),text=document.querySelector('#wp-admin-bar-user-info .display-name'),button=document.querySelector('.mrn-appearance-choices button'),c=getComputedStyle(text);return {width:menu.getBoundingClientRect().width,height:menu.getBoundingClientRect().height,buttonHeight:button.getBoundingClientRect().height,text:c.color,background:getComputedStyle(menu).backgroundColor};});
   result.geometry.push({mode,account:accountData});result.contrast.push({mode,target:'profile-name',ratio:contrast(accountData.text,accountData.background)});
   if(phase==='after'){assert(accountData.width<=310);assert(accountData.buttonHeight<=40);assert(contrast(accountData.text,accountData.background)>=4.5);await scan(mode+'-account',['#wpadminbar']);}
   await p.locator('#wp-admin-bar-user-info > a').hover();await shot(mode+'-profile-hover','#wp-admin-bar-my-account > .ab-sub-wrapper');
   await p.locator('.mrn-appearance-choices button').first().focus();await p.keyboard.press('Escape');await p.mouse.move(1000,250);
   const icon=p.locator('#toplevel_page_options-general-page-updraftplus .wp-menu-image');
   if(phase==='after'){assert(await icon.evaluate(e=>getComputedStyle(e,'::after').maskImage!=='none'));const colors=await icon.evaluate(e=>({ink:getComputedStyle(e,'::after').backgroundColor,bg:getComputedStyle(document.querySelector('#adminmenu')).backgroundColor}));result.contrast.push({mode,target:'updraft-icon',ratio:contrast(colors.ink,colors.bg)});assert(contrast(colors.ink,colors.bg)>=3);}
   await openMenu();await p.locator('#contextual-help-link').hover();await shot(mode+'-view-menu','#wp-admin-bar-mrn-screen > .ab-sub-wrapper');
   const boxes=await p.evaluate(()=>{const rect=s=>{const r=document.querySelector(s).getBoundingClientRect();return {left:r.left,right:r.right,width:r.width};};return {menu:rect('#wp-admin-bar-mrn-screen > .ab-sub-wrapper'),button:rect('#contextual-help-link')};});result.geometry.push({mode,viewMenu:boxes});if(phase==='after')assert(boxes.button.left>=boxes.menu.left&&boxes.button.right<=boxes.menu.right);
   await p.locator('#show-settings-link').click();await expect(p.locator('#screen-options-wrap')).toBeFocused();await shot(mode+'-dashboard-options');if(phase==='after'){assert((await p.locator('#screen-meta').boundingBox()).width<=641);await scan(mode+'-dashboard-options',['#screen-options-wrap']);}
   await p.keyboard.press('Escape');await expect(p.locator('#wp-admin-bar-mrn-screen > a')).toBeFocused();
   await openMenu();await p.locator('#contextual-help-link').click();await expect(p.locator('#contextual-help-wrap')).toBeFocused();await shot(mode+'-help');await p.keyboard.press('Escape');
   await p.locator('#wp-admin-bar-site-name > a').focus();await p.keyboard.press('Enter');await shot(mode+'-site-menu','#wp-admin-bar-site-name > .ab-sub-wrapper');
   if(phase==='after'){await expect(p.locator('#wp-admin-bar-site-name #wp-admin-bar-dashboard > a')).toHaveAttribute('href',base+'/wp-admin/');const labels=await p.locator('#wp-admin-bar-site-name .ab-submenu > li > a').allTextContents();assert(labels.indexOf('Dashboard')<labels.indexOf('Visit Site'));}
   await p.mouse.move(1000,250);
   for(const [name,path,count]of [['pages','edit.php?post_type=page'],['team','edit.php?post_type=team_member'],['settings','options-general.php'],['empty-editor','post.php?post=2580&action=edit',0],['editor','post.php?post=2523&action=edit',19]]){
    await go(path,count);await shot(mode+'-'+name);if(!await p.locator('#show-settings-link').count()){await openMenu();await shot(mode+'-'+name+'-view-menu','#wp-admin-bar-mrn-screen > .ab-sub-wrapper');await p.locator('#contextual-help-link').click();await shot(mode+'-'+name+'-help');await closePanel();continue;}await openOptions();await shot(mode+'-'+name+'-options');
    if(phase==='after'){
     if(count!==undefined){await expect(p.locator('#screen-options-wrap .metabox-prefs legend')).toHaveText('Page Settings panels');await expect(p.locator('#screen-options-wrap .columns-prefs')).toBeHidden();await expect(p.locator('#screen-options-wrap label.mrn-managed-region-toggle')).toHaveCount(3);assert(await p.locator('#screen-options-wrap label.mrn-managed-region-toggle').evaluateAll(es=>es.every(e=>!e.getClientRects().length&&e.querySelector('input').checked)));await p.locator('.mrn-view-settings').click();await expect(p.locator('body')).toHaveClass(/mrn-workspace-settings/);await expect(p.locator('.mrn-mode-settings')).toBeFocused();await p.locator('.mrn-mode-content').click();assert(await p.locator('.mrn-workspace-field').evaluateAll(es=>es.every(e=>e.closest('form')?.id==='post')));if(count===0)await expect(p.locator('.mrn-region-add:visible')).toHaveCount(3);await openOptions();}
     else if(name==='pages'||name==='team'){await expect(p.locator('#screen-options-wrap .screen-per-page')).toBeVisible();await expect(p.locator('#screen-options-wrap .metabox-prefs input').first()).toBeVisible();}
     await scan(mode+'-'+name+'-options',['#screen-options-wrap']);
    }
    if(name==='empty-editor'){
     await p.setViewportSize({width:390,height:844});await shot(mode+'-mobile-options');if(phase==='after'){const r=await p.locator('#screen-meta').boundingBox();assert(r.x>=0&&r.x+r.width<=390);assert(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));}await p.setViewportSize({width:1487,height:1100});
    }
    await closePanel();
   }
  }
  if(phase==='after'){
   // Native list-table per-page and column preference forms, restored after checks.
   await go('edit.php?post_type=team_member');await openOptions();
   const perPage=p.locator('#screen-options-wrap .screen-per-page'),oldCount=await perPage.inputValue();
   try{await perPage.fill('5');await Promise.all([p.waitForNavigation({waitUntil:'load'}),p.locator('#screen-options-apply').click()]);await openOptions();await expect(perPage).toHaveValue('5');assert(await p.locator('#the-list > tr').count()<=5);result.checks.push('CPT native items-per-page persisted and affected the visible row count');}
   finally{await perPage.fill(oldCount);await Promise.all([p.waitForNavigation({waitUntil:'load'}),p.locator('#screen-options-apply').click()]);}
   await openOptions();const date=p.locator('#date-hide'),dateBefore=await date.isChecked();
   try{const done=p.waitForResponse(r=>r.request().postData()?.includes('action=hidden-columns')&&r.status()===200);await date.setChecked(!dateBefore);await done;await p.reload({waitUntil:'load'});await openOptions();assert.equal(await date.isChecked(),!dateBefore);result.checks.push('Native list column visibility persisted');}
   finally{const done=p.waitForResponse(r=>r.request().postData()?.includes('action=hidden-columns')&&r.status()===200);await date.setChecked(dateBefore);await done;}
   await go('post.php?post=2580&action=edit',0);await openOptions();const slug=p.locator('#slugdiv-hide'),slugBefore=await slug.isChecked();
   try{const done=p.waitForResponse(r=>r.request().postData()?.includes('action=closed-postboxes')&&r.status()===200);await slug.setChecked(!slugBefore);await done;await p.reload({waitUntil:'load'});await openOptions();assert.equal(await slug.isChecked(),!slugBefore);await p.locator('.mrn-view-settings').click();assert.equal(await p.locator('#slugdiv').isVisible(),!slugBefore);result.checks.push('Optional Page Settings panel visibility uses native persistence');}
   finally{await openOptions();const done=p.waitForResponse(r=>r.request().postData()?.includes('action=closed-postboxes')&&r.status()===200);await slug.setChecked(slugBefore);await done;}
   await go('post.php?post=2524&action=edit',32);assert(await p.locator('.mrn-workspace-field').evaluateAll(es=>es.every(e=>e.closest('form')?.id==='post')));result.checks.push('0/19/32 sections retain native form ancestry and required region entry points');
   await appearance('system');await go('index.php');await expect(p.locator('html')).toHaveAttribute('data-mrn-appearance','system');for(const colorScheme of ['light','dark']){await p.emulateMedia({colorScheme});const scheme=await p.locator('body').evaluate(e=>getComputedStyle(e).colorScheme);assert.equal(scheme,colorScheme);}result.checks.push('Light/Dark/System persisted; System responds to operating-system color scheme');
   for(const [name,violations]of Object.entries(result.axe))assert.deepEqual(violations.map(v=>v.id),[],name);
  }
  assert.deepEqual(result.errors,[]);result.passed=true;
 }catch(e){result.failure=e.message;await shot('failure');throw e;}
 finally{if(original){try{await go('index.php');await appearance(original);}catch(e){result.restoreFailure=e.message;process.exitCode=1;}}fs.writeFileSync(out+'/results.json',JSON.stringify(result,null,2)+'\n');await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
