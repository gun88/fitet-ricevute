const assert=require('node:assert/strict');
const {chromium}=require(process.env.FR_PLAYWRIGHT_MODULE||'../app/node_modules/playwright');
(async()=>{
 const origin='http://127.0.0.1:8095',api=origin+'/wp-json/fitet-ricevute/v1/';let checks=0;
 const check=(ok,label)=>{assert.ok(ok,label);checks++;console.log('PASS '+label);};
 const browser=await chromium.launch({headless:true,executablePath:process.env.FR_BROWSER_PATH});
 const c=await browser.newContext({viewport:{width:1600,height:1000}});
 let saved,token;const errors=[];
 try{
  const login=await c.request.post(api+'auth/login',{headers:{Origin:origin,'X-FR-Request':'1'},data:{username:'fr-tester',password:'FR-test-only-2026!'}});
  const session=await login.json();saved=session.grid_preferences;token=session.csrf_token;check(login.ok(),'login');
  const headers={Origin:origin,'X-FR-Request':'1','X-FR-CSRF':token};
  const put=async(data)=>c.request.put(api+'profilo/tabelle',{headers,data});
  check((await put({auto_height:false,density:'standard'})).ok(),'account preferences saved');
  check((await put({auto_height:false,density:'invalid'})).status()===400,'invalid density rejected');
  check((await c.request.put(api+'profilo/tabelle',{headers:{Origin:origin,'X-FR-Request':'1'},data:{auto_height:true,density:'compact'}})).status()===403,'CSRF protection');
  const entries=(await(await c.request.get(api+'iscrizioni')).json()).items;assert.ok(entries.length);
  const row=entries[0],p=await c.newPage();p.on('pageerror',e=>errors.push(e.message));
  const go=async(path,title)=>{await p.goto(origin+'/fitet-ricevute/#/'+path);await p.getByRole('heading',{name:title,exact:true}).waitFor();};
  await go('tornei','Tornei');await p.locator('#tournaments-grid .ag-row').first().waitFor();
  check(await p.getByLabel('Mostra solo tornei con iscrizioni').isVisible(),'tournament checkbox retained');
  check(await p.locator('.ag-floating-filter').count()===0,'floating filter row removed');
  const header=p.locator('.ag-header-cell[col-id="tournament_name"]');
  check(await header.locator('.ag-header-cell-filter-button').isVisible(),'filter icon visible without hover');
  check(await p.locator('.ag-header-cell[col-id="actions"] .ag-header-cell-filter-button:not(.ag-hidden)').count()===0,'actions have no filter');
  await header.locator('.ag-header-cell-filter-button').click();await p.locator('.ag-filter-body').waitFor();
  const input=p.locator('.ag-filter-body input').first();await input.fill('zzzz-does-not-exist');await p.waitForFunction(()=>document.querySelectorAll('#tournaments-grid .ag-row').length===0);
  check(true,'header filter actually filters rows');await p.keyboard.press('Escape');
  await p.getByRole('button',{name:'Azzera filtri e ordine',exact:true}).click();await p.locator('#tournaments-grid .ag-row').first().waitFor();
  await header.locator('.ag-header-cell-label').click();check(await header.getAttribute('aria-sort')!=='none','sorting remains available');
  await go('gare','Gare');await p.locator('#races-grid .ag-row').first().waitFor();check(await p.getByText('Mostra solo gare con iscrizioni').count()===0,'race checkbox removed');
  await go('tornei/'+row.tournament_key,row.tournament_name);check(await p.getByRole('link',{name:'Torna all’elenco'}).count()===0,'tournament back link removed');
  await go('gare/'+row.race_id,row.race_name);check(await p.getByRole('link',{name:'Torna all’elenco'}).count()===0,'race back link removed');
  await go('bozza-rimborso','Bozza rimborso');check(await p.getByRole('link',{name:'Aggiungi iscrizioni',exact:true}).count()===0,'draft redundant link removed');
  await go('utente','Profilo utente');
  await p.getByRole('switch',{name:'Altezza automatica'}).check();await p.getByText('Preferenze salvate.',{exact:true}).waitFor();
  await p.getByLabel('Spaziatura tabelle').selectOption('compact');await p.waitForFunction(()=>!document.querySelector('.profile-preferences').disabled);
  const me=await(await c.request.get(api+'auth/me')).json();check(me.grid_preferences.auto_height&&me.grid_preferences.density==='compact','profile choices persisted on server');
  const other=await browser.newContext();
  try{
   const anonymous=await other.request.put(api+'profilo/tabelle',{headers:{Origin:origin,'X-FR-Request':'1'},data:{auto_height:false,density:'standard'}});
   check(anonymous.status()===401,'anonymous preferences update rejected');
   const relogin=await other.request.post(api+'auth/login',{headers:{Origin:origin,'X-FR-Request':'1'},data:{username:'fr-tester',password:'FR-test-only-2026!'}});
   const restored=await relogin.json();check(restored.grid_preferences.auto_height&&restored.grid_preferences.density==='compact','new browser login restores account preferences');
  }finally{await other.close();}
  require('node:fs').mkdirSync(require('node:path').resolve(__dirname,'screenshots'),{recursive:true});
  await p.screenshot({path:require('node:path').resolve(__dirname,'screenshots/table-profile-desktop.png'),fullPage:true});

  await go('gare','Gare');await p.locator('#races-grid.ag-layout-auto-height, #races-grid .ag-layout-auto-height').first().waitFor();
  const compactHeight=await p.locator('#races-grid .ag-row').first().evaluate(e=>e.getBoundingClientRect().height);
  check(await p.locator('#races-grid').evaluate(e=>e.classList.contains('auto-height')&&e.getBoundingClientRect().height<420),'automatic height overrides fixed CSS');
  await p.reload();await p.locator('#races-grid.auto-height').waitFor();check(true,'automatic height survives reload');
  await go('giocatori','Giocatori');await p.locator('#players-grid.auto-height').waitFor();check(true,'preferences apply to player directory');
  await go('utente','Profilo utente');await p.getByRole('switch',{name:'Altezza automatica'}).uncheck();await p.getByText('Preferenze salvate.',{exact:true}).waitFor();
  await p.getByLabel('Spaziatura tabelle').selectOption('standard');await p.waitForFunction(()=>!document.querySelector('.profile-preferences').disabled);
  await go('gare','Gare');await p.locator('#races-grid .ag-row').first().waitFor();
  const standardHeight=await p.locator('#races-grid .ag-row').first().evaluate(e=>e.getBoundingClientRect().height);
  check(standardHeight>compactHeight,'compact spacing reduces row height');
  check(await p.locator('#races-grid').evaluate(e=>!e.classList.contains('auto-height')&&e.getBoundingClientRect().height>=420),'fixed layout restored');
  await p.setViewportSize({width:390,height:844});await p.locator('.mobile-card').first().waitFor();check(await p.locator('ag-grid-angular').count()===0,'mobile still uses cards without AG Grid');
  check(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'mobile has no page overflow');check(errors.length===0,'no browser errors: '+errors.join('; '));
  console.log(checks+' table/profile checks passed.');
 }finally{
  if(saved&&token)await c.request.put(api+'profilo/tabelle',{headers:{Origin:origin,'X-FR-Request':'1','X-FR-CSRF':token},data:saved});
  await c.close();await browser.close();
 }
})().catch(e=>{console.error(e);process.exit(1)});
