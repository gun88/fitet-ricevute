const assert=require('node:assert/strict');const {readFileSync,mkdirSync}=require('node:fs');const {resolve}=require('node:path');
const {chromium,request}=require(process.env.FR_PLAYWRIGHT_MODULE||'../app/node_modules/playwright');
(async()=>{
 const origin='http://127.0.0.1:8095',api=origin+'/wp-json/fitet-ricevute/v1/',config=origin+'/wp-admin/admin.php?page=fitet-ricevute';let checks=0;const check=(ok,text)=>{assert.ok(ok,text);checks++;console.log('PASS',text)};
 const browser=await chromium.launch({headless:true,...(process.env.FR_BROWSER_PATH?{executablePath:process.env.FR_BROWSER_PATH}:{})});
 const admin=await browser.newContext(),page=await admin.newPage(),client=await request.newContext({extraHTTPHeaders:{Origin:origin,'X-FR-Request':'1'}});
 const out=resolve(__dirname,'.runtime/signature-output');mkdirSync(out,{recursive:true});
 let portalContext;
 try{
  check((await client.get(api+'firma')).status()===401,'anonymous client cannot retrieve signature');
  await page.goto(origin+'/wp-login.php');await page.locator('#user_login').fill('wp-test-admin');await page.locator('#user_pass').fill('WP-test-only-2026!');await page.locator('#wp-submit').click();await page.waitForURL('**/wp-admin/**');
  await page.goto(config);await page.getByLabel('Immagine firma',{exact:true}).setInputFiles(resolve(__dirname,'.runtime/signature-a.png'));await page.getByRole('button',{name:'Salva firma',exact:true}).click();await page.getByText('Firma delle ricevute aggiornata.',{exact:true}).waitFor();
  check(await page.getByAltText('Anteprima firma delle ricevute',{exact:true}).isVisible(),'uploaded signature has admin preview');
  const session=await(await client.post(api+'auth/login',{data:{username:'fr-tester',password:'FR-test-only-2026!'}})).json();const headers={Origin:origin,'X-FR-Request':'1','X-FR-CSRF':session.csrf_token};
  const first=(await(await client.get(api+'firma')).json()).signature;check(first.format==='PNG'&&first.width===480,'authenticated client receives validated image');
  const anonymous=await request.newContext();check(!(await(await anonymous.get(api+'config')).text()).includes('data:image'),'public config does not expose signature');
  check((await admin.request.post(origin+'/wp-admin/admin-post.php',{form:{action:'fitet_ricevute_save_signature',_wpnonce:'invalid',clear_signature:'1'}})).status()===403,'signature mutation requires valid admin nonce');
  check((await anonymous.post(origin+'/wp-admin/admin-post.php',{form:{action:'fitet_ricevute_save_signature',clear_signature:'1'}})).status()>=400,'anonymous visitor cannot change signature');await anonymous.dispose();
  await page.getByRole('button',{name:'Salva impostazioni',exact:true}).click();check((await(await client.get(api+'firma')).json()).signature.sha256===first.sha256,'general settings preserve uploaded signature');
  const entries=(await(await client.get(api+'iscrizioni')).json()).items;const row=entries.find(r=>!Number(r.reimbursed)&&r.tournament_date.startsWith('2026'));
  let response=await client.post(api+'carrello',{headers,data:{ids:[Number(row.id)]}});check(response.ok(),'selected entry added for signed receipt');
  portalContext=await browser.newContext({acceptDownloads:true,viewport:{width:1400,height:1000}});const portal=await portalContext.newPage();const errors=[];portal.on('pageerror',e=>errors.push(e.message));
  await portal.goto(origin+'/fitet-ricevute/');await portal.getByLabel('Username',{exact:true}).fill('fr-tester');await portal.getByLabel('Password',{exact:true}).fill('FR-test-only-2026!');await portal.getByRole('button',{name:'Accedi',exact:true}).click();await portal.locator('#tournaments-grid .ag-row').first().waitFor();await portal.locator('.app-nav').getByRole('link',{name:/^Carrello/}).click();await portal.getByLabel('Società emittente',{exact:true}).fill('Firma UI test');
  const download=portal.waitForEvent('download');await portal.getByRole('button',{name:'Emetti rimborsi',exact:true}).click();await(await download).saveAs(resolve(out,'signed.pdf'));
  const history=(await(await client.get(api+'emissioni')).json()).items;const emission=history[0];check(emission.options.signature.sha256===first.sha256,'UI emission stores configured signature');check(!emission.options.signature.data_url,'registry list avoids transferring signature image for every row');
  await page.goto(config);await page.getByLabel('Immagine firma',{exact:true}).setInputFiles(resolve(__dirname,'.runtime/signature-b.png'));await page.getByRole('button',{name:'Salva firma',exact:true}).click();await page.getByText('Firma delle ricevute aggiornata.',{exact:true}).waitFor();
  check((await(await client.get(api+'firma')).json()).signature.sha256!==first.sha256,'signature can be replaced');
  await portal.goto(origin+'/fitet-ricevute/#/rimborsi/'+emission.id);await portal.getByRole('button',{name:'Scarica nuovamente le ricevute',exact:true}).waitFor();const again=portal.waitForEvent('download');await portal.getByRole('button',{name:'Scarica nuovamente le ricevute',exact:true}).click();await(await again).saveAs(resolve(out,'reprint.pdf'));
  check((await(await client.get(api+'emissioni/'+emission.id)).json()).options.signature.sha256===first.sha256,'existing emission retains original signature after replacement');
  await page.goto(config);await page.getByLabel('Rimuovi la firma dalle nuove ricevute',{exact:true}).check();await page.getByRole('button',{name:'Salva firma',exact:true}).click();await page.getByText('Firma delle ricevute aggiornata.',{exact:true}).waitFor();check((await(await client.get(api+'firma')).json()).signature===null,'signature can be removed for future receipts');
  await page.getByLabel('Immagine firma',{exact:true}).setInputFiles(resolve(__dirname,'.runtime/invalid-signature.svg'));await page.getByRole('button',{name:'Salva firma',exact:true}).click();check((await page.locator('body').innerText()).includes('PNG o JPG'),'unsupported upload rejected from admin');
  check(errors.length===0,'signed PDF flow has no browser errors');console.log(checks+' signature UI checks passed.');
 }finally{if(portalContext)await portalContext.close();await admin.close();await browser.close();await client.dispose();}
})().catch(e=>{console.error(e);process.exit(1)});
