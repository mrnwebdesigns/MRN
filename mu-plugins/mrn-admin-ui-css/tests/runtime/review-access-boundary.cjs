'use strict';
// Verify the approved cookie-presence WAF exception does not grant site access.
const fs = require('node:fs');
const assert = require('node:assert/strict');
const req = require('node:module').createRequire('/Users/khofmeyer/Development/MRN-qa-engine/package.json');
const {chromium} = req('@playwright/test');
const base = 'https://admin-editor-pilot.mrndev.io';
const out = process.env.MRN_PILOT_OUTPUT || 'outputs/admin-review-migration/save-qualified';
(async () => {
 const browser = await chromium.launch();
 try {
  const context = await browser.newContext();
  const page = await context.newPage();
  const userAgent = await page.evaluate(() => navigator.userAgent);
  const checks = [];
  for (const path of ['/wp-admin/', '/wp-includes/css/dashicons.min.css']) {
   const response = await context.request.get(base + path, {headers:{'User-Agent':userAgent},maxRedirects:0});
   assert.equal(response.status(),401);
   assert.match(response.headers()['www-authenticate'],/^Basic /i);
   checks.push({path,status:401,result:'Basic Auth required'});
  }
  const rejected = await context.request.post(base + '/wp-admin/post.php', {
   headers:{'User-Agent':userAgent,'Cookie':'wordpress_logged_in_review_boundary=invalid'},
   data:'action=editpost',maxRedirects:0,
  });
  assert.equal(rejected.status(),401);
  assert.match(rejected.headers()['www-authenticate'],/^Basic /i);
  checks.push({path:'/wp-admin/post.php',status:401,result:'Cookie presence alone does not bypass Basic Auth'});
  const protectedContext = await browser.newContext({httpCredentials:JSON.parse(fs.readFileSync(process.env.MRN_PILOT_HTTP_AUTH))});
  const loginPage = await protectedContext.newPage();
  await loginPage.goto(base + '/wp-admin/',{waitUntil:'domcontentloaded',timeout:90000});
  assert.equal(new URL(loginPage.url()).origin,base);
  assert.equal(await loginPage.locator('#loginform').count(),1);
  assert.equal(await loginPage.locator('#wpadminbar').count(),0);
  checks.push({path:'/wp-admin/',result:'Basic Auth alone still requires native WordPress sign-in'});
  fs.writeFileSync(out + '/access-boundary.json',JSON.stringify({at:new Date().toISOString(),checks},null,2)+'\n');
  console.log('Review access boundary passed');
 } finally { await browser.close(); }
})().catch(error=>{console.error(error);process.exit(1);});
