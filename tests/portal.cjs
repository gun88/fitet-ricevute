const assert=require('node:assert/strict');
const {mkdirSync,readFileSync}=require('node:fs');const {resolve}=require('node:path');const {unzipSync}=require('../app/node_modules/fflate');
const {chromium,request}=require(process.env.FR_PLAYWRIGHT_MODULE||'../app/node_modules/playwright');
(async()=>{
 const origin='http://127.0.0.1:8095',api=origin+'/wp-json/fitet-ricevute/v1/';let checks=0;
 const check=(ok,text)=>{assert.ok(ok,text);checks++;console.log('PASS',text)};
 const client=await request.newContext({extraHTTPHeaders:{Origin:origin,'X-FR-Request':'1'}});
 check((await client.get(api+'carrello')).status()===401,'cart requires login');check((await client.get(api+'emissioni')).status()===401,'emission registry requires login');
 const login=await client.post(api+'auth/login',{data:{username:'fr-tester',password:'FR-test-only-2026!'}});const session=await login.json();const headers={Origin:origin,'X-FR-Request':'1','X-FR-CSRF':session.csrf_token};
 check((await client.post(api+'carrello',{data:{ids:[1]}})).status()===403,'cart mutation requires CSRF');
 const entries=(await(await client.get(api+'iscrizioni')).json()).items;const current=entries.filter(r=>r.tournament_date.startsWith('2026'));
 const browser=await chromium.launch({headless:true,...(process.env.FR_BROWSER_PATH?{executablePath:process.env.FR_BROWSER_PATH}:{})});
 let context=await browser.newContext({viewport:{width:1600,height:1100},acceptDownloads:true});let page=await context.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
 const row=(grid,index=0)=>page.locator(grid+' .ag-grid-scrolling-container .ag-row[row-index="'+index+'"]');
 const action=(grid,label)=>page.locator(grid+' .ag-row[row-index="0"] [col-id="actions"]').getByRole('link',{name:label,exact:true});
 const cartAction=(grid,label)=>page.locator(grid+' .ag-row[row-index="0"] [col-id="cart"]').getByRole('button',{name:label,exact:true});
 const nav=name=>page.locator('.app-nav').getByRole('link',name==='Carrello'?{name:/^Carrello/}:{name,exact:true});
 async function horizontal(grid,x){await page.locator(grid+' .ag-grid-viewport').evaluate((el,x)=>{el.scrollLeft=x;el.dispatchEvent(new Event('scroll'));},x);}
 async function logIn(){await page.goto(origin+'/fitet-ricevute/');await page.getByLabel('Username',{exact:true}).fill('fr-tester');await page.getByLabel('Password',{exact:true}).fill('FR-test-only-2026!');await page.getByRole('button',{name:'Accedi',exact:true}).click();await row('#tournaments-grid').waitFor();}
 try{
  await logIn();check(await page.locator('.app-nav a').count()===5,'five main pages in menu');
  check(await page.getByRole('checkbox',{name:'Mostra solo tornei con iscrizioni',exact:true}).isChecked(),'tournament participation filter defaults checked');
  check(await page.locator('#tournaments-grid .ag-grid-scrolling-container .ag-row').count()===1,'tournaments grouped once rather than once per race');
  await page.getByRole('checkbox',{name:'Mostra solo tornei con iscrizioni',exact:true}).uncheck();await row('#tournaments-grid',1).waitFor();check(true,'unchecked filter includes catalog-only tournament');
  await page.reload();await row('#tournaments-grid',1).waitFor();check(!await page.getByRole('checkbox',{name:'Mostra solo tornei con iscrizioni',exact:true}).isChecked(),'tournament checkbox preference survives refresh');
  await page.getByLabel('Stagione agonistica',{exact:true}).selectOption('all');await row('#tournaments-grid',2).waitFor();check(true,'global season includes older tournament');
  await page.getByLabel('Stagione agonistica',{exact:true}).selectOption('latest');await page.getByRole('checkbox',{name:'Mostra solo tornei con iscrizioni',exact:true}).check();
  await horizontal('#tournaments-grid',1700);await action('#tournaments-grid','Dettaglio').click();
  await page.getByRole('heading',{name:'Iscrizioni',exact:true}).waitFor();check(await page.locator('fr-entity-grid').count()===2,'tournament detail contains shared race and entry tables');
  await nav('Tornei').click();await row('#tournaments-grid').waitFor();await horizontal('#tournaments-grid',1700);await action('#tournaments-grid','Gare').click();
  await row('#races-grid').waitFor();check(page.url().includes('torneo='),'tournament action sets race-page query filter');
  check(await page.getByRole('checkbox',{name:'Mostra solo gare con iscrizioni',exact:true}).isChecked(),'race participation filter defaults checked');
  check(await page.locator('#races-grid .ag-grid-scrolling-container .ag-row').count()===2,'race page scoped to selected tournament');
  const quota=row('#races-grid').locator('[col-id="quota"]');await quota.dblclick();await quota.locator('input').fill('17,25');await quota.locator('input').press('Enter');await page.getByRole('status').filter({hasText:'Prezzo salvato.'}).waitFor();check(true,'shared race grid edits price through API');

  await row('#races-grid').locator('[col-id="race_name"]').getByRole('link').click();await page.getByRole('heading',{name:'Iscrizioni',exact:true}).waitFor();check(await page.locator('.detail-summary').innerText().then(t=>t.includes('Quota')),'race detail includes parent tournament and quota');
  await nav('Gare').click();await row('#races-grid').waitFor();const firstRace=await row('#races-grid').getAttribute('row-id');await horizontal('#races-grid',2000);await action('#races-grid','Iscrizioni').click();await row('#entries-grid').waitFor();
  check(page.url().includes('gara='+firstRace),'race action scopes inscription page');
  check(await page.locator('#entries-grid .ag-grid-scrolling-container .ag-row').count()===current.filter(r=>String(r.race_id)===firstRace).length,'only inscriptions of selected race shown');
  await nav('Iscrizioni').click();await row('#entries-grid').waitFor();await horizontal('#entries-grid',0);await row('#entries-grid').locator('fr-athlete-links a').first().click();
  await page.getByRole('heading',{name:'Rimborsi',exact:true}).waitFor();check(await page.locator('fr-entity-grid').count()===2,'player detail has shared inscriptions and refund tables');
  await nav('Iscrizioni').click();await row('#entries-grid').waitFor();await horizontal('#entries-grid',1900);await cartAction('#entries-grid','Aggiungi al carrello').click();
  await page.getByRole('status').filter({hasText:'Iscrizioni aggiunte al carrello.'}).waitFor();check((await page.locator('.cart-pill').innerText())==='1','adding inscription updates cart badge');
  await nav('Carrello').click();await row('#cart-grid').waitFor();await horizontal('#cart-grid',1900);await cartAction('#cart-grid','Rimuovi').click();await page.getByRole('status').filter({hasText:'Iscrizioni rimosse dal carrello.'}).waitFor();check((await page.locator('.cart-pill').innerText())==='0','cart items can be removed');
  await nav('Iscrizioni').click();await row('#entries-grid').waitFor();await horizontal('#entries-grid',0);
  await row('#entries-grid').locator('.ag-selection-checkbox input').check();await row('#entries-grid',1).locator('.ag-selection-checkbox input').check();await page.getByRole('button',{name:'Aggiungi selezionate (2)',exact:true}).click();await page.getByRole('status').filter({hasText:'Iscrizioni aggiunte al carrello.'}).waitFor();
  await page.getByRole('button',{name:'Esci',exact:true}).click();await page.getByLabel('Username',{exact:true}).waitFor();await context.close();
  context=await browser.newContext({viewport:{width:1600,height:1100},acceptDownloads:true});page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));await logIn();await page.waitForFunction(()=>document.querySelector('.cart-pill')?.textContent.trim()==='2');check(true,'cart restored after logout and a new browser session');
  await nav('Carrello').click();await row('#cart-grid').waitFor();await page.getByLabel('Società emittente',{exact:true}).fill('Società portale test');await page.getByLabel('Formato PDF',{exact:true}).selectOption('both');
  const download=page.waitForEvent('download');await page.getByRole('button',{name:'Emetti rimborsi',exact:true}).click();const file=await download;
  const output=resolve(__dirname,'.runtime/portal-output');mkdirSync(output,{recursive:true});await file.saveAs(resolve(output,'rimborso.zip'));const pdfs=unzipSync(readFileSync(resolve(output,'rimborso.zip')));
  check(Object.keys(pdfs).length>=2&&Object.values(pdfs).every(bytes=>Buffer.from(bytes).subarray(0,5).toString()==='%PDF-'),'issuance downloads cumulative and athlete PDF files');
  await page.waitForFunction(()=>document.querySelector('.cart-pill')?.textContent.trim()==='0');check(true,'successful issue removes issued inscriptions from cart');
  const history=(await(await client.get(api+'emissioni')).json()).items;check(history.length===1&&history[0].items.length===2&&history[0].created_at.endsWith('Z'),'server registry stores emission details and timestamp');
  await nav('Rimborsi Emessi').click();await row('#emissions-grid').waitFor();check(true,'emission shown in registry table');await row('#emissions-grid').getByRole('link',{name:'Rimborso #'+history[0].id,exact:true}).click();await page.getByRole('heading',{name:'Rimborso #'+history[0].id,exact:true}).waitFor();check(await page.locator('fr-entity-grid .ag-grid-scrolling-container .ag-row').count()===2,'refund detail shows original inscription snapshots');
  const raceId=history[0].items[0].race_id;await client.patch(api+'prezzi/'+raceId,{headers,data:{manual_cents:9999}});
  const immutable=await(await client.get(api+'emissioni/'+history[0].id)).json();check(immutable.total_cents===history[0].total_cents,'issued total unaffected by new race prices');
  const again=page.waitForEvent('download');await page.getByRole('button',{name:'Scarica nuovamente le ricevute',exact:true}).click();await again;check(true,'issued receipt can be downloaded again');
  await page.locator('fr-entity-grid fr-athlete-links a').first().click();await page.getByRole('heading',{name:'Rimborsi',exact:true}).waitFor();check(await page.locator('fr-entity-grid').nth(1).locator('.ag-grid-scrolling-container .ag-row').count()===1,'player history includes issued refund');
  await page.setViewportSize({width:390,height:844});check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'new navigation and detail tables fit mobile width');
  await page.locator('fr-entity-grid').first().locator('.ag-grid-viewport').evaluate(el=>{el.scrollLeft=el.scrollWidth;});
  await page.locator('fr-entity-grid').first().locator('.ag-row [col-id="cart"] button').first().waitFor({state:'visible'});check(true,'mobile native grid scrolling reaches final action column');
  check(await page.locator('.ag-cell').first().evaluate(el=>getComputedStyle(el).userSelect)==='text','table text selectable');
  mkdirSync(resolve(__dirname,'screenshots'),{recursive:true});await page.screenshot({path:resolve(__dirname,'screenshots/portal-player-mobile.png'),fullPage:true});await page.setViewportSize({width:1600,height:1100});await nav('Rimborsi Emessi').click();await row('#emissions-grid').waitFor();await page.screenshot({path:resolve(__dirname,'screenshots/portal-emissions.png'),fullPage:true});
  check(errors.length===0,'no uncaught JavaScript errors: '+errors.join('; '));console.log(checks+' portal UI/API checks passed.');
 } finally {await context.close();await browser.close();await client.dispose();}
})().catch(error=>{console.error(error);process.exit(1)});
