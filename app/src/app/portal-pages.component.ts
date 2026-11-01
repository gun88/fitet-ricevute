import { FormsModule } from '@angular/forms';
import { Component, computed, inject, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { toSignal } from '@angular/core/rxjs-interop';
import { PortalService } from './portal.service';
import { EntityGridComponent } from './entity-grid.component';
import { errorMessage } from './auth.service';
import { money, dateLabel } from './format';
@Component({selector:'fr-portal-page',imports:[EntityGridComponent,RouterLink],template:`
 <div class="page-heading"><div><h1>{{title}}</h1><p class="dashboard-subtitle">{{subtitle}}</p></div>
 @if(kind==='tournaments'){<div class="sync-controls"><button class="sync-button" [disabled]="portal.data.locked()||portal.season()===''" (click)="sync()">{{portal.data.syncing()?'Sincronizzazione…':'Sincronizza ricevute'}}</button><span class="last-sync">Ultima sincronizzazione: {{portal.data.lastSyncLabel()}}</span></div>}</div>
 @if(kind==='tournaments' && (portal.data.syncing() || portal.data.job())){
  <div class="sync-progress notice"><div class="sync-progress-label" role="status"><span>@if(portal.data.job()){Ricevute elaborate: {{portal.data.job()!.processed}} / {{portal.data.job()!.total}}}@else{Accesso all’extranet e ricerca automatica delle ricevute…}</span>@if(portal.data.job()){<strong>{{portal.data.syncProgress()}}%</strong>}</div><progress max="100" [attr.value]="portal.data.job()?portal.data.syncProgress():null" aria-label="Avanzamento elaborazione ricevute"></progress></div>
 }
 @if(kind==='tournaments' && portal.data.job()?.errors?.length){<div class="error-box"><strong>Ricevute da verificare</strong><ul>@for(item of portal.data.job()!.errors;track item.receipt){<li>Ricevuta {{item.receipt}}: {{item.message}}</li>}</ul></div>}
 @if(query()?.get('torneo')||query()?.get('gara')){<p class="filter-context">Filtro: {{contextName()}} <a [routerLink]="kind==='races'?'/gare':'/iscrizioni'">Rimuovi filtro di navigazione</a></p>}
 <fr-entity-grid [kind]="kind" [rows]="rows()" [viewKey]="kind" [label]="title">
  @if(kind==='tournaments'||kind==='races'){<label tableFilter class="check-field"><input type="checkbox" [checked]="onlyEntries()" (change)="setOnly($any($event.target).checked)">Mostra solo {{kind==='tournaments'?'tornei':'gare'}} con iscrizioni</label>}
 </fr-entity-grid>
`})
export class PortalPageComponent {
 readonly portal=inject(PortalService);private readonly route=inject(ActivatedRoute);readonly query=toSignal(this.route.queryParamMap);
 readonly kind=this.route.snapshot.data['kind'] as 'tournaments'|'races'|'entries'|'emissions';
 readonly title={tournaments:'Tornei',races:'Gare',entries:'Iscrizioni',emissions:'Rimborsi Emessi'}[this.kind];
 readonly subtitle={tournaments:'Tutti i tornei della stagione selezionata.',races:'Quote, riconciliazione e gare dei tornei.',entries:'Aggiungi le quote alla bozza rimborso per la revisione finale.',emissions:'Registro delle emissioni con importi e iscrizioni conservati.'}[this.kind];
 readonly onlyEntries=signal(this.readOnly());
 private key(){return this.portal.preferenceKey('only-entries:'+this.kind);}
 private readOnly(){try{return localStorage.getItem(this.key())!=='false';}catch{return true;}}
 setOnly(value:boolean){this.onlyEntries.set(value);try{localStorage.setItem(this.key(),String(value));}catch{}}
 readonly contextName=computed(()=>{const id=this.query()?.get('gara');return id?this.portal.data.races().find(r=>String(r.id)===id)?.race_name:this.portal.data.races().find(r=>r.tournament_key===this.query()?.get('torneo'))?.tournament_name;});
 readonly rows=computed(()=>{
  let rows:any[]=this.kind==='tournaments'?this.portal.tournaments():this.kind==='races'?this.portal.races():this.kind==='entries'?this.portal.entries():this.portal.history();
  if((this.kind==='tournaments'||this.kind==='races')&&this.onlyEntries())rows=rows.filter(r=>Number(r.entry_count)>0);
  const tournament=this.query()?.get('torneo'),race=this.query()?.get('gara');
  if(tournament)rows=rows.filter(r=>r.tournament_key===tournament);if(race)rows=rows.filter(r=>String(r.race_id||r.id)===race);return rows;
 });
 async sync(){if(this.portal.season()==='')return;try{await this.portal.data.synchronize();await this.portal.refresh();}catch(error){this.portal.data.error.set(errorMessage(error));}}
}
@Component({selector:'fr-portal-detail',imports:[EntityGridComponent,RouterLink,FormsModule],template:`
 <div class="page-heading"><div><h1>{{title()}}</h1><p class="dashboard-subtitle">{{kind==='tournament'?'Dettaglio torneo':kind==='race'?'Dettaglio gara':kind==='player'?'Dettaglio giocatore':'Dettaglio rimborso'}}</p></div><a class="secondary-button" [routerLink]="kind==='emission'?'/rimborsi':'/tornei'">Torna all’elenco</a></div>
 @if(info();as row){<dl class="detail-summary">
  @if(kind==='emission'){<dt>Emesso il</dt><dd>{{timestamp(row.created_at)}}</dd><dt>Operatore</dt><dd>#{{row.user_id}}</dd><dt>Totale emesso</dt><dd>{{money(row.total_cents)}}</dd><dt>Emittente</dt><dd>{{row.options.issuer}}</dd><dt>Data ricevuta</dt><dd>{{dateLabel(row.options.date)}}</dd><dt>Note</dt><dd>{{row.options.notes||'—'}}</dd>}
  @else if(kind==='player'){<dt>Giocatore</dt><dd>{{row.name}}</dd><dt>Tessera</dt><dd>{{row.card||'—'}}</dd><dt>Partecipazioni nella stagione selezionata</dt><dd>{{entries().length}}</dd>}
  @else{<dt>Torneo</dt><dd><a [routerLink]="'/tornei/'+row.tournament_key">{{row.tournament_name}}</a></dd><dt>Data</dt><dd>{{dateLabel(row.tournament_date)}}</dd><dt>Località</dt><dd>{{row.tournament_place}}</dd><dt>Comitato</dt><dd>{{row.committee_label}}</dd>@if(kind==='race'){<dt>Gara</dt><dd>{{row.race_name}}</dd><dt>Quota</dt><dd>{{money(row.price_cents)}}</dd>}<dt>Stato rimborsi</dt><dd>{{portal.tournamentState(row.tournament_key)}}</dd>}
 </dl>
 @if(kind==='emission'){<div class="print-controls"><label>Formato ricevute<select aria-label="Formato ricevute" [ngModel]="printFormat()" (ngModelChange)="printFormat.set($event)"><option value="total">Cumulativa</option><option value="athletes">Per singolo atleta</option><option value="both">Tutto insieme (ZIP)</option></select></label><button class="sync-button" (click)="download()" [disabled]="portal.data.locked()">Stampa ricevute</button></div>}
 @if(kind==='tournament'){<h2>Gare</h2><fr-entity-grid kind="races" [rows]="races()" viewKey="tournament-races" label="gare del torneo" />}
 @if(kind==='emission'){<p class="grid-help">Importo e PDF si riferiscono all’emissione completa; la tabella segue la stagione selezionata.</p>}<h2>Iscrizioni</h2><fr-entity-grid kind="entries" [rows]="entries()" [viewKey]="kind+'-entries'" label="iscrizioni" />
 @if(kind==='player'){<h2>Rimborsi</h2><fr-entity-grid kind="emissions" [rows]="playerEmissions()" viewKey="player-emissions" label="rimborsi del giocatore" />}
 }@else{<p class="empty-state">Nessun dettaglio disponibile nella stagione selezionata. Puoi scegliere un’altra stagione dal menu generale.</p>}
`})
export class PortalDetailComponent {
 readonly portal=inject(PortalService);private readonly route=inject(ActivatedRoute);readonly params=toSignal(this.route.paramMap);
 readonly kind=this.route.snapshot.data['kind'] as 'tournament'|'race'|'player'|'emission';readonly id=computed(()=>this.params()?.get('id')||this.params()?.get('key')||'');
 readonly printFormat=signal<'total'|'athletes'|'both'>('total');
 readonly money=money;readonly dateLabel=dateLabel;readonly timestamp=(date:string)=>new Date(date).toLocaleString('it-IT');
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
 async download(){const e=this.emission();if(!e)return;await this.portal.data.perform(async()=>{const full=await this.portal.emission(e.id);const {downloadRefunds}=await import('./refund-pdf');await downloadRefunds(full.items.map(r=>({...r,reimbursed:false})),{...full.options,format:this.printFormat(),signature:full.options.signature??null,stamp:full.options.stamp??null});});}
}
