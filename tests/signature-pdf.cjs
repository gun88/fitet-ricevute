const {readFileSync,writeFileSync,mkdirSync}=require('node:fs');const {resolve}=require('node:path');const {createHash}=require('node:crypto');
const esbuild=require('../app/node_modules/esbuild');const output=resolve(__dirname,'.runtime/signature-output');mkdirSync(output,{recursive:true});
esbuild.buildSync({entryPoints:[resolve(__dirname,'../app/src/app/refund-pdf.ts')],bundle:true,platform:'node',format:'cjs',outfile:resolve(__dirname,'.runtime/signature-pdf.cjs'),logLevel:'error'});
const {configurePdfFont,buildRefundFiles}=require('./.runtime/signature-pdf.cjs');configurePdfFont(readFileSync(resolve(__dirname,'../app/src/fonts/NotoSans-Regular.ttf')).toString('base64'));
const row={id:1,tournament_name:'Torneo di prova',tournament_date:'2026-11-01',tournament_place:'Milano',race_name:'Doppio maschile',race_kind:'double',athlete1_name:'Nicolò André',athlete1_card:'1001',athlete2_name:'Atleta Due',athlete2_card:'1002',price_cents:1001,paid_cents:null,reimbursed:false};
const bytes=readFileSync(resolve(__dirname,'.runtime/signature-a.png'));const signature={data_url:'data:image/png;base64,'+bytes.toString('base64'),sha256:createHash('sha256').update(bytes).digest('hex'),width:480,height:120,format:'PNG'};
const options={issuer:'Società di test',date:'2026-11-01',notes:'Verifica firma',format:'both',allocation:'half',signature};
for(const [name,bytes]of Object.entries(buildRefundFiles([row],options)))writeFileSync(resolve(output,'model-'+name),bytes);
const many=Array.from({length:80},(_,i)=>({...row,id:i+1,tournament_name:'Torneo con descrizione lunga per controllare il posizionamento della firma su più pagine',athlete1_name:'Atleta numero '+i}));
writeFileSync(resolve(output,'multipage.pdf'),Object.values(buildRefundFiles(many,{...options,format:'total'}))[0]);
writeFileSync(resolve(output,'unsigned.pdf'),Object.values(buildRefundFiles([row],{...options,format:'total',signature:null}))[0]);
console.log('Generated signed cumulative, athlete, multipage and unsigned fixtures.');

const jpg=readFileSync(resolve(__dirname,'.runtime/signature.jpg'));
writeFileSync(resolve(output,'jpeg.pdf'),Object.values(buildRefundFiles([row],{...options,format:'total',signature:{...signature,format:'JPEG',data_url:'data:image/jpeg;base64,'+jpg.toString('base64')}}))[0]);

const stampBytes=readFileSync(resolve(__dirname,'.runtime/stamp.png'));
const stamp={data_url:'data:image/png;base64,'+stampBytes.toString('base64'),sha256:createHash('sha256').update(stampBytes).digest('hex'),width:240,height:240,format:'PNG'};
const longNotes=Array.from({length:100},(_,i)=>'Nota multilinea '+String(i+1).padStart(3,'0')).join('\n');
writeFileSync(resolve(output,'long-notes.pdf'),Object.values(buildRefundFiles([{...row,refund_notes:'Nota della riga'}],{...options,format:'total',notes:longNotes,stamp}))[0]);
console.log('Generated 100-line notes fixture with signature and stamp.');
