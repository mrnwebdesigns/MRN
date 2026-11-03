'use strict';
// Exact private review host; read-only current-candidate timing, not baseline qualification.
const fs=require('node:fs'),assert=require('node:assert/strict');
const req=require('node:module').createRequire('/Users/khofmeyer/Development/MRN-qa-engine/package.json');
const {chromium}=req('@playwright/test');
const base='https://admin-editor-pilot.mrndev.io',out=process.env.MRN_PILOT_OUTPUT||'outputs/admin-review-migration';
const empty=JSON.parse(fs.readFileSync(out+'/empty-fixture.json'));
assert(Number.isInteger(empty.id)&&empty.status==='draft');
(async()=>{
 const browser=await chromium.launch(),records=[],errors=[];
 try {
  for(const [id,count] of [[empty.id,0],[2523,19],[2524,32]]) {
   for(const [sample,mode] of ['light','light','dark','dark','light','light','dark'].entries()) {
    const context=await browser.newContext({viewport:{width:1487,height:1100},colorScheme:mode,httpCredentials:JSON.parse(fs.readFileSync(process.env.MRN_PILOT_HTTP_AUTH))});
    await context.addCookies(JSON.parse(fs.readFileSync(process.env.MRN_PILOT_AUTH)));
    await context.route('**/*',async route=>{
     const url=new URL(route.request().url());
     if(url.origin!==base)return route.abort();
     if(route.request().isNavigationRequest()&&url.pathname==='/wp-admin/post.php'){
      const response=await route.fetch();const html=await response.text();
      assert(/data-mrn-appearance="(?:light|dark|system)"/.test(html));
      return route.fulfill({response,body:html.replace(/data-mrn-appearance="(?:light|dark|system)"/,'data-mrn-appearance="'+mode+'"')});
     }
     return route.continue();
    });
    const page=await context.newPage(),cdp=await context.newCDPSession(page);
    page.on('pageerror',e=>errors.push(e.message));
    await page.addInitScript(()=>{window.mrnMeasuredTasks=[];new PerformanceObserver(list=>window.mrnMeasuredTasks.push(...list.getEntries().map(e=>e.duration))).observe({type:'longtask',buffered:true});});
    await cdp.send('Performance.enable');const start=performance.now();
    const response=await page.goto(base+'/wp-admin/post.php?post='+id+'&action=edit',{waitUntil:'load',timeout:120000});assert.equal(response.status(),200);
    await page.waitForFunction(n=>document.querySelectorAll('.mrn-section-link').length===n&&document.body.classList.contains('mrn-editor-page-ready')&&window.tinymce&&tinymce.editors.every(e=>e.initialized),count,{timeout:60000});
    const wall=performance.now()-start;
    const metrics=()=>cdp.send('Performance.getMetrics').then(r=>Object.fromEntries(r.metrics.map(m=>[m.name,m.value])));
    const m=await metrics();
    const state=await page.evaluate(()=>({tinyMCE:tinymce.editors.length,sections:document.querySelectorAll('.mrn-section-link').length,long_tasks:window.mrnMeasuredTasks.length,long_task_ms:window.mrnMeasuredTasks.reduce((a,b)=>a+b,0),max_long_task_ms:Math.max(0,...window.mrnMeasuredTasks),dom_nodes:document.querySelectorAll('*').length}));
    await cdp.send('HeapProfiler.collectGarbage');const heap=(await cdp.send('Runtime.getHeapUsage')).usedSize/1024/1024;
    const idleStart=await metrics();await page.waitForTimeout(2000);const idleEnd=await metrics();
    const search=page.locator('#mrn-section-search'),t=performance.now();await search.fill('review-no-match-unique');
    await page.waitForFunction(()=>document.querySelector('.mrn-outline-empty')?.textContent.includes('No matching')||document.body.classList.contains('mrn-workspace-empty'));const searchWall=performance.now()-t;
    records.push({id,count,mode,sample,warmup:sample===0,wall_ms:wall,cpu_ms:m.TaskDuration*1000,script_ms:m.ScriptDuration*1000,style_ms:m.RecalcStyleDuration*1000,layout_ms:m.LayoutDuration*1000,retained_heap_mb:heap,idle_cpu_ms:(idleEnd.TaskDuration-idleStart.TaskDuration)*1000,search_wall_ms:searchWall,...state});
    console.log(count,mode,sample,'CPU',Math.round(m.TaskDuration*1000),'wall',Math.round(wall));
    await context.close();
   }
  }
  assert.deepEqual(errors,[]);
 }finally{fs.writeFileSync(out+'/review-performance.json',JSON.stringify({at:new Date().toISOString(),protocol:'Fresh serial Chromium contexts. One warm-up then balanced three Light/three Dark samples per 0/19/32-section fixture. All native TinyMCE initialized. Response-only theme override; no preferences/content changed. Request interception disables browser cache and buffers HTML, so wall time includes instrumentation and is not server TTFB. No prior-candidate baseline; not a release regression verdict.',records,errors},null,2));await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});
