'use strict';
const fs=require('fs'),assert=require('assert/strict'),path=require('path');
const req=require('module').createRequire('/Users/khofmeyer/Development/MRN-qa-engine/package.json');
const {chromium,expect:baseExpect}=req('@playwright/test'),Axe=req('@axe-core/playwright').default,expect=baseExpect.configure({timeout:20000});
const base='https://admin-editor-pilot.mrndev.io',phase=process.argv[2]||'candidate',out=path.resolve('docs/admin-editor-pilot/appearance/review/page-settings-20261104');
// Use a private, short-lived QA cookie file. Never commit credentials.
if(!process.env.MRN_QA_AUTH_FILE)throw new Error('Set MRN_QA_AUTH_FILE to the private QA session file.');
const auth=JSON.parse(fs.readFileSync(process.env.MRN_QA_AUTH_FILE));
(async()=>{const browser=await chromium.launch(),c=await browser.newContext({viewport:{width:1487,height:1000}});await c.addCookies(auth);const p=await c.newPage();p.setDefaultTimeout(20000);const result={phase,checks:[],errors:[],requests:[],axe:[]};p.on('pageerror',e=>result.errors.push(e.message));let calls=[];
if(phase==='candidate'){
 for(const [url,file,type]of [['**/mrn-editor-lockdown/assets/section-navigator.js*','mu-plugins/mrn-editor-lockdown/assets/section-navigator.js','text/javascript'],['**/mrn-admin-ui-css/mrn-editor-workspace.css*','mu-plugins/mrn-admin-ui-css/mrn-editor-workspace.css','text/css']])await c.route(url,r=>r.fulfill({status:200,contentType:type,body:fs.readFileSync(file,'utf8')}));
}
await c.route('**/admin-ajax.php',async r=>{const d=new URLSearchParams(r.request().postData()||'');if(d.get('action')==='closed-postboxes'){calls.push(d);await r.fulfill({status:200,body:'1',contentType:'text/plain'});}else await r.continue();});
async function go(url){const r=await p.goto(base+'/wp-admin/'+url,{waitUntil:'load',timeout:120000});assert.equal(r.status(),200);await expect(p.locator('body')).toHaveClass(/mrn-workspace/);}
async function prefs(){await p.locator('#wp-admin-bar-mrn-screen > a').click();await p.locator('#show-settings-link').click();await expect(p.locator('#screen-options-wrap')).toBeVisible();}
async function close(){await p.locator('.mrn-screen-close').click();await expect(p.locator('#screen-options-wrap')).toBeHidden();}
async function change(id,checked){const count=calls.length;await p.locator('#'+id+'-hide').setChecked(checked);await expect.poll(()=>calls.length).toBeGreaterThan(count);}
function hidden(){return (calls.at(-1).get('hidden')||'').split(',').filter(Boolean);}
function record(label){result.requests.push({label,hidden:hidden(),closed:calls.at(-1).get('closed'),page:calls.at(-1).get('page')});}
async function data(){return p.locator('#post').evaluate(f=>Array.from(new FormData(f)).filter(([k])=>k==='post_title'||k==='post_name'||k==='post_content'||k==='post_parent'||k==='page_template'||k.startsWith('acf[')).map(([k,v])=>[k,typeof v==='string'?v:'file']));}
try{
 await go('post-new.php?post_type=page');await p.locator('.mrn-mode-settings').click();await p.screenshot({path:out+'/'+phase+'-initial-settings.png'});const original=await data();
 await p.locator('.mrn-mode-content').click();await prefs();for(const id of ['postimagediv','pageparentdiv','authordiv','mrn-layout-import','seopress_metabox_opener']){if(!await p.locator('#'+id+'-hide').isChecked())await change(id,true);}
 // Trigger the native persistence path while settings are parked by the Content tab.
 if(await p.locator('#slugdiv-hide').isChecked())await change('slugdiv',false);else{await change('slugdiv',true);await change('slugdiv',false);}
 record('Hide Slug from Content view');
 if(phase==='baseline'){assert(hidden().includes('postimagediv')&&hidden().includes('pageparentdiv'));result.checks.push('Reproduced: checked Page Settings panels are erroneously included in native hidden payload');result.passed=true;return;}
 assert.deepEqual(hidden().filter(id=>id!=='submitdiv').sort(),['slugdiv']);assert.equal(calls.at(-1).get('closedpostboxesnonce'),await p.locator('#closedpostboxesnonce').inputValue());assert.equal(calls.at(-1).get('page'),'page');await change('slugdiv',true);assert.deepEqual(hidden().filter(id=>id!=='submitdiv'),[]);await close();
 result.checks.push('Native preference POST uses only unchecked boxes from Content view; original nonce and page preserved');
 await p.locator('.mrn-mode-settings').click();for(const id of ['postimagediv','pageparentdiv','authordiv','mrn-layout-import','seopress_metabox_opener'])await expect(p.locator('#'+id)).toBeVisible();
 const count=calls.length;await p.locator('#pageparentdiv .handlediv').click();await expect.poll(()=>calls.length).toBeGreaterThan(count);assert.deepEqual(hidden().filter(id=>id!=='submitdiv'),[]);assert(calls.at(-1).get('closed').split(',').includes('pageparentdiv'));await p.locator('#pageparentdiv .handlediv').click();result.checks.push('Settings collapse preserves hidden preferences and core closed-state payload');
 await prefs();for(const id of ['postimagediv','pageparentdiv','authordiv','mrn-layout-import'])await change(id,false);await close();const blank=await p.locator('#side-sortables').boundingBox();assert.equal(blank.height,0);result.checks.push('No 250px empty sidebar placeholder when all side panels are intentionally hidden');
 await prefs();for(const id of ['postimagediv','pageparentdiv','authordiv','mrn-layout-import'])await change(id,true);await close();
 for(const mode of ['light','dark']){await p.locator('html').evaluate((e,m)=>e.dataset.mrnAppearance=m,mode);await p.screenshot({path:out+'/'+phase+'-'+mode+'-settings.png',fullPage:true});const v=(await new Axe({page:p}).include('.mrn-workspace-modes').include('#pageparentdiv').include('#postimagediv').include('#authordiv').include('#mrn-layout-import').include('#slugdiv').withTags(['wcag2a','wcag2aa','wcag21aa']).analyze()).violations;result.axe.push({mode,violations:v.map(e=>({id:e.id,targets:e.nodes.map(n=>n.target)}))});assert.equal(v.length,0);}
 assert.deepEqual(await data(),original);result.checks.push('New-page native fields/form values unchanged; Light/Dark panel and workspace axe scans pass');
 await p.setViewportSize({width:390,height:844});await p.screenshot({path:out+'/'+phase+'-mobile-settings.png',fullPage:true});assert.equal(await p.locator('#side-sortables').evaluate(e=>getComputedStyle(e).gridTemplateColumns.split(' ').length),1);result.checks.push('390px Page Settings uses one column');
 await p.setViewportSize({width:1487,height:1000});await go('post.php?post=2523&action=edit');const existing=await data();await prefs();for(const id of ['postimagediv','pageparentdiv','authordiv','mrn-layout-import','seopress_metabox_opener']){if(!await p.locator('#'+id+'-hide').isChecked())await change(id,true);}await change('slugdiv',false);assert(!hidden().includes('postimagediv'));await change('slugdiv',true);await close();await p.locator('.mrn-mode-settings').click();await expect(p.locator('#pageparentdiv')).toBeVisible();assert.deepEqual(await data(),existing);result.checks.push('19-section existing page preserves fields and settings preferences through workspace toggles');
 if(phase==='installed'){
  await c.unroute('**/admin-ajax.php');await go('post.php?post=2580&action=edit');await prefs();
  for(const id of ['postimagediv','pageparentdiv','authordiv','mrn-layout-import','seopress_metabox_opener'])await expect(p.locator('#'+id+'-hide')).toBeChecked();
  async function persistAuthor(checked){const response=p.waitForResponse(r=>r.url().endsWith('/admin-ajax.php')&&(r.request().postData()||'').includes('action=closed-postboxes'));await p.locator('#authordiv-hide').setChecked(checked);const r=await response;assert.equal(r.status(),200);assert.equal((await r.text()).trim(),'1');}
  try{
   await persistAuthor(false);await go('post.php?post=2580&action=edit');await expect(p.locator('#authordiv-hide')).not.toBeChecked();await p.locator('.mrn-mode-settings').click();await expect(p.locator('#authordiv')).toBeHidden();await expect(p.locator('#postimagediv')).toBeVisible();await expect(p.locator('#pageparentdiv')).toBeVisible();
  }finally{await prefs();if(!await p.locator('#authordiv-hide').isChecked())await persistAuthor(true);}
  await go('post.php?post=2580&action=edit');await expect(p.locator('#authordiv-hide')).toBeChecked();await p.locator('.mrn-mode-settings').click();await expect(p.locator('#authordiv')).toBeVisible();await p.screenshot({path:out+'/installed-restored-settings.png',fullPage:true});result.checks.push('Real native AJAX visibility save/reload changes only Author, then restores it; all five recovered panels persist');
 }
 assert.deepEqual(result.errors,[]);result.passed=true;
}catch(e){result.failure=e.message;await p.screenshot({path:out+'/'+phase+'-failure.png',fullPage:true});throw e;}
finally{fs.writeFileSync(out+'/'+phase+'-results.json',JSON.stringify(result,null,2));await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
