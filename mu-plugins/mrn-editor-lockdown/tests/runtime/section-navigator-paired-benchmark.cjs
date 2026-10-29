'use strict';
const fs=require('node:fs'),assert=require('node:assert/strict'),{createRequire}=require('node:module');
const req=createRequire('/Users/khofmeyer/Development/MRN-qa-engine/package.json'),{chromium}=req('@playwright/test');
const site='/Users/khofmeyer/Development/MRN-sites/admin-editor-pilot',config=site+'/public/wp-config.php',original=fs.readFileSync(config,'utf8');
const base='https://admin-editor-pilot.localhost',rows='.acf-field[data-name="page_content_rows"] > .acf-input > .acf-flexible-content > .values > .layout:not(.acf-clone)';
(async()=>{const b=await chromium.launch({headless:true}),c=await b.newContext({ignoreHTTPSErrors:true,viewport:{width:1440,height:1000}}),results={method:'Six adjacent pairs per page; order alternates between baseline and pilot; same warm context; native save guard loaded in both; external requests blocked',samples:[]};
try{await c.addCookies(JSON.parse(fs.readFileSync(site+'/logs/administrator-auth.json')));await c.route('**/*',r=>new URL(r.request().url()).origin===base?r.continue():r.abort());
for(const [kind,id] of [['real',2523],['stress',2524]])for(let pair=0;pair<6;pair++)for(const enabled of pair%2?[true,false]:[false,true]){
 fs.writeFileSync(config,original.replace(/define\('MRN_EDITOR_EXPERIENCE_ENABLED', (true|false)\);/,`define('MRN_EDITOR_EXPERIENCE_ENABLED', ${enabled});`));
 await new Promise(resolve=>setTimeout(resolve,2500));const p=await c.newPage();await p.addInitScript(()=>{window.pilotLongTasks=[];new PerformanceObserver(l=>l.getEntries().forEach(e=>pilotLongTasks.push(e.duration))).observe({type:'longtask',buffered:true})});
 await p.goto(`${base}/wp-admin/post.php?post=${id}&action=edit`,{waitUntil:'load',timeout:120000});
 if(!!(await p.locator('#mrn-section-navigator-js').count())!==enabled){await p.waitForTimeout(2500);await p.reload({waitUntil:'load',timeout:120000})}assert.equal(!!(await p.locator('#mrn-section-navigator-js').count()),enabled,'Flag must match the requested sample: '+await p.title());
 await p.waitForFunction(s=>window.acf&&document.querySelectorAll(s).length>0,rows);if(enabled)await p.locator('.mrn-section-link').first().waitFor({state:'attached'});await p.evaluate(()=>new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r))));
 const sample=await p.evaluate(()=>{const n=performance.getEntriesByType('navigation')[0];return {response_end_ms:n.responseEnd,dcl_ms:n.domContentLoadedEventEnd,load_ms:n.loadEventEnd,client_dcl_ms:n.domContentLoadedEventEnd-n.responseEnd,ready_ms:performance.now(),client_ready_ms:performance.now()-n.responseEnd,heap_bytes:performance.memory?.usedJSHeapSize,long_task_ms:pilotLongTasks.reduce((a,b)=>a+b,0)}});results.samples.push({kind,pair,enabled,...sample});await p.close();
}
fs.writeFileSync('outputs/admin-editor-pilot/paired-metrics.json',JSON.stringify(results,null,2));console.log('Paired samples recorded:',results.samples.length);
}finally{fs.writeFileSync(config,original);await b.close()}
})().catch(e=>{console.error(e);process.exit(1)});
