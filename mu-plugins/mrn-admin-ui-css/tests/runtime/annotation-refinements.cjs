'use strict';
// Authorized Local Hub only. Test edits stay unsaved; native save QA is separate.
const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict'),req=require('node:module').createRequire('/Users/khofmeyer/Development/MRN-qa-engine/package.json'),{chromium,expect:baseExpect}=req('@playwright/test'),Axe=req('@axe-core/playwright').default;
const expect=baseExpect.configure({timeout:30000}),base='https://admin-editor-pilot.localhost',out='outputs/admin-refinements',baseline=process.argv.includes('--baseline');
const root='.acf-field[data-name="page_content_rows"] > .acf-input > .acf-flexible-content > .values > .layout:not(.acf-clone)';
(async()=>{fs.mkdirSync(out+'/after',{recursive:true});const b=await chromium.launch(),c=await b.newContext({ignoreHTTPSErrors:true,viewport:{width:1487,height:1100}});await c.addCookies(JSON.parse(fs.readFileSync('/Users/khofmeyer/Development/MRN-sites/admin-editor-pilot/logs/administrator-auth.json')));if(baseline)await c.route('**/*',r=>{
 const url=new URL(r.request().url());if(url.origin!==base)return r.abort();
 if(baseline&&/\.(css|js)$/.test(url.pathname)){
  const relative=url.pathname.split('/wp-content/mu-plugins/')[1];
  const file=relative&&path.join(out,'runtime-before',relative);
  if(file&&fs.existsSync(file)){let body=fs.readFileSync(file,'utf8');if(relative.endsWith('section-navigator.js'))body='mrnSectionNavigator.search="Find a section";\n'+body;return r.fulfill({status:200,contentType:file.endsWith('.css')?'text/css':'application/javascript',body});}
 }
 return r.continue();
});const p=await c.newPage(),cdp=await c.newCDPSession(p);await cdp.send('Network.enable');await cdp.send('Network.setBlockedURLs',{urlPatterns:[{urlPattern:base+'/*',block:false},{urlPattern:'*://*:*/*',block:true}],urls:['http://*','https://*']});const result={checks:[],geometry:[],axe:{},errors:[],metrics:{}};p.setDefaultNavigationTimeout(180000);p.on('dialog',d=>d.accept());p.on('pageerror',e=>result.errors.push(e.message));const check=t=>{result.checks.push(t);console.log(t);};
async function go(path){console.log('Loading',path);await p.goto(base+'/wp-admin/'+path,{waitUntil:'load'});}
async function form(){return p.evaluate(()=>{tinymce.triggerSave();return [...new FormData(document.querySelector('#post'))].filter(([k,v])=>k.startsWith('acf[')&&typeof v==='string');});}
async function shot(name){await p.screenshot({path:out+'/'+(baseline?'before':'after')+'/'+name+'.png',fullPage:true});}
try{
for(const mode of ['light','dark']){
 for(const [name,path]of [['dashboard','index.php'],['pages','edit.php?post_type=page'],['settings','options-general.php?page=mrn-config-helper'],['editor','post.php?post=2523&action=edit']].filter(([name])=>!process.argv.includes('--editor-only')||name==='editor')){
  await go(path);await p.evaluate(m=>{document.documentElement.dataset.mrnAppearance=m;document.body.classList.remove('folded');},mode);
  if(name==='editor'){await expect(p.locator('.mrn-section-link')).toHaveCount(19);console.log('Native editor state',await p.evaluate(()=>({count:window.tinymce?.editors.length,editors:window.tinymce?.editors.map(e=>({id:e.id,initialized:e.initialized})),ready:document.readyState})));await p.waitForFunction(()=>window.tinymce?.editors.length>=3&&tinymce.editors.every(e=>e.initialized),{},{timeout:30000});}
  await shot(mode+'-'+name);
  if(baseline)continue;
  if(name==='dashboard'){
   await expect(p.locator('.mrn-page-header #show-settings-link')).toBeVisible();await p.locator('#show-settings-link').focus();await p.keyboard.press('Enter');await expect(p.locator('#screen-options-wrap')).toBeVisible();await p.locator('#show-settings-link').focus();await p.keyboard.press('Enter');await expect(p.locator('#screen-options-wrap')).toBeHidden();
   await p.locator('#contextual-help-link').click();await expect(p.locator('#contextual-help-wrap')).toBeVisible();await p.locator('#contextual-help-link').click();await expect(p.locator('#contextual-help-wrap')).toBeHidden();
   assert.equal(await p.locator('#seopress-ab-icon').evaluate(e=>getComputedStyle(e).backgroundImage),'none');assert(await p.locator('#seopress-ab-icon').evaluate(e=>getComputedStyle(e).maskImage!=='none'));
   await expect(p.locator('#toplevel_page_relevanssi-relevanssi .mrn-shell-mask')).toHaveCount(1);
   await p.locator('#menu-pages>a').hover();await shot(mode+'-sidebar-hover');await p.locator('#menu-pages>a').focus();await p.keyboard.press('Tab');await shot(mode+'-sidebar-keyboard');await p.mouse.move(1200,400);
   result.axe[mode+'-shell']=(await new Axe({page:p}).include('#adminmenu').include('#wpadminbar').include('.mrn-page-header').withTags(['wcag2a','wcag2aa','wcag21aa']).analyze()).violations;
   check(mode+': native Screen Options/Help open and close; both named icons use semantic masks; navigation hover and keyboard submenu captured');
  }
  if(name!=='editor')continue;
  const original=await form();
  result.metrics[mode+'-search-ms']=await p.evaluate(()=>{const input=document.querySelector('#mrn-section-search');const query=value=>{const start=performance.now();input.value=value;input.dispatchEvent(new Event('input',{bubbles:true}));return performance.now()-start;};const cold=query('trilliant'),warm=query('growth');query('');return {cold_index_and_filter:cold,cached_filter:warm};});
  for(const width of [1487,800,390]){
   await p.setViewportSize({width,height:width===390?844:1100});
   for(const scroll of [0,600,1500]){
    await p.evaluate(y=>scrollTo(0,y),scroll);await p.waitForTimeout(80);
    const geometry=await p.evaluate(()=>{const bar=document.querySelector('#mrn-universal-sticky-toolbar').getBoundingClientRect(),header=document.querySelector('#mrn-workspace-header').getBoundingClientRect();return {bar:{top:bar.top,bottom:bar.bottom},header:{top:header.top,bottom:header.bottom},overflow:document.documentElement.scrollWidth>innerWidth,hits:[...document.querySelectorAll('#mrn-workspace-header button,#mrn-workspace-header a')].map(e=>{const r=e.getBoundingClientRect();return e.contains(document.elementFromPoint(r.x+r.width/2,r.y+r.height/2));})};});
    result.geometry.push({mode,width,scroll,...geometry});assert(geometry.header.top>=geometry.bar.bottom-1);assert(geometry.hits.every(Boolean));assert(!geometry.overflow);
   }
   if(width===390)await shot(mode+'-editor-mobile');
  }
  await p.setViewportSize({width:1487,height:1100});await p.evaluate(()=>scrollTo(0,0));
  await p.locator('.mrn-section-link').first().click();const nested=p.locator(root).first().locator('.mrn-nested-component[data-name="card_items"]').first();
  await expect(nested.locator(':scope > .acf-input > .acf-repeater > table > tbody > .acf-row:not(.acf-clone) > td.acf-fields > .mrn-nested-header').first()).toBeVisible();await nested.locator(':scope > .acf-input > .acf-repeater > table > tbody > .acf-row:not(.acf-clone) > td.acf-fields > .mrn-nested-header').first().focus();await p.keyboard.press('Enter');await expect(nested.locator(':scope > .acf-input > .acf-repeater > table > tbody > .acf-row:not(.acf-clone) > td.acf-fields > .mrn-nested-header').first()).toHaveAttribute('aria-expanded','false');assert.deepEqual(await form(),original);await p.keyboard.press('Enter');await expect(nested.locator(':scope > .acf-input > .acf-repeater > table > tbody > .acf-row:not(.acf-clone) > td.acf-fields > .mrn-nested-header').first()).toHaveAttribute('aria-expanded','true');
  const expandedHeight=await nested.evaluate(e=>e.getBoundingClientRect().height);await nested.locator(':scope > .acf-label > .mrn-nested-all').click();result.metrics[mode+'-nested-height']={expanded:expandedHeight,collapsed:await nested.evaluate(e=>e.getBoundingClientRect().height)};assert(await nested.locator(':scope > .acf-input > .acf-repeater > table > tbody > .acf-row:not(.acf-clone) > td.acf-fields > .mrn-nested-header').evaluateAll(es=>es.every(e=>e.getAttribute('aria-expanded')==='false')));assert.deepEqual(await form(),original);await shot(mode+'-nested-collapsed');await nested.locator(':scope > .acf-label > .mrn-nested-all').click();
  const text=p.locator(root).first().locator('.acf-field[data-name="heading"] input[type=text]').first(),saved=await text.inputValue();await text.fill('MRN searchable unsaved heading');
  await p.locator('#mrn-section-search').fill('searchable unsaved');await expect(p.locator('.mrn-section-link:visible')).toHaveCount(1);await expect(p.locator('.mrn-section-match')).toContainText('MRN searchable unsaved heading');await p.locator('#mrn-section-search').press('Enter');await expect(text).toBeFocused();await text.fill(saved);await p.locator('#mrn-section-search').fill('');
  // A nested tab/row match must reveal via ACF, without changing any stored field.
  const match=await p.locator(root).first().locator('.acf-field[data-name="card_items"] .acf-field[data-name="item_label"] input[type=text]').evaluateAll(es=>{const e=es.find(e=>!e.disabled);return e&&{id:e.id,value:e.value};});
  assert(match);await p.locator('#'+match.id).fill('MRN nested search target');await nested.locator(':scope > .acf-label > .mrn-nested-all').click();await p.locator('#mrn-section-search').fill('nested search target');await expect(p.locator('.mrn-section-link:visible')).toHaveCount(1);await p.locator('#mrn-section-search').press('Enter');await expect(p.locator('#'+match.id)).toBeFocused();await p.locator('#'+match.id).fill(match.value);await p.locator('#mrn-section-search').fill('');assert.deepEqual(await form(),original);
  // Current Visual editor values, including unsaved input and HTML escaping.
  const rich=await p.evaluate(()=>{const e=tinymce.editors.find(e=>!e.isHidden()&&document.getElementById(e.id)?.closest('.mrn-workspace-field'));return e&&{id:e.id,html:e.getContent()};});
  assert(rich,'Expected initialized native ACF rich text');if(rich){await p.evaluate(({id})=>{const e=tinymce.get(id);e.setContent('<p>MRN rich unsaved &lt;sample&gt;</p>');e.fire('change');},rich);await p.locator('#mrn-section-search').fill('rich unsaved');await expect(p.locator('.mrn-section-match')).toContainText('MRN rich unsaved <sample>');await p.locator('#mrn-section-search').press('Enter');await p.evaluate(({id,html})=>{const e=tinymce.get(id);e.setContent(html);e.fire('change');e.save();},rich);await p.locator('#mrn-section-search').fill('');}
  await shot(mode+'-editor-refined');
  result.axe[mode+'-editor']=(await new Axe({page:p}).include('#mrn-workspace-header').include('#mrn-section-outline').include('#mrn-editor-tools').include('.mrn-nested-header').include('.mrn-nested-all').include('.acf-tab-wrap').withTags(['wcag2a','wcag2aa','wcag21aa']).analyze()).violations;
  check(mode+': fixed stack hit tests pass at 3 widths × 3 scroll positions; nested keyboard collapse and content search preserve FormData and reveal native fields');
 }
}
assert.deepEqual(result.errors,[]);for(const [name,violations]of Object.entries(result.axe)){assert.deepEqual(violations.map(v=>v.id),[],name);}fs.writeFileSync(out+'/annotation-results.json',JSON.stringify(result,null,2));check('No page errors or owned WCAG A/AA violations');
}catch(error){result.failure=error.message;fs.writeFileSync(out+'/annotation-results.json',JSON.stringify(result,null,2));throw error;}finally{await b.close();}})().catch(e=>{console.error(e);process.exit(1)});
