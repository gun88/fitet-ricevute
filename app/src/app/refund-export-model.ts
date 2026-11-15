import type { Emission } from './portal.service';
import { PaymentMethod, paymentTotals, settlement } from './refund-settlement';
import { athleteSummary } from './refund-model';
import { names } from './format';
export interface OriginalReceipt {id:string;reference:string;sha256:string;source_url:string;tournament_name:string;tournament_date:string;tournament_key:string;available:boolean;pages?:number;firstPage?:number;lastPage?:number;problem?:string}
export interface ReceiptManifest {items:OriginalReceipt[];entry_receipts:Record<string,string>;legacy:boolean}
export interface RefundLine {beneficiary:string;card:string;date:string;place:string;tournament:string;race:string;athletes:string;cents:number;receipt:string;notes:string;entryId:number}
export interface RefundPlan {emission:Emission;beneficiaries:{name:string;card:string;cents:number;count:number;payment_method:PaymentMethod}[];paymentTotals:{transfer:number;cash:number};adjustments:{beneficiary:string;card:string;reason:string;cents:number}[];lines:RefundLine[];receipts:OriginalReceipt[];total:number;legacy:boolean}
export function refundPlan(emission:Emission,manifest:ReceiptManifest):RefundPlan {
 const rows=emission.items.map(row=>({...row,reimbursed:false,price_cents:row.paid_cents??row.price_cents}));
 if(!rows.length||rows.some(row=>!Number.isSafeInteger(row.price_cents)||row.price_cents!<0)||new Set(rows.map(row=>row.id)).size!==rows.length)throw new Error('Quote dell’emissione non valide.');
 const settled=settlement(rows,emission.options);const total=settled.reduce((sum,p)=>sum+(p.pending_cents??0),0);if(settled.some(p=>p.pending_cents===null||p.pending_cents<0)||total!==emission.total_cents)throw new Error('Il dettaglio non coincide con il totale dell’emissione.');
 if(!['half','first','second'].includes(emission.options.allocation))throw new Error('Ripartizione dei doppi non valida.');
 const receipts=[...new Map(manifest.items.map(item=>[item.id,{...item}])).values()];const references=new Map(receipts.map(receipt=>[receipt.id,receipt.reference]));
 const beneficiaries=athleteSummary(rows,emission.options.allocation);const lines:RefundLine[]=[];
 for(const person of beneficiaries)for(const line of person.lines.slice().sort((a,b)=>b.entry.tournament_date.localeCompare(a.entry.tournament_date)||a.entry.id-b.entry.id)){
  const row=line.entry,receipt=references.get(manifest.entry_receipts[String(row.id)]);if(!receipt)throw new Error('Manca il riferimento alla ricevuta di una quota.');
  lines.push({beneficiary:person.name,card:person.card,date:row.tournament_date,place:row.tournament_place,tournament:row.tournament_name,race:row.race_name,athletes:names(row),cents:line.cents!*(emission.options.line_modes?.[String(row.id)]==='paid'?0:emission.options.line_modes?.[String(row.id)]==='deduct'?-1:1),receipt,notes:[emission.options.line_modes?.[String(row.id)]==='paid'?'Iscrizione già pagata dalla società':'',emission.options.line_modes?.[String(row.id)]==='deduct'?'Iscrizione già pagata dalla società: detrazione':'',row.refund_notes||'',row.athlete2_name?'Coppia: '+names(row):''].filter(Boolean).join('\n'),entryId:row.id});
 }
 const adjustments=(emission.options.adjustments||[]).map(a=>{const person=settled.find(p=>p.key===a.beneficiary);if(!person)throw new Error('Beneficiario rettifica non disponibile.');return {beneficiary:person.name,card:person.card,reason:a.reason,cents:a.cents};});
 if(lines.reduce((sum,line)=>sum+line.cents,0)+adjustments.reduce((sum,a)=>sum+a.cents,0)!==total)throw new Error('La ripartizione non coincide con il totale del rimborso.');
 return {emission,beneficiaries:settled.map(person=>({name:person.name,card:person.card,cents:person.pending_cents!,count:person.quotes,payment_method:person.payment_method})),paymentTotals:paymentTotals(settled),adjustments,lines,receipts,total,legacy:manifest.legacy};
}
