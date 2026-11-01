const assert=require('node:assert/strict');
const {chromium}=require(process.env.FR_PLAYWRIGHT_MODULE||'../app/node_modules/playwright');
(async()=>{
 const browser=await chromium.launch({headless:true,...(process.env.FR_BROWSER_PATH?{executablePath:process.env.FR_BROWSER_PATH}:{})});
 const context=await browser.newContext({viewport:{width:320,height:740}}),page=await context.newPage();
 try{
  await page.goto('http://127.0.0.1:8095/fitet-ricevute/');await page.getByLabel('Username',{exact:true}).fill('fr-tester');await page.getByLabel('Password',{exact:true}).fill('FR-test-only-2026!');await page.getByRole('button',{name:'Accedi',exact:true}).click();await page.locator('#tournaments-grid .ag-row').first().waitFor();
  assert.equal(await page.locator('#tournaments-grid .ag-grid-pinned-right-cells .ag-cell').count(),0);console.log('PASS fresh 320px session leaves actions scrollable');
  await page.getByRole('searchbox',{name:'Cerca Tornei'}).fill('Torneo');
  await page.setViewportSize({width:1280,height:900});await page.locator('#tournaments-grid .ag-grid-pinned-right-cells [col-id="actions"]').first().waitFor();console.log('PASS desktop action pin restored after first mobile visit');
  await page.reload();await page.locator('#tournaments-grid .ag-grid-pinned-right-cells [col-id="actions"]').first().waitFor();assert.equal(await page.getByRole('searchbox',{name:'Cerca Tornei'}).inputValue(),'Torneo');console.log('PASS search and desktop pin preferences survive reload');
 }finally{await context.close();await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});
