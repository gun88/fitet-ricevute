import { athleteSummary, Allocation } from './refund-model';
import type { Row } from './data.service';
export type PaymentMethod='transfer'|'cash';
export const paymentLabel=(method:PaymentMethod)=>method==='cash'?'Contanti':'Bonifico';
export interface Adjustment {beneficiary:string;reason:string;cents:number}
export interface SettlementOptions {allocation:Allocation;line_modes?:Record<string,'reimburse'|'deduct'|'paid'>;adjustments?:Adjustment[];payment_method?:PaymentMethod;payment_methods?:Record<string,PaymentMethod>}
export function settlement(rows:Row[],options:SettlementOptions){
 const people=athleteSummary(rows.map(r=>({...r,reimbursed:false,price_cents:r.paid_cents??r.price_cents})),options.allocation);
 return people.map(person=>{let amount:number|null=0;for(const line of person.lines){if(line.cents===null){amount=null;break;}amount+=line.cents*(options.line_modes?.[String(line.entry.id)]==='paid'?0:options.line_modes?.[String(line.entry.id)]==='deduct'?-1:1);}for(const a of options.adjustments||[])if(a.beneficiary===person.key&&amount!==null)amount+=a.cents;return {...person,pending_cents:amount,payment_method:options.payment_methods?.[person.key]||options.payment_method||'transfer'};});
}

export function paymentTotals(people:{pending_cents:number|null;payment_method:PaymentMethod}[]){return people.reduce((totals,p)=>{totals[p.payment_method]+=p.pending_cents??0;return totals;},{transfer:0,cash:0});}
export function emissionPayments(source:{items:Row[];options:SettlementOptions&{settlement?:Record<string,{cents:number;payment_method?:PaymentMethod}>}}){
 if(source.options.settlement)return Object.entries(source.options.settlement).map(([key,p])=>({pending_cents:p.cents,payment_method:source.options.payment_methods?.[key]||p.payment_method||source.options.payment_method||'transfer'}));
 return settlement(source.items,source.options);
}
export const emissionPaymentLabel=(source:Parameters<typeof emissionPayments>[0])=>{const methods=new Set(emissionPayments(source).map(p=>p.payment_method));return methods.size>1?'Bonifico e contanti':paymentLabel([...methods][0]||'transfer');};
