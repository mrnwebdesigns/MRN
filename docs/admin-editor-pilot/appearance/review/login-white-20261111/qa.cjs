const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict'),crypto=require('node:crypto');
const req=require('node:module').createRequire('/Users/khofmeyer/Development/MRN-qa-engine/package.json');
const {chromium}=req('@playwright/test'),Axe=req('@axe-core/playwright').default;
const source='/Users/khofmeyer/Development/MRN/.worktrees/admin-editor-pilot-20261029/mu-plugins/mrn-admin-ui-css';
const base=process.env.MRN_LOGIN_BASE||'https://admin-editor-pilot.mrndev.io',phase=process.env.MRN_LOGIN_QA_PHASE||'candidate';
assert(['candidate','installed'].includes(phase));assert(['https://admin-editor-pilot.mrndev.io','https://admin-editor-pilot.localhost'].includes(base));const label=(base.endsWith('.localhost')?'local-':'')+phase;
(async()=>{const browser=await chromium.launch();const rows=[];try{
for(const [preference,scheme] of [['light','dark'],['dark','light'],['system','light'],['system','dark']]){
const c=await browser.newContext({ignoreHTTPSErrors:true,viewport:{width:1440,height:1000},colorScheme:scheme});
await c.addCookies([{name:'mrn_login_appearance',value:preference,domain:new URL(base).hostname,path:'/',httpOnly:true,secure:true,sameSite:'Lax'}]);
const p=await c.newPage(),errors=[],requests=[];p.on('pageerror',e=>errors.push(e.message));p.on('response',r=>{if(r.url().includes('mrn-login-logo-white.svg'))requests.push(r.status())});
if(phase==='candidate')await p.route(base+'/wp-content/mu-plugins/mrn-admin-ui-css/**',async route=>{const name=new URL(route.request().url()).pathname.split('/').pop();if(['login.css','mrn-login-logo-white.svg'].includes(name))return route.fulfill({path:path.join(source,name),contentType:name.endsWith('.svg')?'image/svg+xml':'text/css'});return route.continue()});
for(const screen of ['login','recovery']){
await p.goto(base+'/site-login/'+(screen==='recovery'?'?action=lostpassword':''),{waitUntil:'load',timeout:60000});
const logo=p.locator('#login h1 a');const info=await logo.evaluate(e=>({image:getComputedStyle(e).backgroundImage,href:e.href,aria:e.getAttribute('aria-label'),text:e.textContent,rect:{width:e.offsetWidth,height:e.offsetHeight},inputs:[...document.querySelectorAll('form input')].map(i=>[i.name,i.type]),scripts:[...document.scripts].map(s=>s.src).filter(s=>s.includes('/mrn-admin-ui-css/'))}));
const dark=preference==='dark'||preference==='system'&&scheme==='dark';assert.equal(info.image.includes('mrn-login-logo-white.svg'),dark);assert.equal(info.href,'https://mrnwebdesigns.com/');assert.deepEqual(info.rect,{width:90,height:90});assert.equal(info.scripts.length,0);assert(info.inputs.some(i=>i[0]==='user_login'||i[0]==='log'));
const a=await new Axe({page:p}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();assert.equal(a.violations.length,0,JSON.stringify(a.violations.map(x=>x.id)));
await p.screenshot({path:path.join(__dirname,`${label}-${preference}-${scheme}-${screen}.png`),fullPage:true});
if(screen==='login'){for(const width of [768,320]){await p.setViewportSize({width,height:850});assert.equal(await p.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);await p.screenshot({path:path.join(__dirname,`${label}-${preference}-${scheme}-${width}.png`),fullPage:true})}await p.setViewportSize({width:1440,height:1000});}
rows.push({preference,scheme,screen,dark,...info,violations:a.violations.map(x=>x.id),errors});
}
if(preference==='system'){await p.emulateMedia({colorScheme:scheme==='dark'?'light':'dark'});assert.equal((await p.locator('#login h1 a').evaluate(e=>getComputedStyle(e).backgroundImage)).includes('mrn-login-logo-white.svg'),scheme==='light')}
if(preference==='light'){const candidate=crypto.createHash('sha256').update(await p.locator('#login h1 a').screenshot()).digest('hex');await p.route(base+'/wp-content/mu-plugins/mrn-admin-ui-css/login.css*',r=>r.fulfill({path:path.join(__dirname,'before.css'),contentType:'text/css'}));await p.reload({waitUntil:'load'});assert.equal(crypto.createHash('sha256').update(await p.locator('#login h1 a').screenshot()).digest('hex'),candidate)}
assert(errors.length===0);assert(requests.every(s=>s===200));await c.close();
}fs.writeFileSync(path.join(__dirname,label+'-results.json'),JSON.stringify({phase,rows,checks:['Light logo screenshot unchanged from previous CSS','MRN link and 90px geometry retained','Explicit Light wins over dark OS; explicit Dark wins over light OS','System switches logo live with OS','Login and recovery: axe zero violations','320px and 768px no overflow','No added component JavaScript']},null,2));console.log(JSON.stringify({phase,screens:rows.length,violations:0}));}finally{await browser.close()}})().catch(e=>{console.error(e.message);process.exitCode=1});
