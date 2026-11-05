'use strict';
const fs = require('node:fs');
const crypto = require('node:crypto');
const req = require('node:module').createRequire('/Users/khofmeyer/Development/MRN-qa-engine/package.json');
const { chromium } = req('@playwright/test');
const source = require('node:path').resolve(__dirname, '../../../../../mu-plugins/mrn-editor-lockdown/assets/section-navigator.js');
const output = __dirname;
const pages = [['empty',2580,0],['real19',2523,19],['stress32',2524,32]];
const results = [];
const digest = s => crypto.createHash('sha256').update(s).digest('hex');
async function settled(p) {
 await p.waitForFunction(() => document.body.classList.contains('mrn-editor-page-ready') && !document.documentElement.classList.contains('mrn-editor-starting') && window.tinymce && tinymce.editors.every(e => e.initialized));
 await p.waitForLoadState('load'); await p.waitForTimeout(800);
}
async function measure(d) {
 const m = Object.fromEntries((await d.send('Performance.getMetrics')).metrics.map(x => [x.name,x.value]));
 await d.send('HeapProfiler.collectGarbage');
 return {cpuMs:m.TaskDuration*1000,scriptMs:m.ScriptDuration*1000,layoutMs:m.LayoutDuration*1000,styleMs:m.RecalcStyleDuration*1000,heapMiB:(await d.send('Runtime.getHeapUsage')).usedSize/1048576};
}
async function state(p) { return p.evaluate(() => ({
 sections:document.querySelectorAll('.mrn-section-groups .mrn-section-link').length,
 acfFields:acf.getFields().length,
 editors:tinymce.editors.map(e => ({id:e.id,initialized:!!e.initialized})),
 nativeControls:document.querySelectorAll('#post input,#post select,#post textarea').length,
 acfValues:[...document.querySelectorAll('#post [name^="acf["]')].filter(e=>!e.closest('.acf-clone')).map(e=>[e.name,e.type==='checkbox'||e.type==='radio'?e.checked:e.value]),
 nodes:document.getElementsByTagName('*').length,
 cls:window.cpuQA.cls,longTasks:window.cpuQA.tasks
 })); }
(async () => {
 const browser=await chromium.launch();
 try {
  // Balanced ABBA order, both themes, same routed requests/cache policy.
  for (const phase of ['before','candidate','candidate','before']) for (const [name,id,count] of pages) for(const theme of ['light','dark']) {
   const context=await browser.newContext({viewport:{width:1487,height:1000}});
   await context.addCookies(JSON.parse(fs.readFileSync(output+'/auth.json')));
   const p=await context.newPage(),errors=[];
   p.on('pageerror',e=>errors.push(e.message));
   await p.route('**/section-navigator.js?*',route=>route.fulfill({status:200,contentType:'application/javascript',body:fs.readFileSync(phase==='before'?output+'/section-navigator-before.js':source)}));
   await p.route('**/post.php?post='+id+'&action=edit',async route=>{
    const response=await route.fetch();
    await route.fulfill({response,body:(await response.text()).replace(/data-mrn-appearance="[^"]+"/,'data-mrn-appearance="'+theme+'"')});
   });
   await p.addInitScript(()=>{
    window.cpuQA={cls:0,tasks:[]};
    new PerformanceObserver(l=>l.getEntries().forEach(e=>{if(!e.hadRecentInput)cpuQA.cls+=e.value})).observe({type:'layout-shift',buffered:true});
    new PerformanceObserver(l=>l.getEntries().forEach(e=>cpuQA.tasks.push(e.duration))).observe({type:'longtask',buffered:true});
   });
   const d=await context.newCDPSession(p);await d.send('Performance.enable');
   const start=Date.now();await p.goto('https://admin-editor-pilot.mrndev.io/wp-admin/post.php?post='+id+'&action=edit',{waitUntil:'domcontentloaded',timeout:120000});await settled(p);
   const metrics=await measure(d),raw=await state(p);if(raw.sections!==count)throw Error('Section count mismatch');
   const {acfValues,...form}=raw;const row={phase,name,theme,wallMs:Date.now()-start,...metrics,...form,formHash:digest(JSON.stringify(acfValues)),errors};
   if(name==='stress32'&&phase==='candidate'){
    const before=await measure(d), times=[];
    for(let i=0;i<8;i++){
     const t=Date.now();await p.locator('.mrn-section-groups .mrn-section-link').nth(i%2).click();
     await p.evaluate(()=>new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r))));times.push(Date.now()-t);
     await p.locator('.mrn-mode-settings').click();await p.locator('.mrn-mode-content').click();
    }
    const after=await measure(d);row.interactions={cycles:8,wallMs:times,cpuMs:after.cpuMs-before.cpuMs,heapBeforeMiB:before.heapMiB,heapAfterMiB:after.heapMiB};
   }
   results.push(row);fs.writeFileSync(output+'/qualified.json',JSON.stringify({browser:browser.version(),records:results},null,2));console.log(JSON.stringify({phase,name,theme,cpuMs:row.cpuMs,heapMiB:row.heapMiB,cls:row.cls,errors}));await context.close();
  }
 } finally {await browser.close()}
})().catch(e=>{console.error(e.stack);process.exitCode=1});
