const assert = require('node:assert/strict');
const { resolve } = require('node:path');
const { readFileSync, writeFileSync, mkdirSync } = require('node:fs');
const esbuild = require('../app/node_modules/esbuild');
const root = resolve(__dirname, '..');
const out = resolve(__dirname, '.runtime/refund-output'); mkdirSync(out, { recursive: true });
esbuild.buildSync({ entryPoints: [resolve(root,'app/src/app/refund-model.ts')], bundle:true, platform:'node', format:'cjs', outfile:resolve(__dirname,'.runtime/refund-model.cjs'), logLevel:'error' });
esbuild.buildSync({ entryPoints: [resolve(root,'app/src/app/refund-pdf.ts')], bundle:true, platform:'node', format:'cjs', outfile:resolve(__dirname,'.runtime/refund-pdf.cjs'), logLevel:'error' });
const { athleteSummary, pendingTotal } = require('./.runtime/refund-model.cjs');
const { buildRefundFiles, configurePdfFont } = require('./.runtime/refund-pdf.cjs');
configurePdfFont(readFileSync(resolve(root,'app/src/fonts/NotoSans-Regular.ttf')).toString('base64'));
let checks = 0;
function ok(value, message) { assert.ok(value,message); checks++; console.log('PASS',message); }
const row = { id:1, tournament_name:'Torneo regionale di qualificazione', tournament_date:'2026-10-24', tournament_place:'Località di prova', race_name:'Doppio maschile', race_kind:'double', athlete1_name:'Nicolò André', athlete1_card:'1001', athlete2_name:'Αλέξανδρος', athlete2_card:'1002', price_cents:1001, paid_cents:null, reimbursed:false };
let athletes = athleteSummary([row],'half');
ok(athletes.reduce((sum,a)=>sum+a.pending_cents,0)===1001 && athletes.find(a=>a.card==='1001').pending_cents===501, 'odd cent allocation preserves the exact pair amount');
athletes = athleteSummary([row],'first');
ok(athletes.length===1 && athletes[0].card==='1001' && athletes[0].pending_cents===1001, 'full pair quote can be assigned to first athlete');
athletes = athleteSummary([row],'second');
ok(athletes.length===1 && athletes[0].card==='1002' && athletes[0].pending_cents===1001, 'full pair quote can be assigned to second athlete');
const paid = {...row,reimbursed:true,paid_cents:800};
athletes = athleteSummary([paid],'half');
ok(athletes.reduce((sum,a)=>sum+a.paid_cents,0)===800 && pendingTotal([paid])===0, 'paid recap preserves recorded amount and excludes it from new emission');
ok(pendingTotal([{...row,price_cents:null}])===null && athleteSummary([{...row,price_cents:null}],'half').every(a=>a.pending_cents===null), 'unknown prices propagate into recap totals');
ok(pendingTotal([{...row,price_cents:0}])===0, 'zero-price reimbursement remains valid');
const single = {...row,id:2,athlete2_name:null,athlete2_card:null,race_kind:'single',price_cents:1500};
athletes = athleteSummary([row,single],'half');
ok(athletes.find(a=>a.card==='1001').pending_cents===2001 && athletes.find(a=>a.card==='1001').quotes===2, 'recap groups athlete across single and double participations');
const options = {issuer:'Società di test - Tennistavolo',date:'2026-10-24',notes:'Verifica di accenti e ripartizione delle quote.',format:'both',allocation:'half'};
const files = buildRefundFiles([row,single],options);
ok(Object.keys(files).length===3, 'both mode produces one cumulative and two individual receipts');
for (const [name,bytes] of Object.entries(files)) writeFileSync(resolve(out,'test-'+name),bytes);
try { buildRefundFiles([paid],options); ok(false,'paid selection rejection'); } catch { ok(true,'already paid rows cannot be emitted again'); }
try { buildRefundFiles([{...row,price_cents:null}],options); ok(false,'missing-price rejection'); } catch { ok(true,'incomplete quotes cannot be emitted'); }
const many = Array.from({length:80},(_,index)=>({...single,id:index+100,athlete1_name:'Nicolò André - beneficiario di prova numero '+index,tournament_name:'Torneo regionale molto lungo per verificare la corretta impaginazione delle ricevute e delle gare',price_cents:1500}));
const multi = buildRefundFiles(many,{...options,format:'total',notes:'Nota di verifica. '.repeat(25)});
writeFileSync(resolve(out,'multipagina.pdf'),Object.values(multi)[0]);
ok(Buffer.from(Object.values(multi)[0]).subarray(0,5).toString()==='%PDF-', 'large cumulative receipt is generated as a PDF');
console.log(checks+' refund model/PDF checks passed.');
