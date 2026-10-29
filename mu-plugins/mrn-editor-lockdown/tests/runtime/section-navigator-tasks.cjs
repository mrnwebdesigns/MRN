'use strict';
const fs=require('node:fs'),{createRequire}=require('node:module'),req=createRequire('/Users/khofmeyer/Development/MRN-qa-engine/package.json'),{chromium}=req('@playwright/test');
const mode=process.argv[2],root='.acf-field[data-name="page_content_rows"]',rows=root+' > .acf-input > .acf-flexible-content > .values > .layout:not(.acf-clone)';
(async()=>{const b=await chromium.launch({headless:true}),c=await b.newContext({ignoreHTTPSErrors:true,viewport:{width:1440,height:1000}});await c.addCookies(JSON.parse(fs.readFileSync('/Users/khofmeyer/Development/MRN-sites/admin-editor-pilot/logs/administrator-auth.json')));await c.route('**/*',r=>new URL(r.request().url()).hostname==='admin-editor-pilot.localhost'?r.continue():r.abort());const results={mode,method:'Scripted task; known last section; 800px wheel increments and animation-frame settling. No human reading time or user study.',pages:{}};
for(const [kind,id] of [['real',2523],['stress',2524]]){const p=await c.newPage();await p.goto('https://admin-editor-pilot.localhost/wp-admin/post.php?post='+id+'&action=edit',{waitUntil:'load',timeout:120000});await p.waitForFunction(()=>window.acf&&document.querySelector('.acf-field[data-name="page_content_rows"]'));
results.pages[kind]={};
for(const state of ['collapsed','expanded']){
 await p.evaluate(({root,state})=>{const f=acf.getField(jQuery(root).first());f.$layouts().each(function(){state==='expanded'?f.openLayout(jQuery(this)):jQuery(this).addClass('-collapsed')});window.scrollTo(0,0)}, {root,state});await p.evaluate(()=>new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r))));
 const geometry=await p.locator(rows).last().evaluate(el=>({targetY:el.getBoundingClientRect().top+scrollY,documentHeight:document.documentElement.scrollHeight,title:el.querySelector('.acf-field[data-name="internal_name"] input[type="text"]')?.value||el.querySelector('.acf-fc-layout-title').textContent.trim()}));
 let wheels=0;const start=performance.now();if(mode==='before'){
  while(await p.locator(rows).last().evaluate(el=>{const r=el.querySelector('.acf-fc-layout-handle').getBoundingClientRect();return r.top>innerHeight-100||r.top<170})){const top=await p.locator(rows).last().evaluate(el=>el.getBoundingClientRect().top);await p.mouse.wheel(0,top<170?-400:800);await p.evaluate(()=>new Promise(r=>requestAnimationFrame(r)));if(++wheels>150)throw Error('Task scroll limit exceeded')}
  await p.locator(rows).last().locator('.acf-fc-layout-handle').first().click();
 }else{
  await p.locator('#wp-admin-bar-mrn-page-sections > a').click();await p.locator('#mrn-section-search').fill(geometry.title);await p.locator('.mrn-section-link:visible').last().click();await p.locator('.mrn-outline-close').click();
 }
 results.pages[kind][state]={...geometry,scriptedTaskMs:performance.now()-start,manualWheelActions:wheels};
 if(kind==='real'&&state==='collapsed'){await p.evaluate(root=>{const box=document.querySelector(root).closest('.postbox');box.scrollIntoView({block:'start'})},root);await p.screenshot({path:`outputs/admin-editor-pilot/${mode}-sections.png`,timeout:10000});}
}
await p.close();}
fs.writeFileSync(`outputs/admin-editor-pilot/${mode}-tasks.json`,JSON.stringify(results,null,2));console.log(results);await b.close()})().catch(e=>{console.error(e);process.exit(1)});
