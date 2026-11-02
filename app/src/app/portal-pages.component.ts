import { RefundRecapComponent } from './refund-recap.component';
import { refundStatistics } from './refund-statistics';
import { IconComponent } from './icon.component';
import { emissionPayments, emissionPaymentLabel, paymentTotals, paymentLabel } from './refund-settlement';
import { tournamentType } from './format';
import { FormsModule } from '@angular/forms';
import { Component, computed, inject, signal, HostListener, ElementRef, ViewChild } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { toSignal } from '@angular/core/rxjs-interop';
import { PortalService } from './portal.service';
import { EntityGridComponent } from './entity-grid.component';
import { errorMessage } from './auth.service';
import { money, dateLabel } from './format';
@Component({selector:'fr-portal-page',imports:[IconComponent,EntityGridComponent,RouterLink],template:`
 <div class="page-heading"><div><div class="page-title-row"><h1>{{title}}</h1>@if(kind==='tournaments'){<div class="page-advanced-actions" #advancedActions><button #advancedTrigger class="page-more-button" type="button" aria-label="Funzioni avanzate Tornei" aria-controls="tournament-advanced-menu" [attr.aria-expanded]="advancedOpen()" (click)="advancedOpen.update(toggle)"><fr-icon name="more" /></button>@if(advancedOpen()){<nav id="tournament-advanced-menu" class="page-advanced-menu" aria-label="Funzioni avanzate"><span>Funzioni avanzate</span><a routerLink="/tornei/nuovo" (click)="advancedOpen.set(false)">Aggiungi torneo</a></nav>}</div>}</div><p class="dashboard-subtitle">{{subtitle}}</p></div>
 @if(kind==='tournaments'){<div class="sync-controls"><button class="sync-button" [disabled]="portal.data.locked()||portal.season()===''" (click)="sync()">{{portal.data.syncing()?'Sincronizzazione…':'Sincronizza ricevute'}}</button><span class="last-sync">Ultima sincronizzazione: {{portal.data.lastSyncLabel()}}</span></div>}</div>
 @if(kind==='emissions'){<div class="recap-totals refund-stat-cards">@if(paymentTotals().transfer){<div><span>Totale bonifico</span><strong>{{money(paymentTotals().transfer)}}</strong></div>}@if(paymentTotals().cash){<div><span>Totale contanti</span><strong>{{money(paymentTotals().cash)}}</strong></div>}<div><span>Totale giocatori rimborsati</span><strong>{{refundCounts().players}}</strong></div><div><span>Totale gare rimborsate</span><strong>{{refundCounts().races}}</strong></div></div>}
 @if(kind==='tournaments' && (portal.data.syncing() || portal.data.job()) && (!portal.data.job()?.done || hiddenJob()!==portal.data.job()?.id)){
  <div class="sync-progress notice">@if(portal.data.job()?.done){<button class="text-button" aria-label="Nascondi esito sincronizzazione" (click)="hiddenJob.set(portal.data.job()!.id)"><fr-icon name="close" [size]="16" /> Chiudi</button>}<div class="sync-progress-label" role="status"><span>@if(portal.data.job()){@if(portal.data.job()!.phase==='posters'){Ricerca locandine: {{portal.data.job()!.poster_processed}} / {{portal.data.job()!.poster_total}} · }Ricevute elaborate: {{portal.data.job()!.processed}} / {{portal.data.job()!.total}}}@else{Accesso all’extranet e ricerca automatica delle ricevute…}</span>@if(portal.data.job()){<strong>{{portal.data.syncProgress()}}%</strong>}</div><progress max="100" [attr.value]="portal.data.job()?portal.data.syncProgress():null" aria-label="Avanzamento elaborazione ricevute"></progress></div>
 }
 @if(kind==='tournaments' && portal.data.job()?.errors?.length){<div class="error-box"><strong>Ricevute da verificare</strong><ul>@for(item of portal.data.job()!.errors;track item.receipt){<li>Ricevuta {{item.receipt}}: {{item.message}}</li>}</ul></div>}
 @if(query()?.get('torneo')||query()?.get('gara')){<p class="filter-context">Filtro: {{contextName()}} <a [routerLink]="kind==='races'?'/gare':'/iscrizioni'">Rimuovi filtro di navigazione</a></p>}
 <fr-entity-grid [kind]="kind" [rows]="rows()" [viewKey]="kind" [label]="title">
  @if(kind==='tournaments'){<label tableFilter class="check-field"><input type="checkbox" [checked]="onlyEntries()" (change)="setOnly($any($event.target).checked)">Mostra solo {{kind==='tournaments'?'tornei':'gare'}} con iscrizioni</label>}
 </fr-entity-grid>
`})
export class PortalPageComponent {
 readonly advancedOpen=signal(false);readonly toggle=(value:boolean)=>!value;
 @ViewChild('advancedActions') private advancedActions?:ElementRef<HTMLElement>;
 @ViewChild('advancedTrigger') private advancedTrigger?:ElementRef<HTMLButtonElement>;
 @HostListener('document:click',['$event']) closeAdvancedOutside(event:MouseEvent){if(this.advancedOpen()&&!this.advancedActions?.nativeElement.contains(event.target as Node))this.advancedOpen.set(false);}
 @HostListener('document:focusin',['$event']) closeAdvancedFocus(event:FocusEvent){if(this.advancedOpen()&&!this.advancedActions?.nativeElement.contains(event.target as Node))this.advancedOpen.set(false);}
 @HostListener('document:keydown.escape') closeAdvancedEscape(){if(this.advancedOpen()){this.advancedOpen.set(false);this.advancedTrigger?.nativeElement.focus();}}

 readonly portal=inject(PortalService);private readonly route=inject(ActivatedRoute);readonly query=toSignal(this.route.queryParamMap);
 readonly kind=this.route.snapshot.data['kind'] as 'tournaments'|'races'|'entries'|'emissions';
 readonly title={tournaments:'Tornei',races:'Gare',entries:'Iscrizioni',emissions:'Rimborsi Emessi'}[this.kind];
 readonly subtitle={tournaments:'Tutti i tornei della stagione selezionata.',races:'Quote, riconciliazione e gare dei tornei.',entries:'Aggiungi le quote alla bozza rimborso per la revisione finale.',emissions:'Registro delle emissioni con importi e iscrizioni conservati.'}[this.kind];
 readonly money=money;readonly paymentTotals=computed(()=>this.portal.history().reduce((totals,e)=>{const payments=paymentTotals(emissionPayments(e));totals.cash+=payments.cash;totals.transfer+=payments.transfer;return totals;},{cash:0,transfer:0}));
 readonly refundCounts=computed(()=>refundStatistics(this.portal.history(),rows=>this.portal.filter(rows)));
 readonly hiddenJob=this.portal.data.dismissedSyncJob;
 readonly onlyEntries=signal(this.readOnly());
 private key(){return this.portal.preferenceKey('only-entries:'+this.kind);}
 private readOnly(){try{return localStorage.getItem(this.key())!=='false';}catch{return true;}}
 setOnly(value:boolean){this.onlyEntries.set(value);try{localStorage.setItem(this.key(),String(value));}catch{}}
 readonly contextName=computed(()=>{const id=this.query()?.get('gara');return id?this.portal.data.races().find(r=>String(r.id)===id)?.race_name:this.portal.data.races().find(r=>r.tournament_key===this.query()?.get('torneo'))?.tournament_name;});
 readonly rows=computed(()=>{
  let rows:any[]=this.kind==='tournaments'?this.portal.tournaments():this.kind==='races'?this.portal.races():this.kind==='entries'?this.portal.entries():this.portal.history();
  if(this.kind==='tournaments'&&this.onlyEntries())rows=rows.filter(r=>Number(r.entry_count)>0);
  const tournament=this.query()?.get('torneo'),race=this.query()?.get('gara');
  if(tournament)rows=rows.filter(r=>r.tournament_key===tournament);if(race)rows=rows.filter(r=>String(r.race_id||r.id)===race);return rows;
 });
 async sync(){if(this.portal.season()==='')return;try{await this.portal.data.synchronize();await this.portal.refresh();}catch(error){this.portal.data.error.set(errorMessage(error));}}
}
@Component({selector:'fr-portal-detail',imports:[RefundRecapComponent,IconComponent,EntityGridComponent,RouterLink,FormsModule],template:`
 <div class="page-heading"><div><h1>{{title()}}</h1><p class="dashboard-subtitle">{{kind==='tournament'?'Dettaglio torneo':kind==='race'?'Dettaglio gara':kind==='player'?'Dettaglio giocatore':'Dettaglio rimborso'}}</p></div>@if(kind==='player'||kind==='emission'){<a class="secondary-button" [routerLink]="kind==='emission'?'/rimborsi':'/tornei'">Torna all’elenco</a>}</div>
 @if(info();as row){<dl class="detail-summary">
  @if(kind==='emission'){<dt>Emesso il</dt><dd>{{timestamp(row.created_at)}}</dd><dt>Operatore</dt><dd>{{row.user_name||'Operatore non disponibile'}}</dd><dt>Totale emesso</dt><dd>{{money(row.total_cents)}}</dd><dt>Emittente</dt><dd>{{row.options.issuer}}</dd><dt>Data ricevuta</dt><dd>{{dateLabel(row.options.date)}}</dd><dt>Modalità rimborso</dt><dd>{{paymentDescription(row)}}</dd><dt>Note</dt><dd>{{row.options.notes||'—'}}</dd>@if(row.options.updated_at){<dt>Ultima modifica</dt><dd>{{timestamp(row.options.updated_at)}} · {{row.options.updated_by_name||'Operatore non disponibile'}}</dd>}}
  @else if(kind==='player'){<dt>Giocatore</dt><dd>{{row.name}} ({{row.card||'—'}})</dd><dt>Partecipazioni nella stagione selezionata</dt><dd>{{entries().length}}</dd>}
  @else{<dt>Torneo</dt><dd><a [routerLink]="'/tornei/'+row.tournament_key">{{row.tournament_name}}</a></dd><dt>Data</dt><dd>{{dateLabel(row.tournament_date)}}</dd><dt>Località</dt><dd>{{row.tournament_place}}</dd><dt>Tipo</dt><dd>{{tournamentType(row)}}</dd>@if(kind==='tournament'){<dt>Locandina</dt><dd>@if(row.poster_url){<a [href]="row.poster_url" target="_blank" rel="noopener noreferrer">Visualizza locandina</a>}@else{ {{row.tournament_scope==='national'?'Non prevista per i tornei nazionali':'Non disponibile'}} }</dd>}@if(kind==='race'){<dt>Gara</dt><dd>{{row.race_name}}</dd><dt>Quota</dt><dd>{{money(row.price_cents)}}</dd>}<dt>Rimborsi</dt><dd class="refund-progress-summary"><strong>{{refundSummary().count}}</strong><span>({{refundSummary().label}})</span></dd>}
 </dl>
 @if(kind==='emission'){
 @if(row.items?.length){<h2>Riepilogo rimborsi</h2><fr-refund-recap [emission]="row" /><div class="settlement-summary">@if(payments(row).transfer){<p>Totale bonifico: {{money(payments(row).transfer)}}</p>}@if(payments(row).cash){<p>Totale contanti: {{money(payments(row).cash)}}</p>}</div>}
 @if(row.options.history?.length){<details class="revision-history"><summary><fr-icon class="disclosure-icon" name="chevron-right" [size]="16" />Versioni precedenti ({{row.options.history.length}})</summary><ul>@for(version of row.options.history;track version.revision){<li>Versione {{version.revision+1}} · {{money(version.total_cents)}} · sostituita il {{timestamp(version.replaced_at)}} da {{version.replaced_by_name||'Operatore non disponibile'}}</li>}</ul></details>}
 <div class="print-controls"><a class="secondary-button" [routerLink]="'/rimborsi/'+row.id+'/modifica'">Modifica rimborso</a><button class="sync-button" (click)="download('pdf')" [disabled]="portal.data.locked()">Stampa rimborso (PDF)</button><button class="secondary-button" (click)="download('xlsx')" [disabled]="portal.data.locked()">Esporta Excel</button><button class="secondary-button" (click)="download('zip')" [disabled]="portal.data.locked()">Scarica PDF + Excel (ZIP)</button></div>
 <p class="grid-help">Un unico PDF con quanto consegnare a ogni atleta, dettaglio quote e ricevute originali. L’Excel contiene quattro fogli con dati filtrabili e totali calcolati.</p>
 @if(!row.options.original_receipts){<p class="notice">Per questa emissione precedente, le ricevute vengono recuperate dai documenti attualmente associati ai tornei: verificarne la corrispondenza.</p>}
 }
 @if(kind==='tournament'){<h2>Gare</h2><fr-entity-grid kind="races" [rows]="races()" viewKey="tournament-races" label="gare del torneo" />}
 @if(kind==='emission'){<p class="grid-help">Importo e PDF si riferiscono all’emissione completa; la tabella segue la stagione selezionata.</p>}<h2>Iscrizioni</h2><fr-entity-grid kind="entries" [rows]="entries()" [viewKey]="kind+'-entries'" label="iscrizioni" />
 @if(kind==='player'){<h2>Rimborsi</h2><fr-entity-grid kind="emissions" [rows]="playerEmissions()" viewKey="player-emissions" label="rimborsi del giocatore" />}
 }@else{<p class="empty-state">Nessun dettaglio disponibile nella stagione selezionata. Puoi scegliere un’altra stagione dal menu generale.</p>}
`})
export class PortalDetailComponent {
 readonly portal=inject(PortalService);private readonly route=inject(ActivatedRoute);readonly params=toSignal(this.route.paramMap);
 readonly kind=this.route.snapshot.data['kind'] as 'tournament'|'race'|'player'|'emission';readonly id=computed(()=>this.params()?.get('id')||this.params()?.get('key')||'');
 readonly refundSummary=computed(()=>this.portal.refundStats(this.info()?.tournament_key||'',this.kind==='race'?Number(this.info()?.race_id||this.info()?.id):undefined));
 readonly paymentLabel=paymentLabel;readonly paymentDescription=emissionPaymentLabel;
 payments(row:any){return paymentTotals(emissionPayments(row));}
 settlements(row:any):any[]{return Object.entries(row.options.settlement||{}).map(([key,p]:[string,any])=>({...p,payment_method:row.options.payment_methods?.[key]||p.payment_method||row.options.payment_method||'transfer'}));}
 readonly tournamentType=tournamentType;readonly money=money;readonly dateLabel=dateLabel;readonly timestamp=(date:string)=>new Date(date).toLocaleString('it-IT');
 readonly emission=computed(()=>this.portal.emissions().find(e=>String(e.id)===this.id()));
 readonly entries=computed(()=>this.kind==='emission'?this.portal.filter(this.emission()?.items||[]):this.kind==='player'?this.portal.playerRows(this.id()):this.portal.entries().filter(r=>this.kind==='race'?String(r.race_id)===this.id():r.tournament_key===this.id()));
 readonly races=computed(()=>this.portal.races().filter(r=>r.tournament_key===this.id()));
 readonly info=computed<any>(()=>{
  if(this.kind==='emission')return this.emission();
  if(this.kind==='player'){const r=this.entries()[0];const second=r&&('card:'+r.athlete2_card===this.id()||'name:'+r.athlete2_name?.trim().toLocaleUpperCase('it-IT')===this.id());return r?{name:second?r.athlete2_name:r.athlete1_name,card:second?r.athlete2_card:r.athlete1_card}:null;}
  const all=[...this.portal.filter(this.portal.data.races()),...this.portal.filter(this.portal.emissions().flatMap(e=>e.items))];
  return all.find(r=>this.kind==='race'?String(r.race_id||r.id)===this.id():r.tournament_key===this.id());
 });
 readonly title=computed(()=>this.kind==='emission'?'Rimborso #'+this.id():this.kind==='player'?this.info()?.name||'Giocatore':this.kind==='race'?this.info()?.race_name||'Gara':this.info()?.tournament_name||'Torneo');
 readonly playerEmissions=computed(()=>{const ids=new Set(this.entries().map(r=>r.id));return this.portal.history().filter(e=>e.items.some(r=>ids.has(r.id)));});
 async download(format:'pdf'|'xlsx'|'zip'){
  const e=this.emission();if(!e)return;
  await this.portal.data.perform(async()=>{
   const full=await this.portal.emission(e.id),manifest=await this.portal.receiptManifest(e.id);
   const {downloadRefundExport}=await import('./refund-export');
   await downloadRefundExport(full,manifest,id=>this.portal.originalReceipt(e.id,id),format,text=>this.portal.data.notice.set(text));
   this.portal.data.notice.set(format==='pdf'?'PDF completo preparato.':format==='xlsx'?'Excel preparato.':'PDF ed Excel preparati.');
  });
 }
}
