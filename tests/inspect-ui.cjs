const {chromium}=require(process.env.FR_PLAYWRIGHT_MODULE);
(async()=>{
 const b=await chromium.launch({headless:true,executablePath:process.env.FR_BROWSER_PATH});
 const p=await b.newPage({viewport:{width:1600,height:1100}}); const errors=[];
 p.on('pageerror',e=>errors.push(e.message)); p.on('console',m=>{if(m.type()==='error')errors.push(m.text())});
 await p.goto('http://127.0.0.1:8095/fitet-ricevute/');
 await p.getByLabel('Username',{exact:true}).fill('fr-tester'); await p.getByLabel('Password',{exact:true}).fill('FR-test-only-2026!');
 await p.getByRole('button',{name:'Accedi',exact:true}).click();
 await p.waitForTimeout(7000);
 console.log('URL',p.url()); console.log('ERRORS',errors); console.log('BODY',(await p.locator('body').innerText()).slice(0,7000));
 console.log('ROWS',await p.locator('.ag-row').evaluateAll(rows=>rows.slice(0,3).map(row=>({attrs:[...row.attributes].map(a=>[a.name,a.value]),parent:row.parentElement.className,cells:[...row.querySelectorAll('.ag-cell')].map(c=>[...c.attributes].map(a=>[a.name,a.value]))}))));
 await p.screenshot({path:'tests/screenshots/inspect.png',fullPage:true}); await b.close();
})().catch(e=>{console.error(e);process.exit(1)});
