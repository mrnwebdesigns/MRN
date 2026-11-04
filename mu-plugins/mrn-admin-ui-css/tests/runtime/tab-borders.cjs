'use strict';
// Exact review target; use a private short-lived session after the backup gate.
const fs=require('fs'),path=require('path'),assert=require('assert/strict');
const req=require('module').createRequire('/Users/khofmeyer/Development/MRN-qa-engine/package.json');
const {chromium,expect:baseExpect}=req('@playwright/test'),Axe=req('@axe-core/playwright').default,expect=baseExpect.configure({timeout:20000});
const base='https://admin-editor-pilot.mrndev.io',phase=process.argv[2]||'candidate',out=path.resolve('docs/admin-editor-pilot/appearance/review/tab-borders-20261104');
assert(process.env.MRN_QA_AUTH_FILE,'Private QA session required');
const files=['mrn-admin-shell.css','mrn-editor-workspace.css','mrn-modern-admin.css'];
(async()=>{const browser=await chromium.launch(),c=await browser.newContext({viewport:{width:1487,height:1000}});await c.addCookies(JSON.parse(fs.readFileSync(process.env.MRN_QA_AUTH_FILE)));const p=await c.newPage();p.setDefaultTimeout(20000);const result={phase,checks:[],styles:[],axe:[],errors:[]};p.on('pageerror',e=>result.errors.push(e.message));
if(phase==='candidate')for(const file of files)await c.route('**/mrn-admin-ui-css/'+file+'*',r=>r.fulfill({status:200,contentType:'text/css',body:fs.readFileSync('mu-plugins/mrn-admin-ui-css/'+file,'utf8')}));
async function go(url){const response=await p.goto(base+'/wp-admin/'+url,{waitUntil:'load',timeout:120000});assert.equal(response.status(),200);}
async function fields(){return p.locator('#post').evaluate(f=>Array.from(new FormData(f)).filter(([k])=>k.startsWith('acf[')||['post_title','post_content','post_name','post_parent','page_template'].includes(k)).map(([k,v])=>[k,typeof v==='string'?v:'file']));}
async function theme(mode){await p.locator('html').evaluate((e,m)=>e.dataset.mrnAppearance=m,mode);}
async function scan(selector,label){const v=(await new Axe({page:p}).include(selector.replaceAll(':visible','')).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze()).violations;result.axe.push({label,violations:v.map(x=>({id:x.id,targets:x.nodes.map(n=>n.target)}))});assert.equal(v.length,0);}
async function inspect(selector,label){const a=await p.locator(selector).first().evaluate(e=>{const s=getComputedStyle(e),parent=e.closest('.acf-tab-wrap')||e.parentElement,list=e.closest('.acf-tab-group')||e.parentElement;return {top:s.borderTopWidth,bottom:s.borderBottomWidth,left:s.borderLeftWidth,shadow:s.boxShadow,active:s.borderBottomColor,color:s.color,wrapperTop:getComputedStyle(parent).borderTopWidth,wrapperBottom:getComputedStyle(parent).borderBottomWidth,listTop:getComputedStyle(list).borderTopWidth,listBottom:getComputedStyle(list).borderBottomWidth,margin:s.margin,height:e.getBoundingClientRect().height};});result.styles.push({label,...a});if(phase!=='baseline'){assert.equal(a.top,'0px');assert.equal(a.left,'0px');assert.equal(a.bottom,'2px');assert.equal(a.shadow,'none');assert.equal(a.wrapperTop,'0px');assert.equal(a.wrapperBottom,'0px');assert.equal(a.listTop,'0px');assert.equal(a.listBottom,'0px');}}
try{
 await go('post.php?post=2523&action=edit');await expect(p.locator('body')).toHaveClass(/mrn-workspace/);await expect(p.locator('.mrn-editor-section:visible')).toHaveCount(1);const original=await fields();
 const top='.mrn-editor-section:visible > .acf-fields > .acf-tab-wrap.-top',nested='.mrn-editor-section:visible .acf-field-repeater .acf-tab-wrap.-top:visible';
 await expect(p.locator(nested).first()).toBeVisible();
 for(const mode of ['light','dark']){
  await theme(mode);await p.screenshot({path:out+'/'+phase+'-'+mode+'-workspace.png'});await p.locator(top).screenshot({path:out+'/'+phase+'-'+mode+'-tabs.png'});
  await inspect(top+' li.active > a',mode+' parent');if(phase!=='baseline')assert.equal(await p.locator(top+' + .acf-field-tab + .acf-field').evaluate(e=>getComputedStyle(e).borderTopWidth),'0px');await inspect(nested+' li.active > a',mode+' nested');
  await scan(top,mode+' parent tabs');await scan(nested,mode+' nested tabs');
  const tab=p.locator(top+' a').filter({hasText:/^Configs$/});await tab.focus();await p.keyboard.press('Enter');await expect(tab.locator('..')).toHaveClass(/active/);const focus=await tab.evaluate(e=>({visible:e.matches(':focus-visible'),width:getComputedStyle(e).outlineWidth}));assert(focus.visible);assert(parseFloat(focus.width)>=2);await p.screenshot({path:out+'/'+phase+'-'+mode+'-focus.png'});
  await tab.hover();await scan(top,mode+' hover and focus');await p.locator(top+' a').filter({hasText:/^Content$/}).click();
  const n=p.locator(nested).first(),nestedLabel=await n.locator('li.active a').innerText();await n.locator('a').filter({hasText:/^Configs$/}).click();await expect(n.locator('li.active a')).toHaveText('Configs');await n.locator('a').filter({hasText:new RegExp('^'+nestedLabel+'$')}).click();
 }
 const perf=await c.newCDPSession(p);await perf.send('Performance.enable');const before=await perf.send('Performance.getMetrics');for(let i=0;i<5;i++){await p.locator(top+' a').filter({hasText:/^Configs$/}).click();await p.locator(top+' a').filter({hasText:/^Content$/}).click();}await p.evaluate(()=>new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r))));const after=await perf.send('Performance.getMetrics');result.tabPerformance=Object.fromEntries(['TaskDuration','ScriptDuration','RecalcStyleDuration','LayoutDuration'].map(k=>[k,(after.metrics.find(m=>m.name===k).value-before.metrics.find(m=>m.name===k).value)*1000]));await perf.detach();assert.deepEqual(await fields(),original);result.checks.push('Parent/nested native ACF tabs switch via keyboard and pointer with unchanged form values; Light/Dark axe and focus pass');
 await p.locator('.mrn-mode-settings').click();await expect(p.locator('#pageparentdiv')).toBeVisible();await p.locator('.mrn-mode-content').click();assert.deepEqual(await fields(),original);result.checks.push('Content/Page Settings navigation and recovered settings panels remain intact');
 await p.setViewportSize({width:390,height:844});await theme('light');await p.locator(top).scrollIntoViewIfNeeded();await p.screenshot({path:out+'/'+phase+'-mobile-workspace.png',fullPage:true});if(phase!=='baseline'){const bounds=await p.locator(top+' a').evaluateAll(es=>es.map(e=>{const r=e.getBoundingClientRect();return {x:r.x,right:r.right,height:r.height};}));assert(bounds.every(r=>r.x>=0&&r.right<=390&&r.height>=44));}result.checks.push('390px tabs wrap within the viewport with 44px targets');
 await p.setViewportSize({width:1487,height:1000});await go('admin.php?page=mrn-notifications-center');const native='.mrn-modern-admin .wrap > .nav-tab-wrapper';await expect(p.locator(native)).toBeVisible();
 for(const mode of ['light','dark']){await theme(mode);await inspect(native+' > .nav-tab-active',mode+' native settings');await p.screenshot({path:out+'/'+phase+'-'+mode+'-settings.png'});await scan(native,mode+' native settings tabs');}
 result.checks.push('MRN Notifications native navigation shares the tab treatment in both themes');
 assert.deepEqual(result.errors,[]);result.passed=true;
}catch(e){result.failure=e.message;await p.screenshot({path:out+'/'+phase+'-failure.png'});throw e;}
finally{fs.writeFileSync(out+'/'+phase+'-results.json',JSON.stringify(result,null,2));await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
