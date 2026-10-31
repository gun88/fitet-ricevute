const assert=require('node:assert/strict');
const {chromium}=require(process.env.FR_PLAYWRIGHT_MODULE||'../app/node_modules/playwright');
const origin='http://127.0.0.1:8095',api=origin+'/wp-json/fitet-ricevute/v1/';
(async()=>{
 const browser=await chromium.launch({headless:true,...(process.env.FR_BROWSER_PATH?{executablePath:process.env.FR_BROWSER_PATH}:{})});
 const context=await browser.newContext({viewport:{width:390,height:844},hasTouch:true,isMobile:true});const page=await context.newPage();let raceId,csrf;let checks=0;
 const headers={Origin:origin,'X-FR-Request':'1'};
 const ok=(value,message)=>{assert.ok(value,message);checks++;console.log('PASS',message)};
 try{
  const session=await(await context.request.post(api+'auth/login',{headers,data:{username:'fr-tester',password:'FR-test-only-2026!'}})).json();csrf=session.csrf_token;
  const races=await(await context.request.get(api+'gare',{headers})).json();raceId=Number(races.items[0].id);
  await page.goto(origin+'/fitet-ricevute/');
  await page.locator('#prices-grid .ag-row[row-id="'+raceId+'"]').waitFor();
  await page.locator('#prices-grid .ag-grid-viewport').evaluate(el=>{el.scrollLeft=1350;el.dispatchEvent(new Event('scroll'))});
  const cell=page.locator('#prices-grid .ag-row[row-id="'+raceId+'"] [col-id="quota"]');await cell.scrollIntoViewIfNeeded();await cell.tap();
  await cell.locator('input').fill('16,50');await cell.locator('input').press('Enter');
  await page.getByRole('status').filter({hasText:'Prezzo salvato.'}).waitFor();ok(true,'single touch opens price editing and commits through API');
  const entries=await(await context.request.get(api+'iscrizioni',{headers})).json();ok(entries.items.filter(row=>Number(row.race_id)===raceId).every(row=>Number(row.price_cents)===1650),'touch price edit updates all joined registration amounts');
  await page.getByRole('link',{name:'Iscrizioni',exact:true}).tap();
  await page.locator('#entries-grid .ag-grid-viewport').evaluate(el=>{el.scrollLeft=1000;el.dispatchEvent(new Event('scroll'))});
  const checkbox=page.locator('#entries-grid .ag-row[row-index="0"] .ag-selection-checkbox input');await checkbox.tap();
  await page.getByRole('button',{name:'Emetti rimborso (1)',exact:true}).waitFor();ok(true,'selection checkbox remains touch-accessible while scrolling the mobile table');
  ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'touch layout remains inside the mobile viewport');
  await context.request.patch(api+'prezzi/'+raceId,{headers:{...headers,'X-FR-CSRF':csrf},data:{manual_cents:null}});raceId=null;
  await page.getByRole('button',{name:'Esci',exact:true}).tap();await page.getByRole('heading',{name:'Accedi al portale',exact:true}).waitFor();
  ok(await page.evaluate(()=>Object.keys(localStorage).every(key=>!key.includes(':undefined:'))),'logout creates no anonymous preference namespace');
  console.log(checks+' touch checks passed.');
 }finally{
  if(raceId&&csrf)await context.request.patch(api+'prezzi/'+raceId,{headers:{...headers,'X-FR-CSRF':csrf},data:{manual_cents:null}});
  await context.close();await browser.close();
 }
})().catch(error=>{console.error(error);process.exit(1)});
