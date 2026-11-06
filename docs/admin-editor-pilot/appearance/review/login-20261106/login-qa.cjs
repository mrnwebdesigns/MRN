'use strict';
const fs=require('node:fs'),assert=require('node:assert/strict'),path=require('node:path');
const req=require('node:module').createRequire('/Users/khofmeyer/Development/MRN-qa-engine/package.json');
const {chromium,expect}=req('@playwright/test'),Axe=req('@axe-core/playwright').default;
const base=process.env.MRN_LOGIN_BASE||'https://admin-editor-pilot.localhost';
assert(['https://admin-editor-pilot.localhost','https://admin-editor-pilot.mrndev.io'].includes(base));
const name=new URL(base).hostname.endsWith('.localhost')?'local':'review',out=__dirname;const results={host:base,screens:[],errors:[]};
(async()=>{const b=await chromium.launch();try{
for(const [preference,scheme] of [['light','light'],['dark','dark'],['system','light'],['system','dark']]){
 const c=await b.newContext({ignoreHTTPSErrors:true,viewport:{width:1440,height:1000},colorScheme:scheme});
 await c.addCookies([{name:'mrn_login_appearance',value:preference,domain:new URL(base).hostname,path:'/',httpOnly:true,secure:true,sameSite:'Lax'}]);
 const p=await c.newPage();p.on('pageerror',e=>results.errors.push(e.message));await p.addInitScript(()=>{window.loginCLS=0;new PerformanceObserver(l=>l.getEntries().forEach(e=>{if(!e.hadRecentInput)window.loginCLS+=e.value})).observe({type:'layout-shift',buffered:true})});
 for(const [screen,suffix] of [['login',''],['recovery','?action=lostpassword']]){
  const at=Date.now(),r=await p.goto(base+'/site-login/'+suffix,{waitUntil:'load',timeout:60000});assert.equal(r.status(),200);await expect(p.locator('html')).toHaveAttribute('data-mrn-appearance',preference);await expect(p.locator('body')).toHaveClass(/mrn-login/);
  const m=await p.evaluate(()=>({scheme:getComputedStyle(document.documentElement).colorScheme,background:getComputedStyle(document.body).backgroundColor,cls:window.loginCLS,scriptAssets:[...document.scripts].map(x=>x.src).filter(x=>x.includes('/mrn-admin-ui-css/')),formAction:document.querySelector('#login form').action,logo:document.querySelector('#login h1 a').getAttribute('href'),logoImage:getComputedStyle(document.querySelector('#login h1 a')).backgroundImage,overflow:document.documentElement.scrollWidth>innerWidth}));assert.equal(m.scheme,scheme);assert.equal(m.scriptAssets.length,0);assert.equal(m.overflow,false);assert(new URL(m.formAction).origin===base);
  const axe=await new Axe({page:p}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();const violations=axe.violations.map(v=>({id:v.id,impact:v.impact,nodes:v.nodes.map(n=>({target:n.target,summary:n.failureSummary}))}));
  results.screens.push({preference,scheme,screen,loadMs:Date.now()-at,...m,violations});fs.writeFileSync(path.join(out,name+'-results.json'),JSON.stringify(results,null,2));
  if(preference!=='system')await p.screenshot({path:path.join(out,name+'-'+preference+'-'+screen+'.png'),fullPage:true});
  if(screen==='login'){
   await p.locator('#user_login').focus();await p.keyboard.press('Tab');await expect(p.locator('#user_pass')).toBeFocused();await p.locator('#user_pass').fill('Visual-only password');await p.getByRole('button',{name:'Show password',exact:true}).click();await expect(p.locator('#user_pass')).toHaveAttribute('type','text');await p.getByRole('button',{name:'Hide password',exact:true}).click();await expect(p.locator('#user_pass')).toHaveAttribute('type','password');await p.locator('#user_pass').fill('');
   await p.locator('#rememberme').check();assert.equal(await p.locator('#rememberme').isChecked(),true);
   await p.setViewportSize({width:320,height:740});assert.equal(await p.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);if(preference!=='system')await p.screenshot({path:path.join(out,name+'-'+preference+'-mobile.png'),fullPage:true});await p.setViewportSize({width:1440,height:1000});
  }
 }
 if(preference==='system'){await p.emulateMedia({colorScheme:scheme==='dark'?'light':'dark'});assert.equal(await p.locator('html').evaluate(e=>getComputedStyle(e).colorScheme),scheme==='dark'?'light':'dark')}
 await c.close();
}
const nojs=await b.newContext({ignoreHTTPSErrors:true,javaScriptEnabled:false,colorScheme:'dark'}),np=await nojs.newPage();await np.goto(base+'/site-login/',{waitUntil:'load',timeout:60000});await expect(np.locator('html')).toHaveAttribute('data-mrn-appearance','system');await expect(np.locator('#user_login')).toBeVisible();results.noJavaScript=true;await nojs.close();
const c=await b.newContext({ignoreHTTPSErrors:true,colorScheme:'dark'}),p=await c.newPage();await p.goto(base+'/site-login/',{waitUntil:'load',timeout:60000});await p.locator('#user_login').fill('mrn-login-qa-missing-20261106');await p.locator('#user_pass').fill('Deliberately-invalid-QA-password');await Promise.all([p.waitForNavigation({waitUntil:'load',timeout:60000}),p.locator('#wp-submit').click()]);await expect(p.locator('#login_error,.notice-error').first()).toBeVisible();const a=await new Axe({page:p}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();results.errorViolations=a.violations.map(v=>({id:v.id,nodes:v.nodes.map(n=>n.target)}));await p.screenshot({path:path.join(out,name+'-dark-error.png'),fullPage:true});await c.close();
assert.equal(results.errors.length,0);fs.writeFileSync(path.join(out,name+'-results.json'),JSON.stringify(results,null,2));console.log(JSON.stringify({host:base,screens:results.screens.map(x=>({mode:x.preference+'/'+x.scheme,screen:x.screen,violations:x.violations.map(v=>v.id),cls:x.cls})),errorViolations:results.errorViolations}));
if(results.screens.some(x=>x.violations.length)||results.errorViolations.length)process.exitCode=2;
}finally{await b.close()}})().catch(e=>{console.error(e.stack);process.exitCode=1});
