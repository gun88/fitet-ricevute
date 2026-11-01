const assert=require('node:assert/strict');const {resolve}=require('node:path');const {mkdirSync}=require('node:fs');

const {chromium}=require(process.env.FR_PLAYWRIGHT_MODULE||'../app/node_modules/playwright');

(async()=>{const origin='http://127.0.0.1:8095',api=origin+'/wp-json/fitet-ricevute/v1/';const browser=await chromium.launch({headless:true,executablePath:process.env.FR_BROWSER_PATH});let checks=0;const check=(x,s)=>{assert.ok(x,s);checks++;console.log('PASS '+s);};const errors=[];

const session=async()=>{const c=await browser.newContext({viewport:{width:390,height:844}});await c.request.post(api+'auth/login',{headers:{Origin:origin,'X-FR-Request':'1'},data:{username:'fr-tester',password:'FR-test-only-2026!'}});return c;};

let c=await session(),p;try{

 await c.route('**/gare?*',async route=>{const response=await route.fetch();const json=await response.json();const source=json.items.find(r=>r.race_kind!=='empty');json.items=Array.from({length:65},(_,i)=>({...source,id:9000+i,race_id:9000+i,tournament_key:'mock-'+i,tournament_name:'Card Mock '+String(i).padStart(3,'0'),entry_count:1}));json.total=65;await route.fulfill({response,json});});

 p=await c.newPage();p.on('pageerror',e=>errors.push(e.message));await p.goto(origin+'/fitet-ricevute/#/tornei');await p.getByRole('heading',{name:'Tornei',exact:true}).waitFor();await p.locator('.mobile-card').first().waitFor();check(await p.locator('ag-grid-angular').count()===0,'mobile mounts cards without AG Grid');check(await p.locator('.mobile-card').count()===20,'first batch contains 20 of 65 cards');

 for(const n of [40,60,65]){await p.locator('.infinite-sentinel').scrollIntoViewIfNeeded();await p.waitForFunction(n=>document.querySelectorAll('.mobile-card').length>=n,n);check(await p.locator('.mobile-card').count()===n,'infinite scroll reaches '+n+' cards');}

 await p.getByRole('searchbox').fill('Mock 064');await p.waitForFunction(()=>document.querySelectorAll('.mobile-card').length===1);check((await p.locator('.mobile-card').innerText()).includes('064'),'search covers all records beyond first batch');await p.locator('.mobile-card summary').click();await p.locator('.card-fields').waitFor({state:'visible'});check(await p.locator('.card-fields').isVisible(),'secondary information expands');

 await p.setViewportSize({width:1600,height:1000});await p.locator('#tournaments-grid .ag-row').first().waitFor();check(await p.locator('.mobile-card').count()===0,'desktop mounts AG Grid instead of cards');check(await p.getByRole('searchbox').inputValue()==='Mock 064','general search survives breakpoint');await p.getByRole('searchbox').fill('');

 await p.locator('#tournaments-grid .ag-floating-filter[col-id="tournament_name"] input').fill('Mock 064');await p.waitForFunction(()=>Object.entries(localStorage).some(([k,v])=>k.endsWith(':portal-tournaments')&&JSON.parse(v).grid?.filter?.filterModel?.tournament_name?.filter==='Mock 064'));await p.waitForFunction(()=>document.querySelectorAll('#tournaments-grid .ag-row').length===1);

 await p.setViewportSize({width:390,height:844});await p.locator('.mobile-card').first().waitFor();check(await p.locator('.mobile-card').count()>=20,'mobile ignores desktop column filters');await p.setViewportSize({width:1600,height:1000});await p.waitForFunction(()=>document.querySelectorAll('#tournaments-grid .ag-row').length===1);check(true,'desktop column filter survives card transition');await c.close();

 c=await session();p=await c.newPage();p.on('pageerror',e=>errors.push(e.message));await p.goto(origin+'/fitet-ricevute/#/iscrizioni');await p.getByRole('heading',{name:'Iscrizioni',exact:true}).waitFor();await p.locator('.mobile-card').first().waitFor();await p.getByRole('button',{name:'Metti in bozza',exact:true}).first().click();await p.getByRole('status').filter({hasText:'Iscrizioni aggiunte'}).waitFor();check((await(await c.request.get(api+'carrello')).json()).items.length===1,'card action adds entry to saved draft');

 await p.goto(origin+'/fitet-ricevute/#/bozza-rimborso');await p.getByRole('heading',{name:'Bozza rimborso',exact:true}).waitFor();await p.locator('.mobile-card').first().waitFor();check(await p.locator('ag-grid-angular').count()===0,'draft and athlete recap both use mobile cards');await p.locator('.mobile-card').first().locator('summary').click();await p.getByLabel('Note riga',{exact:true}).fill('Nota dalla card mobile');await p.getByRole('button',{name:'Salva note',exact:true}).click();await p.getByRole('status').filter({hasText:'Note della bozza salvate'}).waitFor();check((await(await c.request.get(api+'carrello')).json()).items[0].refund_notes==='Nota dalla card mobile','mobile notes persist through shared API');

 mkdirSync(resolve(__dirname,'screenshots'),{recursive:true});await p.screenshot({path:resolve(__dirname,'screenshots/mobile-draft-cards.png'),fullPage:true});await p.getByRole('button',{name:'Rimuovi',exact:true}).click();await p.getByRole('status').filter({hasText:'Iscrizioni rimosse'}).waitFor();check((await(await c.request.get(api+'carrello')).json()).items.length===0,'card removes draft entry');

 await p.goto(origin+'/fitet-ricevute/#/gare');await p.getByRole('heading',{name:'Gare',exact:true}).waitFor();await p.locator('.mobile-card').first().waitFor();await p.locator('.mobile-card').first().locator('summary').click();await p.getByLabel('Quota (€)',{exact:true}).first().fill('17,50');await p.getByRole('button',{name:'Salva quota',exact:true}).first().click();await p.getByRole('status').filter({hasText:'Prezzo salvato'}).waitFor();check((await p.locator('.card-amount').first().innerText()).includes('17,50'),'mobile price edit refreshes amount');

 await p.screenshot({path:resolve(__dirname,'screenshots/mobile-race-cards.png'),fullPage:true});

 for(const path of ['tornei','rimborsi','giocatori']){await p.goto(origin+'/fitet-ricevute/#/'+path);await p.locator('main h1').waitFor();check(await p.locator('ag-grid-angular').count()===0,'no mobile grid on '+path);check(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'no overflow on '+path);}

 await p.goto(origin+'/fitet-ricevute/#/tornei');await p.getByRole('heading',{name:'Tornei',exact:true}).waitFor();await p.locator('.mobile-card').first().waitFor();await p.screenshot({path:resolve(__dirname,'screenshots/mobile-tournament-cards.png'),fullPage:true});await p.locator('.mobile-card').first().getByRole('link',{name:'Dettaglio',exact:true}).click();await p.locator('main h2').first().waitFor();check(await p.locator('ag-grid-angular').count()===0,'tournament detail uses cards for nested lists');check(errors.length===0,'no browser errors: '+errors.join('; '));console.log(checks+' mobile card checks passed.');

}catch(e){console.log('DEBUG',await p.evaluate(()=>({rows:document.querySelectorAll('#tournaments-grid .ag-row').length,inputs:[...document.querySelectorAll('#tournaments-grid input')].map(x=>({value:x.value,placeholder:x.placeholder})),prefs:Object.entries(localStorage).filter(([k])=>k.endsWith(':portal-tournaments')).map(([k,v])=>JSON.parse(v).grid?.filter),errors:[...document.querySelectorAll('.error-box')].map(x=>x.textContent)})));throw e;}finally{await c.close();await browser.close();}})().catch(e=>{console.error(e);process.exit(1)});

