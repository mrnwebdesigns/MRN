'use strict';
// Read-only, serial A/B client-asset probe on the authorized review fixtures.
const fs=require('node:fs'),assert=require('node:assert/strict'),cp=require('node:child_process');
const req=require('node:module').createRequire('/Users/khofmeyer/Development/MRN-qa-engine/package.json');
const {chromium}=req('@playwright/test');
const base='https://admin-editor-pilot.mrndev.io',out=process.env.MRN_PILOT_OUTPUT;
assert(out&&process.env.MRN_PILOT_AUTH);
const names=['mrn-admin-ui-css/appearance.js','mrn-admin-ui-css/mrn-admin-shell.css','mrn-editor-lockdown/assets/section-navigator.js'];
const assets=Object.fromEntries(names.map(name=>[base+'/wp-content/mu-plugins/'+name,{
 before:cp.execFileSync('git',['show','00103b41:mu-plugins/'+name]),
 after:fs.readFileSync('mu-plugins/'+name)
}]));
(async()=>{
 const browser=await chromium.launch(),records=[],errors=[];
 try{
  for(const [id,count] of [[2523,19],[2524,32]]){
   // Balanced ABBA order per theme, with an excluded warm-up for each fixture.
   const plan=[['after','light',true],...['light','dark'].flatMap(mode=>['before','after','after','before'].map(candidate=>[candidate,mode,false]))];
   for(const [candidate,mode,warmup] of plan){
    const c=await browser.newContext({viewport:{width:1487,height:1100},colorScheme:mode});
    await c.addCookies(JSON.parse(fs.readFileSync(process.env.MRN_PILOT_AUTH)));
    await c.route('**/*',async route=>{
     const url=new URL(route.request().url());if(url.origin!==base)return route.abort();
     const source=assets[url.origin+url.pathname];
     if(source)return route.fulfill({status:200,contentType:url.pathname.endsWith('.css')?'text/css':'text/javascript',body:source[candidate]});
     if(route.request().isNavigationRequest()&&url.pathname==='/wp-admin/post.php'){
      const response=await route.fetch(),html=await response.text();assert(/data-mrn-appearance="(?:light|dark|system)"/.test(html));
      return route.fulfill({response,body:html.replace(/data-mrn-appearance="(?:light|dark|system)"/,'data-mrn-appearance="'+mode+'"')});
     }
     return route.continue();
    });
    const p=await c.newPage(),cdp=await c.newCDPSession(p);p.on('pageerror',e=>errors.push(e.message));
    await cdp.send('Performance.enable');const start=performance.now();
    const response=await p.goto(base+'/wp-admin/post.php?post='+id+'&action=edit',{waitUntil:'load',timeout:120000});assert.equal(response.status(),200);
    await p.waitForFunction(n=>document.querySelectorAll('.mrn-section-link').length===n&&window.tinymce&&tinymce.editors.every(e=>e.initialized),count,{timeout:60000});
    const metrics=async()=>Object.fromEntries((await cdp.send('Performance.getMetrics')).metrics.map(m=>[m.name,m.value]));
    const wall=performance.now()-start,m=await metrics();await cdp.send('HeapProfiler.collectGarbage');const heap=(await cdp.send('Runtime.getHeapUsage')).usedSize/1024/1024;
    const idleStart=await metrics();await p.waitForTimeout(1000);const idleEnd=await metrics();
    const searchStart=performance.now();await p.locator('#mrn-section-search').fill('round3-no-match-unique');await p.waitForFunction(()=>document.querySelector('.mrn-outline-empty')?.textContent.includes('No matching'));
    records.push({id,count,candidate,mode,warmup,wall_ms:wall,cpu_ms:m.TaskDuration*1000,script_ms:m.ScriptDuration*1000,style_ms:m.RecalcStyleDuration*1000,layout_ms:m.LayoutDuration*1000,retained_heap_mb:heap,idle_cpu_ms:(idleEnd.TaskDuration-idleStart.TaskDuration)*1000,search_ms:performance.now()-searchStart});
    console.log(count,candidate,mode,warmup?'warmup':'measured',Math.round(m.TaskDuration*1000)+'ms CPU');await c.close();
   }
  }
  assert.deepEqual(errors,[]);
 }finally{
  fs.mkdirSync(out,{recursive:true});fs.writeFileSync(out+'/performance-results.json',JSON.stringify({protocol:'Serial isolated Chromium contexts; one excluded warm-up per fixture, then ABBA per Light/Dark mode. Same installed HTML/server, three changed client assets substituted from pre-round3 commit 00103b41 or current source. No content or preference writes. This isolates this round client-asset delta; not original pilot qualification or server save performance. Two samples per candidate/theme/fixture; limited precision.',records,errors},null,2));await browser.close();
 }
})().catch(e=>{console.error(e);process.exit(1);});
