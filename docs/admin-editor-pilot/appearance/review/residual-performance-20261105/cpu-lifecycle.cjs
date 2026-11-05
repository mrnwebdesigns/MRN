// Run only against the owned review fixtures after a fresh verified remote backup for any save job. Supply auth.json privately; never commit it.
'use strict';
const fs = require('node:fs');
const crypto = require('node:crypto');
const req = require('node:module').createRequire('/Users/khofmeyer/Development/MRN-qa-engine/package.json');
const { chromium } = req('@playwright/test');

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
  // Current-state observations: one serial Light/Dark pair per fixture; identical request/cache policy. No baseline comparison.
  for (const phase of ['pass1']) for (const [name,id,count] of pages) for(const theme of (phase==='pass1'?['light','dark']:['dark','light'])) {
   const context=await browser.newContext({viewport:{width:1487,height:1000}});
   await context.addCookies(JSON.parse(fs.readFileSync(output+'/auth.json')));
   const p=await context.newPage(),errors=[];
   p.on('pageerror',e=>errors.push(e.message));
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
   const startup=await measure(d); await p.evaluate(()=>acf.getFields({type:'wysiwyg'}).forEach(f=>{if(f.$control().hasClass('delay'))f.onMousedown({preventDefault(){}})})); await p.waitForFunction(()=>tinymce.editors.every(e=>e.initialized)); await p.waitForTimeout(500); const metrics=await measure(d),raw=await state(p);if(raw.sections!==count)throw Error('Section count mismatch');
   const {acfValues,...form}=raw;const row={phase,name,theme,wallMs:Date.now()-start,startup,...metrics,...form,formHash:digest(JSON.stringify(acfValues)),errors};
   const idleStart=await measure(d);await p.waitForTimeout(1500);const idleEnd=await measure(d);row.idleCpuMs=idleEnd.cpuMs-idleStart.cpuMs;
   const sectionHandles=await p.$$('.mrn-section-groups .mrn-section-link'),settingsHandle=await p.$('.mrn-mode-settings'),contentHandle=await p.$('.mrn-mode-content');
   const interactionStart=await measure(d),times=[];
   for(let i=0;i<6;i++){const at=Date.now();if(count)await sectionHandles[i%count].click();await settingsHandle.click();await contentHandle.click();await p.evaluate(()=>new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r))));times.push(Date.now()-at);}
   const interactionEnd=await measure(d);row.interactions={cycles:6,cpuMs:interactionEnd.cpuMs-interactionStart.cpuMs,wallMs:times};row.formHashAfter=digest(JSON.stringify((await state(p)).acfValues));if(row.formHashAfter!==row.formHash)throw Error('Navigation changed native field values');
   await d.send('HeapProfiler.collectGarbage');row.retainedHeapMiB=(await d.send('Runtime.getHeapUsage')).usedSize/1048576;
   row.securityScripts=await p.evaluate(()=>performance.getEntriesByType('resource').filter(x=>x.name.includes('/cdn-cgi/')).map(x=>new URL(x.name).pathname));
   results.push(row);fs.writeFileSync(output+'/cpu-lifecycle.json',JSON.stringify({browser:browser.version(),records:results},null,2));console.log(JSON.stringify({phase,name,theme,startup:row.startup.cpuMs,cpuMs:row.cpuMs,editors:row.editors.length,heapMiB:row.heapMiB,errors}));await context.close();
  }
 } finally {await browser.close()}
})().catch(e=>{console.error(e.stack);process.exitCode=1});