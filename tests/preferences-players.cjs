const assert=require('node:assert/strict');
const {resolve}=require('node:path');
const {mkdirSync}=require('node:fs');
const {chromium,request}=require(process.env.FR_PLAYWRIGHT_MODULE || '../app/node_modules/playwright');
const origin='http://127.0.0.1:8095', api=origin+'/wp-json/fitet-ricevute/v1/';
const credentials={username:'fr-tester',password:'FR-test-only-2026!'};
let checks=0;
function ok(value,message){assert.ok(value,message);checks++;console.log('PASS',message)}
const gridRows=(page,id)=>page.locator(id+' .ag-grid-scrolling-container .ag-row');
async function count(page,id,n){await page.waitForFunction(([id,n])=>document.querySelectorAll(id+' .ag-grid-scrolling-container .ag-row').length===n,[id,n]);}
async function login(page){await page.getByLabel('Username',{exact:true}).fill(credentials.username);await page.getByLabel('Password',{exact:true}).fill(credentials.password);await page.getByRole('button',{name:'Accedi',exact:true}).click();await page.locator('#prices-grid').waitFor();}
async function preference(page,name){return page.evaluate(name=>{const key=Object.keys(localStorage).find(key=>key.endsWith(':'+name));return key?JSON.parse(localStorage.getItem(key)):null},name);}
async function horizontal(page,id,x){await page.locator(id+' .ag-grid-viewport').evaluate((el,x)=>{el.scrollLeft=x;el.dispatchEvent(new Event('scroll'))},x);}
async function filter(page,id,column,text){await page.locator(id+' .ag-floating-filter[col-id="'+column+'"] input').fill(text);await page.waitForFunction(([name,column,text])=>{const key=Object.keys(localStorage).find(k=>k.endsWith(':'+name));return key&&JSON.parse(localStorage.getItem(key)).grid?.filter?.filterModel?.[column]?.filter===text},[id==='#prices-grid'?'prices':id==='#refund-grid'?'refund-recap':id==='#athlete-grid'?'refund-athletes':id==='#entries-grid'?'entries':'player-history',column,text]);}
(async()=>{
 const client=await request.newContext({extraHTTPHeaders:{Origin:origin,'X-FR-Request':'1'},timeout:60000});
 await client.post(api+'auth/login',{data:credentials});
 const source=await (await client.get(api+'iscrizioni')).json(); const base=source.items[0];
 const shared={...base,athlete1_name:'Atleta storico',athlete1_card:'55501',athlete2_name:null,athlete2_card:null,price_cents:1500,paid_cents:null,reimbursed:0};
 const rows=[
  {...shared,id:8801,tournament_key:'past-one',tournament_date:'2025-06-30',tournament_name:'Torneo storico',race_name:'Singolo storico'},
  {...shared,id:8802,tournament_key:'past-two',tournament_date:'2025-07-01',tournament_name:'Torneo intermedio',race_name:'Singolo intermedio',reimbursed:1,paid_cents:1500},
  {...shared,id:8803,tournament_key:'latest',tournament_date:'2026-10-24',tournament_name:'Torneo recente',race_name:'Singolo recente'},
  {...shared,id:8804,tournament_key:'latest',tournament_date:'2026-10-24',tournament_name:'Torneo recente',race_name:'Doppio recente',athlete2_name:'Compagno prova',athlete2_card:'55502',race_kind:'double',reimbursed:1,price_cents:2001,paid_cents:2001,source_committee:'30',tournament_scope:'national'},
 ];
 const browser=await chromium.launch({headless:true,...(process.env.FR_BROWSER_PATH?{executablePath:process.env.FR_BROWSER_PATH}:{})});
 let context=await browser.newContext({viewport:{width:1600,height:1000}});let page=await context.newPage();const errors=[];page.on('pageerror',error=>errors.push(error.message));
 mkdirSync(resolve(__dirname,'screenshots'),{recursive:true});
 const mocks=async ctx=>{
  await ctx.route('**/wp-json/fitet-ricevute/v1/iscrizioni*',route=>route.fulfill({json:{items:rows,total:rows.length,page:1,per_page:50}}));
  await ctx.route('**/wp-json/fitet-ricevute/v1/rimborsi/anteprima',route=>route.fulfill({json:{items:rows.filter(row=>route.request().postDataJSON().ids.includes(row.id))}}));
 };
 try {
  await mocks(context);await page.goto(origin+'/fitet-ricevute/');await login(page);await count(page,'#prices-grid',2);
  ok((await gridRows(page,'#prices-grid').first().locator('[col-id="tournament_scope"]').innerText())==='Territoriale','price table exposes territorial/national scope');
  await filter(page,'#prices-grid','tournament_name','Vedano');
  await page.locator('#prices-grid .ag-header-cell[col-id="race_name"] .ag-header-cell-label').click();
  await page.getByLabel('Cerca prezzi',{exact:true}).fill('Open3');await count(page,'#prices-grid',1);
  let prefs=await preference(page,'prices');
  ok(prefs.search==='Open3'&&prefs.grid.filter.filterModel.tournament_name.filter==='Vedano'&&prefs.grid.sort.sortModel.some(item=>item.colId==='race_name'&&item.sort==='asc'),'browser stores quick search, column filter and sorting together');
  await page.getByRole('link',{name:'Iscrizioni',exact:true}).click();await count(page,'#entries-grid',4);
  await page.getByLabel('Cerca iscrizioni',{exact:true}).fill('Doppio');await count(page,'#entries-grid',1);
  await page.getByRole('link',{name:'Compagno prova',exact:true}).click();
  await page.getByRole('heading',{name:'Compagno prova',exact:true}).waitFor();await count(page,'#player-grid',1);
  ok(true,'second player in a double opens their own profile');
  await page.getByRole('link',{name:'Tutti i giocatori',exact:true}).click();await page.getByRole('link',{name:'Atleta storico',exact:true}).click();await count(page,'#player-grid',2);
  ok(await page.getByLabel('Annata agonistica',{exact:true}).inputValue()==='latest','new player view defaults to the latest available season');
  ok((await page.locator('.player-totals').innerText()).includes('Quote rimborsate')&&await gridRows(page,'#player-grid').count()===2,'latest-season recap includes tournaments and paid participation state');
  await page.getByLabel('Annata agonistica',{exact:true}).selectOption('all');await count(page,'#player-grid',4);
  await page.getByLabel('Cerca partecipazioni',{exact:true}).fill('Doppio');await count(page,'#player-grid',1);
  await page.reload();await page.getByRole('heading',{name:'Atleta storico',exact:true}).waitFor();await count(page,'#player-grid',1);
  ok(await page.getByLabel('Annata agonistica',{exact:true}).inputValue()==='all'&&await page.getByLabel('Cerca partecipazioni',{exact:true}).inputValue()==='Doppio','player season and search survive a page reload');
  await page.screenshot({path:resolve(__dirname,'screenshots/player-desktop.png'),fullPage:true});
  await page.getByRole('link',{name:'Prezzi tornei',exact:true}).click();await count(page,'#prices-grid',1);
  ok(await page.getByLabel('Cerca prezzi',{exact:true}).inputValue()==='Open3'&&await page.locator('#prices-grid .ag-floating-filter[col-id="tournament_name"] input').inputValue()==='Vedano','price filters restore after leaving and reopening the page');
  await page.getByRole('link',{name:'Iscrizioni',exact:true}).click();await count(page,'#entries-grid',1);
  ok(await page.getByLabel('Cerca iscrizioni',{exact:true}).inputValue()==='Doppio','entry table retains its own filters independently');
  await page.getByRole('button',{name:'Azzera filtri e ordine',exact:true}).click();await count(page,'#entries-grid',4);
  await page.locator('#entries-grid .ag-row[row-index="0"] .ag-selection-checkbox input').check();
  await page.getByRole('button',{name:'Emetti rimborso (1)',exact:true}).click();await page.locator('#refund-grid').waitFor();
  const chosen=await gridRows(page,'#refund-grid').first().locator('[col-id="race_name"]').innerText();
  await filter(page,'#refund-grid','race_name',chosen);
  await filter(page,'#athlete-grid','name','Atleta');
  await page.getByRole('link',{name:'Prezzi tornei',exact:true}).click();await page.getByRole('link',{name:'Emissione rimborso',exact:true}).click();await page.locator('#athlete-grid').waitFor();
  ok(await page.locator('#refund-grid .ag-floating-filter[col-id="race_name"] input').inputValue()===chosen&&await page.locator('#athlete-grid .ag-floating-filter[col-id="name"] input').inputValue()==='Atleta','both refund recap tables restore independent column filters');
  const storage=await context.storageState(); const presentation=storage.origins;
  await page.getByRole('button',{name:'Esci',exact:true}).click();await page.getByRole('heading',{name:'Accedi al portale',exact:true}).waitFor();
  ok((await preference(page,'prices')).search==='Open3','logout preserves table preferences');
  await context.close();context=await browser.newContext({viewport:{width:1600,height:1000},storageState:{cookies:[],origins:presentation}});await mocks(context);page=await context.newPage();page.on('pageerror',error=>errors.push(error.message));
  await page.goto(origin+'/fitet-ricevute/');await login(page);await count(page,'#prices-grid',1);
  ok(await page.getByLabel('Cerca prezzi',{exact:true}).inputValue()==='Open3','preferences survive a browser session restart and a new login');
  await page.goto(origin+'/fitet-ricevute/#/giocatori/card:55501');await count(page,'#player-grid',1);
  for (const width of [390,320]) {await page.setViewportSize({width,height:844});ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'player page stays within mobile viewport '+width+'px');}
  await page.screenshot({path:resolve(__dirname,'screenshots/player-mobile.png'),fullPage:true});
  await page.getByRole('button',{name:'Azzera filtri e ordine',exact:true}).click();await count(page,'#player-grid',2);
  ok(await page.getByLabel('Annata agonistica',{exact:true}).inputValue()==='latest','reset returns profile to the latest season');
  await page.getByRole('link',{name:'Giocatori',exact:true}).click();await page.getByLabel('Cerca giocatore',{exact:true}).fill('Compagno');await count(page,'#players-grid',1);
  await page.reload();await count(page,'#players-grid',1);
  ok(await page.getByLabel('Cerca giocatore',{exact:true}).inputValue()==='Compagno','players directory also saves its filters');
  ok(errors.length===0,'player navigation and persistent grids have no uncaught JS errors: '+errors.join(';'));
  await page.setViewportSize({width:1500,height:1000});
  await page.goto(origin+'/wp-login.php');await page.locator('#user_login').fill('wp-test-admin');await page.locator('#user_pass').fill('WP-test-only-2026!');await page.locator('#wp-submit').click();
  await page.goto(origin+'/wp-admin/admin.php?page=fitet-ricevute');await page.setViewportSize({width:320,height:844});
  ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'wp-admin configuration fits a 320px mobile viewport');
  const name='Società mobile di test';await page.locator('#fr-society_name').fill(name);await page.getByRole('button',{name:'Salva impostazioni',exact:true}).click();
  ok(await page.locator('#fr-society_name').inputValue()===name,'wp-admin configuration can be saved from mobile');
  await page.screenshot({path:resolve(__dirname,'screenshots/admin-config-mobile.png'),fullPage:true});
  await page.goto(origin+'/wp-admin/admin.php?page=fitet-ricevute-users');
  ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'wp-admin users and forms fit a 320px mobile viewport');
  const username='mobile-'+Date.now();await page.locator('#fr-username').fill(username);await page.locator('#fr-name').fill('Operatore mobile');await page.locator('#fr-password').fill('Mobile-test-only-2026!');await page.getByRole('button',{name:'Crea utente',exact:true}).click();
  ok((await page.locator('.fr-users-table').innerText()).includes(username),'a portal user can be created from the mobile admin form');
  await page.screenshot({path:resolve(__dirname,'screenshots/admin-users-mobile.png'),fullPage:true});
  await page.goto(origin+'/fitet-ricevute/#/prezzi');await page.getByRole('button',{name:'Esci',exact:true}).click();await page.getByRole('heading',{name:'Accedi al portale',exact:true}).waitFor();
  await page.getByLabel('Username',{exact:true}).fill(username);await page.getByLabel('Password',{exact:true}).fill('Mobile-test-only-2026!');await page.getByRole('button',{name:'Accedi',exact:true}).click();await count(page,'#prices-grid',2);
  ok(await page.getByLabel('Cerca prezzi',{exact:true}).inputValue()==='','another account on the same browser receives independent table preferences');
  console.log(checks+' preferences/player/mobile checks passed.');
 } catch(error) {try {await page.screenshot({path:resolve(__dirname,'screenshots/features-failure.png'),fullPage:true});console.log('FAIL PAGE',page.url(),(await page.locator('body').innerText()).slice(0,1800));}catch{}throw error;}
 finally {await context.close();await browser.close();await client.dispose();}
})().catch(error=>{console.error(error);process.exit(1)});
