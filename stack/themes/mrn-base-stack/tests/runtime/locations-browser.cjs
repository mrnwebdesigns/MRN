/* Native Locations editor persistence and WCAG 2.1 AA checks. Private fixture input arrives on stdin. */
'use strict';
const fs=require('node:fs');
const assert=require('node:assert/strict');
const path=require('node:path');
const {createRequire}=require('node:module');
(async()=>{
 const input=JSON.parse(fs.readFileSync(0,'utf8'));
 assert.equal(new URL(input.url).hostname,'127.0.0.1');
 assert.equal(path.isAbsolute(input.output_directory),true);
 const output=name=>path.join(input.output_directory,name);
 const req=createRequire(input.engine_root+'/package.json');
 const {chromium,expect}=req('@playwright/test');
 const AxeBuilder=req('@axe-core/playwright').default;
 const browser=await chromium.launch({headless:true});
 const context=await browser.newContext({reducedMotion:'reduce'});
 await context.route('**/*',route=>new URL(route.request().url()).origin===input.url?route.continue():route.abort());
 const page=await context.newPage();
 const errors=[];page.on('pageerror',error=>errors.push(error.message));
 try {
  await page.goto(input.login_url,{waitUntil:'networkidle'});
  await page.locator('#user_login').fill(input.username);
  await page.locator('#user_pass').fill(input.password);
  await Promise.all([page.waitForURL(u=>u.pathname!==new URL(input.login_url).pathname),page.locator('#wp-submit').click()]);
  const editor=input.url+'/wp-admin/post.php?post='+input.location_id+'&action=edit';
  for(let i=0;i<4;i++){
   await page.goto(editor,{waitUntil:'networkidle'});
   if(new URL(page.url()).searchParams.get('page')==='wpforms-getting-started')continue;
   if(new URL(page.url()).searchParams.get('page')==='seopress-setup'){
    await page.getByRole('link',{name:'Skip for now',exact:true}).click();continue;
   }
   break;
  }
  const group=page.locator('#acf-group_mrn_location');
  await expect(group).toBeVisible();
  const field=name=>group.locator('.acf-field[data-key="field_mrn_location_'+name+'"]');
  const tab=name=>group.locator('.acf-tab-wrap .acf-tab-button[data-key="field_mrn_location_'+name+'_tab"]');
  const checkbox=name=>field('use_business_'+name).locator('input[type=checkbox]');
  const scan=async name=>{
   const result=await new AxeBuilder({page}).include('#acf-group_mrn_location').withTags(['wcag2a','wcag2aa','wcag21a','wcag21aa']).analyze();
   fs.writeFileSync(output('axe-'+name+'.json'),JSON.stringify(result.violations.map(v=>({id:v.id,impact:v.impact,nodes:v.nodes.map(n=>n.target)}))));
   assert.equal(result.violations.length,0,'Location fields AA scan: '+name);
  };
  await scan('hours');
  await checkbox('hours').check();
  await expect(field('hours_monday_open')).toBeHidden();
  await tab('contact').click();
  await expect(field('phone').locator('input')).toHaveValue('(919) 555-4444');
  await field('fax').locator('input').fill('9195557777');
  await scan('contact');
  await Promise.all([page.waitForNavigation({waitUntil:'networkidle'}),page.locator('#save-post').click()]);
  await page.goto(editor,{waitUntil:'networkidle'});
  await tab('contact').click();
  await expect(field('fax').locator('input')).toHaveValue('(919) 555-7777');
  for(const name of ['phone','text_phone','fax']){
   await checkbox(name).check();await expect(field(name)).toBeHidden();
  }
  await tab('social').click();
  await scan('social');
  await checkbox('social_links').check();
  await expect(field('social_links')).toBeHidden();
  await tab('details').click();
  await scan('address');
  await page.screenshot({path:output('locations-editor.png'),fullPage:true});
  const save=async()=>{
   const button=page.locator('#save-post');
   await Promise.all([page.waitForNavigation({waitUntil:'networkidle'}),button.click()]);
   await page.goto(editor,{waitUntil:'networkidle'});
  };
  await save();
  await expect(checkbox('hours')).toBeChecked();
  assert.equal(await checkbox('hours').isChecked(),true);
  await tab('contact').click();
  for(const name of ['phone','text_phone','fax']){
   await expect(checkbox(name)).toBeChecked();await checkbox(name).uncheck();
  }
  await expect(field('phone').locator('input')).toHaveValue('(919) 555-4444');
  await expect(field('fax').locator('input')).toHaveValue('(919) 555-7777');
  await tab('hours').click();await checkbox('hours').uncheck();
  await expect(field('hours_monday_open')).toBeVisible();
  await tab('social').click();
  await expect(checkbox('social_links')).toBeChecked();await checkbox('social_links').uncheck();
  await expect(field('social_links').locator('.acf-row:not(.acf-clone) input[type=url]')).toHaveValue('https://example.test/local');
  await save();
  await tab('contact').click();
  await expect(checkbox('phone')).not.toBeChecked();
  await expect(field('fax').locator('input')).toHaveValue('(919) 555-7777');
  assert.equal(errors.length,0,'Native editor must have no JavaScript errors');
  fs.writeFileSync(output('browser.json'),JSON.stringify({status:'pass',save_reload:true,retained_values:true,checks:['hours','phone','text','fax','social','address','image'],axe:'WCAG 2.1 A/AA: all four tabs'}));
 }catch(error){
  await page.screenshot({path:output('failure.png'),fullPage:true});
  fs.writeFileSync(output('failure.txt'),error.stack+'\nURL: '+page.url());
  throw error;
 }finally{await browser.close();}
})().catch(error=>{console.error(error.message);process.exitCode=1;});
