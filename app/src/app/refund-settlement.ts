import { athleteSummary, Allocation } from './refund-model';
import type { Row } from './data.service';
export interface Adjustment {beneficiary:string;reason:string;cents:number}
export interface SettlementOptions {allocation:Allocation;line_modes?:Record<string,'reimburse'|'deduct'>;adjustments?:Adjustment[];payment_method?:'transfer'|'cash'}
export function settlement(rows:Row[],options:SettlementOptions){
 const people=athleteSummary(rows.map(r=>({...r,reimbursed:false,price_cents:r.paid_cents??r.price_cents})),options.allocation);
 return people.map(person=>{let amount:number|null=0;for(const line of person.lines){if(line.cents===null){amount=null;break;}amount+=line.cents*(options.line_modes?.[String(line.entry.id)]==='deduct'?-1:1);}for(const a of options.adjustments||[])if(a.beneficiary===person.key&&amount!==null)amount+=a.cents;return {...person,pending_cents:amount};});
}
