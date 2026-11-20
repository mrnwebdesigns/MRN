/* Isolated browser behavior proof; WordPress/editor/full-page QA is separate. */
const assert=require('node:assert/strict');const fs=require('node:fs');
const {chromium}=require(process.env.MRN_PLAYWRIGHT_MODULE || 'playwright');
const root=require('node:path').resolve(__dirname,'../..');
const css=fs.readFileSync(root+'/style.css','utf8'),script=fs.readFileSync(root+'/js/front-end-faq.js','utf8');
const checks=[];function ok(value,label){assert(value,label);checks.push(label);}
const attrs='class="has-faq-frame has-faq-scroll-active" data-mrn-faq-scroll="0.45" data-mrn-faq-mobile-all="true" style="--mrn-faq-scroll-heading-aside-width:361px;--mrn-faq-scroll-column-gap:127px;--mrn-faq-scroll-content-width:1200px;--mrn-faq-scroll-inner-gutter:30px;--mrn-faq-scroll-heading-sticky-top:180px;--mrn-faq-scroll-item-space-before:64px;--mrn-faq-scroll-item-space-after:48px;--mrn-faq-frame-question-size:30px;--mrn-faq-frame-question-size-mobile:22px;--mrn-faq-frame-answer-size:18px;--mrn-faq-scroll-heading-size:42px;--mrn-faq-scroll-heading-size-tablet:32px"';
const item=(n)=>`<details class="mrn-faq__item mrn-ui__item" open><summary class="mrn-faq__question mrn-ui__heading">Service ${n}</summary><div class="mrn-faq__answer mrn-ui__text"><p>Complete description ${n}</p><ul><li>Feature ${n}a</li><li>Feature ${n}b</li></ul><a href="#after">Read service ${n}</a></div></details>`;
const html=`<!doctype html><html lang="en"><head><title>Owned FAQ interaction test</title><style>${css}</style><style>body{margin:0;color:#211010;background:white;font:18px/1.5 sans-serif}main{padding-top:500px}#after{height:1400px}.mrn-layout-section{padding:0}*{box-sizing:border-box}</style></head><body><main><div ${attrs}><div class="mrn-layout-section mrn-layout-section--reusable-faq"><div class="mrn-layout-container"><section class="mrn-reusable-block--faq"><div class="mrn-reusable-block__inner"><div class="mrn-faq mrn-faq--layout-split"><div class="mrn-ui__head"><h2 class="mrn-ui__heading">What we offer</h2></div><div class="mrn-faq__items">${[1,2,3,4,5].map(item).join('')}</div></div></div></section></div></div></div><div id="after"></div></main></body></html>`;
(async()=>{const browser=await chromium.launch({headless:true});try{
 const page=await browser.newPage({viewport:{width:1440,height:900}});await page.setContent(html);const items=page.locator('details');
 ok(await page.locator('details[open]').count()===5,'No-script native markup exposes all five answers');
 await page.addScriptTag({content:script});await page.waitForFunction(()=>document.querySelectorAll('details[open]').length===1);
 ok(await items.nth(0).getAttribute('open')!==null,'Desktop starts with the first service');
 const g=await page.locator('.mrn-faq').evaluate(e=>{const c=getComputedStyle(e);return {columns:c.gridTemplateColumns,gap:c.columnGap};});ok(g.columns.startsWith('361px ')&&g.gap==='127px','Desktop uses editable aside width and column gap');
 await page.evaluate(()=>{const e=document.querySelectorAll('details')[2];window.scrollTo(0,e.getBoundingClientRect().top+window.scrollY-innerHeight*.45+2);});await page.waitForTimeout(120);
 ok(await items.nth(2).getAttribute('open')!==null&&await page.locator('details[open]').count()===1,'Scrolling to 45 percent activates the correct service');
 await items.nth(2).locator('a').focus();await page.evaluate(()=>window.scrollTo(0,0));await page.waitForTimeout(100);
 ok(await items.nth(2).getAttribute('open')!==null,'Scrolling never closes an answer around focused content');
 await items.nth(1).locator('summary').focus();await page.keyboard.press('Enter');await page.waitForTimeout(80);
 ok(await items.nth(1).getAttribute('open')!==null&&await page.locator('details[open]').count()===1,'Enter opens a chosen answer and closes its unfocused sibling');
 await page.keyboard.press('Space');await page.waitForTimeout(80);ok(await items.nth(1).getAttribute('open')===null,'Space preserves native disclosure semantics');
 await page.evaluate(()=>document.activeElement.blur());await page.setViewportSize({width:767,height:900});await page.waitForTimeout(100);
 ok(await page.locator('details[open]').count()===5,'All five answers open on entry to mobile');
 ok(await page.locator('.mrn-faq__answer > ul').first().evaluate(e=>getComputedStyle(e).gridTemplateColumns.split(' ').length)===1,'Mobile features use one column');
 ok(await page.locator('.mrn-faq > .mrn-ui__head').evaluate(e=>getComputedStyle(e).position)==='static','Mobile heading no longer sticks');
 await items.nth(3).locator('summary').click();await page.evaluate(()=>window.dispatchEvent(new Event('scroll')));await page.waitForTimeout(80);
 ok(await items.nth(3).getAttribute('open')===null,'Mobile manual collapse survives subsequent scroll events');
 await page.evaluate(()=>document.activeElement.blur());await page.setViewportSize({width:1440,height:900});await page.waitForTimeout(100);
 ok(await page.locator('details[open]').count()===1,'Resizing back to desktop restores scroll-active selection');
 await page.emulateMedia({reducedMotion:'reduce'});await page.waitForTimeout(100);
 ok(await page.locator('details[open]').count()===5,'Reduced motion expands all answers without scroll-driven changes');
 ok(await page.locator('.mrn-faq > .mrn-ui__head').evaluate(e=>getComputedStyle(e).position)==='static','Reduced motion removes sticky heading movement');
 await page.evaluate(()=>window.scrollTo(0,0));await page.waitForTimeout(80);ok(await page.locator('details[open]').count()===5,'Reduced-motion scrolling keeps every answer visible');
 await page.setViewportSize({width:390,height:844});await page.waitForTimeout(80);ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'No horizontal overflow at 390px');
 console.log(JSON.stringify({result:'passed',count:checks.length,checks},null,2));
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
