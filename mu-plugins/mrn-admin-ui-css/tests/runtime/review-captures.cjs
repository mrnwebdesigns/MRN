'use strict';
// Read-only captures of the exact authorized review copy; no preference saves.
const fs=require('node:fs'),assert=require('node:assert/strict');
const req=require('node:module').createRequire('/Users/khofmeyer/Development/MRN-qa-engine/package.json');
const {chromium,expect:baseExpect}=req('@playwright/test'),Axe=req('@axe-core/playwright').default,expect=baseExpect.configure({timeout:30000});
const base='https://admin-editor-pilot.mrndev.io',out=process.env.MRN_PILOT_OUTPUT||'outputs/admin-review-migration';
(async()=>{const browser=await chromium.launch(),results={checks:[],axe:{},errors:[]};fs.mkdirSync(out+'/after',{recursive:true});
 try{for(const mode of ['light','dark']){
  const context=await browser.newContext({viewport:{width:1487,height:1100},httpCredentials:JSON.parse(fs.readFileSync(process.env.MRN_PILOT_HTTP_AUTH))});
  await context.addCookies(JSON.parse(fs.readFileSync(process.env.MRN_PILOT_AUTH)));
  await context.route('**/*',async route=>{const request=route.request(),url=new URL(request.url());if(url.origin!==base)return route.abort();if(request.isNavigationRequest()&&url.pathname.startsWith('/wp-admin/')){const response=await route.fetch(),html=await response.text();return route.fulfill({response,body:html.replace(/data-mrn-appearance="(?:light|dark|system)"/,'data-mrn-appearance="'+mode+'"')});}return route.continue();});
  const page=await context.newPage();page.on('pageerror',e=>results.errors.push(e.message));
  async function shot(name){await page.screenshot({path:out+'/after/'+mode+'-'+name+'.png',fullPage:true});}
  for(const [name,path]of [['dashboard','index.php'],['pages','edit.php?post_type=page'],['settings','options-general.php'],['mrn-settings','options-general.php?page=mrn-config-helper'],['editor','post.php?post=2523&action=edit']]){
   console.log(mode,name);const response=await page.goto(base+'/wp-admin/'+path,{waitUntil:'load',timeout:90000});assert.equal(response.status(),200);await expect(page.locator('html')).toHaveAttribute('data-mrn-appearance',mode);
   if(name==='editor'){await expect(page.locator('.mrn-section-link')).toHaveCount(19);await page.waitForFunction(()=>window.tinymce?.editors.length===4&&tinymce.editors.every(e=>e.initialized));}
   await shot(name);
   if(name==='dashboard'){await page.locator('#menu-pages>a').hover();await expect(page.locator('#menu-pages .wp-submenu')).toBeVisible();await shot('sidebar-expanded');await page.mouse.move(1000,200);}
   let axe=new Axe({page}).withTags(['wcag2a','wcag2aa','wcag21aa']);for(const selector of name==='editor'?['#mrn-workspace-header','#mrn-section-outline','#mrn-editor-tools']:name==='dashboard'?['#wpadminbar','#adminmenu']:['#wpbody-content>.wrap'])axe=axe.include(selector);
   results.axe[mode+'-'+name]=(await axe.analyze()).violations.map(v=>({id:v.id,impact:v.impact,nodes:v.nodes.map(n=>({target:n.target,summary:n.failureSummary}))}));
   if(name==='editor'){await page.locator('.mrn-mode-settings').click();await shot('editor-settings');await page.locator('.mrn-mode-content').click();await page.locator('.mrn-view-mode').click();await shot('editor-arrange');await page.locator('.mrn-view-mode').click();}
   if(['dashboard','editor','settings'].includes(name)){await page.setViewportSize({width:390,height:844});assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),name+' mobile overflow');await shot(name+'-mobile');await page.setViewportSize({width:1487,height:1100});}
   results.checks.push(mode+' '+name+' rendered and captured');
  }await context.close();
 }assert.deepEqual(results.errors,[]);
 }finally{fs.writeFileSync(out+'/capture-results.json',JSON.stringify(results,null,2));await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});
