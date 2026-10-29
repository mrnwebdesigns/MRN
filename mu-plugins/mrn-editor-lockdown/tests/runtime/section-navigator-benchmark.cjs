const fs = require('node:fs');
const { createRequire } = require('node:module');
const req = createRequire('/Users/khofmeyer/Development/MRN-qa-engine/package.json');
const { chromium } = req('@playwright/test');
const base = 'https://admin-editor-pilot.localhost';
const selector = '.acf-field[data-name="page_content_rows"] > .acf-input > .acf-flexible-content > .values > .layout:not(.acf-clone)';
(async () => {
 const mode = process.argv[2] || 'before';
 const browser = await chromium.launch({headless:true});
 const context = await browser.newContext({ignoreHTTPSErrors:true,viewport:{width:1440,height:1000}});
 await context.addCookies(JSON.parse(fs.readFileSync('/Users/khofmeyer/Development/MRN-sites/admin-editor-pilot/logs/administrator-auth.json')));
 await context.route('**/*',r => new URL(r.request().url()).hostname === 'admin-editor-pilot.localhost' ? r.continue() : r.abort());
 const results = {mode,conditions:'Chromium headless, 1440x1000, new page each sample, shared warm context, local VM, external requests blocked',pages:{}};
 for (const [kind,id] of [['real',2523],['stress',2524]]) {
  const samples=[];
  for(let i=0;i<5;i++) {
   const p=await context.newPage(); const errors=[]; p.on('pageerror',e=>errors.push(e.message));
   await p.addInitScript(()=>{window.pilotLongTasks=[];new PerformanceObserver(list=>list.getEntries().forEach(e=>window.pilotLongTasks.push(e.duration))).observe({type:'longtask',buffered:true});});
   await p.goto(base+'/wp-admin/post.php?post='+id+'&action=edit',{waitUntil:'load',timeout:120000});
   await p.waitForFunction(s => window.acf && document.querySelectorAll(s).length > 0,selector);
   if(mode==='after') await p.locator('.mrn-section-link').first().waitFor({state:'attached'});
   await p.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));
   const sample=await p.evaluate(s=>{const n=performance.getEntriesByType('navigation')[0];const r=Array.from(document.querySelectorAll(s));return {dcl_ms:n.domContentLoadedEventEnd,load_ms:n.loadEventEnd,ready_ms:performance.now(),heap_bytes:performance.memory?.usedJSHeapSize,rows:r.length,scroll_height:document.documentElement.scrollHeight,target_document_y:r.at(-1).getBoundingClientRect().top+scrollY,viewport:innerHeight,long_tasks:window.pilotLongTasks.length,long_task_ms:window.pilotLongTasks.reduce((a,b)=>a+b,0),resources:performance.getEntriesByType('resource').length};},selector);
   samples.push({...sample,errors});
   if(i===0) {
    const inventory=await p.locator(selector).evaluateAll(rs=>rs.map((r,i)=>({index:i+1,type:r.dataset.layout,title:r.querySelector('.acf-fc-layout-title')?.textContent.trim(),fields:Array.from(r.querySelectorAll('.acf-field')).filter(f=>f.closest('.layout')===r).map(f=>({name:f.dataset.name,type:f.dataset.type})).filter(f=>['heading','internal_name','label'].includes(f.name))})));
    results.pages[kind]={inventory};
    if(kind==='real') await p.screenshot({path:`outputs/admin-editor-pilot/${mode}-editor.png`});
   }
   await p.close();
  }
  results.pages[kind].samples=samples;
 }
 fs.writeFileSync(`outputs/admin-editor-pilot/${mode}-metrics.json`,JSON.stringify(results,null,2));
 console.log(JSON.stringify({mode,pages:Object.fromEntries(Object.entries(results.pages).map(([k,v])=>[k,{rows:v.samples[0].rows,samples:v.samples}]))},null,2));
 await browser.close();
})().catch(e=>{console.error(e);process.exit(1)});
