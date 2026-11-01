import type { ReceiptManifest } from './refund-export-model';
import { refundProgress } from './refund-progress';
import { Injectable, computed, inject, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { AuthService } from './auth.service';
import { DataService, Row } from './data.service';
import { apiUrl, config } from './config';
import { seasonFor, seasonsFor, playerKey } from './players-model';
import type { Allocation } from './refund-model';
export interface SignatureImage { data_url: string; sha256: string; width: number; height: number; format: 'PNG'|'JPEG' }
export interface ReceiptOptions { original_receipts?:unknown[]; signature?: SignatureImage | null; stamp?: SignatureImage | null; issuer: string; date: string; notes: string; allocation: Allocation; format: 'total'|'athletes'|'both' }
export interface Emission { id: number; user_id: number; created_at: string; total_cents: number; items: Row[]; options: ReceiptOptions }
export type EmissionSummary = Omit<Emission,'options'> & {options: Omit<ReceiptOptions,'signature'|'stamp'> & {signature?: Omit<SignatureImage,'data_url'> | null;stamp?: Omit<SignatureImage,'data_url'> | null}};
const normal = (row: any): Row => ({...row,id:Number(row.id),race_id:Number(row.race_id),price_cents:row.price_cents==null?null:Number(row.price_cents),paid_cents:row.paid_cents==null?null:Number(row.paid_cents),reimbursed:Number(row.reimbursed)===1});
@Injectable({providedIn:'root'})
export class PortalService {
 readonly data=inject(DataService); private readonly http=inject(HttpClient);private readonly auth=inject(AuthService);
 private revision=0;
 readonly cart=signal<Row[]>([]);readonly emissions=signal<EmissionSummary[]>([]);readonly season=signal('latest');
 readonly seasons=computed(()=>seasonsFor([...this.data.races(),...this.data.entries(),...this.emissions().flatMap(e=>e.items)]));
 readonly selectedSeason=computed(()=>this.season()==='latest'?(this.seasons()[0]??''):this.season());
 readonly races=computed(()=>this.filter(this.data.races().filter(r=>r.race_kind!=='empty')));
 readonly entries=computed(()=>this.filter(this.data.entries()));
 readonly cartRows=computed(()=>this.filter(this.cart()));
 readonly cartIds=computed(()=>new Set(this.cart().map(r=>r.id)));
 readonly history=computed(()=>this.emissions().filter(e=>this.filter(e.items).length));
 readonly tournaments=computed(()=>{
   const map=new Map<string,any>();
   for(const row of this.filter(this.data.races())){
     const current=map.get(row.tournament_key)??{...row,id:row.tournament_key,entry_count:0,race_count:0};
     current.entry_count+=Number(row.entry_count);current.race_count+=row.race_kind==='empty'?0:1;map.set(row.tournament_key,current);
   }
   return [...map.values()];
 });
 preferenceKey(name:string){return 'fr:portal:'+encodeURIComponent(config.apiUrl)+':'+this.auth.user()?.id+':'+name;}
 private seasonKey(){return this.preferenceKey('season');}
 setSeason(value:string){if(value!==''&&!/^(latest|all|\d{4}\/\d{4})$/.test(value))return;this.season.set(value);try{localStorage.setItem(this.seasonKey(),value);}catch{}}
 filter<T extends {tournament_date:string}>(rows:T[]):T[]{return this.selectedSeason()===''?[]:this.selectedSeason()==='all'?rows:rows.filter(r=>seasonFor(r.tournament_date)===this.selectedSeason());}
 restoreSeason(){try{this.setSeason(localStorage.getItem(this.seasonKey())??'latest');}catch{}}
 async load(){this.restoreSeason();await this.refresh();}
 async refresh(){
   const revision=++this.revision;
   const cart=await firstValueFrom(this.http.get<{items:any[]}>(apiUrl('carrello')));if(revision===this.revision)this.cart.set(cart.items.map(normal));
   const items:EmissionSummary[]=[];let page=1;
   while(true){const result=await firstValueFrom(this.http.get<{items:EmissionSummary[];total:number}>(apiUrl('emissioni'),{params:{page}}));items.push(...result.items.map(e=>({...e,items:e.items.map(normal)})));if(items.length>=result.total)break;if(!result.items.length)throw new Error('Elenco rimborsi cambiato. Ricarica.');page++;}
   if(revision===this.revision)this.emissions.set(items);
 }
 clear(){this.revision++;this.cart.set([]);this.emissions.set([]);this.season.set('latest');}
 async changeCart(rows:Row[],add:boolean){await this.data.perform(async()=>{
   this.revision++;
   const result=await firstValueFrom(this.http.request<{items:any[]}>(add?'POST':'DELETE',apiUrl('carrello'),{body:{ids:rows.map(r=>r.id)}}));this.cart.set(result.items.map(normal));this.data.notice.set(add?'Iscrizioni aggiunte alla bozza rimborso.':'Iscrizioni rimosse dalla bozza rimborso.');
 });}
 async updateLine(row:Row,notes:string){await this.data.perform(async()=>{this.revision++;const result=await firstValueFrom(this.http.patch<{items:any[]}>(apiUrl('carrello/'+row.id),{notes}));this.cart.set(result.items.map(normal));this.data.notice.set('Note della bozza salvate.');});}
 async emission(id:number):Promise<Emission>{const e=await firstValueFrom(this.http.get<Emission>(apiUrl('emissioni/'+id)));return {...e,items:e.items.map(normal)};}
 async receiptManifest(id:number):Promise<ReceiptManifest>{return firstValueFrom(this.http.get<ReceiptManifest>(apiUrl('emissioni/'+id+'/ricevute')));}
 async originalReceipt(id:number,receipt:string):Promise<Uint8Array>{return new Uint8Array(await firstValueFrom(this.http.get(apiUrl('emissioni/'+id+'/ricevute/'+receipt),{responseType:'arraybuffer'})));}
 async stamp():Promise<SignatureImage|null>{return (await firstValueFrom(this.http.get<{stamp:SignatureImage|null}>(apiUrl('timbro')))).stamp;}
 async signature():Promise<SignatureImage|null>{return (await firstValueFrom(this.http.get<{signature:SignatureImage|null}>(apiUrl('firma')))).signature;}
 async issue(rows:Row[],options:ReceiptOptions,key:string):Promise<Emission>{
   const result=await firstValueFrom(this.http.post<Emission>(apiUrl('emissioni'),{ids:rows.map(r=>r.id),prices:Object.fromEntries(rows.map(r=>[r.id,r.price_cents])),options:{...options,...('signature' in options?{signature:options.signature?{sha256:options.signature.sha256}:null}:{}),...('stamp' in options?{stamp:options.stamp?{sha256:options.stamp.sha256}:null}:{})},request_key:key}));
   return {...result,items:result.items.map(normal)};
 }
 state(row:Row):string{return row.reimbursed?'Rimborsata':(row as any).stale?'Da verificare':this.cartIds().has(row.id)?'In bozza rimborso':'Da rimborsare';}
 refundStats(key:string,raceId?:number){return refundProgress(this.data.entries().filter(row=>row.tournament_key===key&&(raceId===undefined||Number(row.race_id)===raceId)));}

 playerRows(key:string):Row[]{const map=new Map<number,Row>();for(const row of [...this.data.entries(),...this.emissions().flatMap(e=>e.items)]){if([playerKey(row.athlete1_name,row.athlete1_card),...(row.athlete2_name?[playerKey(row.athlete2_name,row.athlete2_card)]:[])].includes(key)&&!map.has(row.id))map.set(row.id,row);}return this.filter([...map.values()]);}
}
