import { settlement, Adjustment } from './refund-settlement';
import { ActivatedRoute } from '@angular/router';
import { GridPresentation } from './grid-presentation.service';
import { ClearSearchComponent } from './clear-search.component';
import { playerKey } from './players-model';
import { ResponsiveService } from './responsive.service';
import { MobileListComponent } from './mobile-list.component';
import { Component, OnInit, OnDestroy, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AgGridAngular } from 'ag-grid-angular';
import { PortalService, ReceiptOptions } from './portal.service';
import { AuthService, errorMessage } from './auth.service';
import { EntityGridComponent } from './entity-grid.component';
import { money } from './format';
import { athleteSummary, pendingTotal } from './refund-model';
import { gridTheme, defaultColDef } from './grid';
import { TableView } from './table-preferences';
@Component({selector:'fr-cart',imports:[FormsModule,RouterLink,EntityGridComponent,AgGridAngular,MobileListComponent,ClearSearchComponent],template:`
 <div class="page-heading"><div><h1>{{editingId?'Modifica rimborso #'+editingId:'Bozza rimborso'}}</h1><p class="dashboard-subtitle">Rivedi le iscrizioni e registra l’emissione dei rimborsi.</p></div></div>
 @if(initializing()){<p class="load-note" role="status">Caricamento bozza…</p>}@else{
 <p>{{rows().length}} iscrizioni nella stagione selezionata · {{portal.cart().length}} totali nella bozza rimborso.</p>
 @if(lastEmission()){<p class="notice">Rimborso registrato. <a [routerLink]="'/rimborsi/'+lastEmission()">Apri il dettaglio e riscarica le ricevute</a>.</p>}
 <fr-entity-grid kind="entries" [rows]="rows()" viewKey="cart" label="bozza rimborso" [cartMode]="true" [onRemove]="editingId?removeEdited:undefined" [onNote]="editingId?noteEdited:undefined" />
 @if(rows().length||editingId){
  <div class="recap-totals"><div><span>Iscrizioni</span><strong>{{rows().length}}</strong></div><div><span>Giocatori</span><strong>{{athletes().length}}</strong></div><div><span>Totale</span><strong>{{money(total())}}</strong></div></div>
  @if(invalid()){<p class="error-box">Ci sono quote già rimborsate, non più disponibili o senza prezzo. Rimuovile o aggiorna i prezzi prima di emettere.</p>}
  <section class="refund-settings draft-refund-settings" aria-label="Impostazioni rimborso">
   <label>Società<input type="text" name="issuer" [value]="issuer()||'Da configurare in wp-admin'" readonly></label>
   <label>Data ricevuta<input type="date" name="date" [(ngModel)]="options.date"></label>

   <label>Ripartizione doppi<select [ngModel]="allocation()" (ngModelChange)="allocation.set($event)" aria-label="Ripartizione doppi"><option value="half">Metà per atleta</option><option value="first">Tutta al primo atleta</option><option value="second">Tutta al secondo atleta</option></select></label>
   <label>Modalità rimborso<select [(ngModel)]="options.payment_method"><option value="transfer">Bonifico</option><option value="cash">Contanti</option></select></label>
   <label class="wide-field">Note generali<textarea [(ngModel)]="options.notes" maxlength="2000" rows="4"></textarea></label>
  </section>
  @if(editingId){<section class="refund-settings"><label class="wide-field">Aggiungi iscrizione<select #entry><option value="">Seleziona iscrizione</option>@for(row of available();track row.id){<option [value]="row.id">{{row.athlete1_name}} · {{row.tournament_name}} · {{row.race_name}}</option>}</select></label><button class="secondary-button" type="button" [disabled]="!entry.value" (click)="addEdited(Number(entry.value));entry.value=''">Aggiungi</button></section>}
  <section class="refund-settings"><h2 class="wide-field">Quote e rettifiche</h2>
   @for(row of rows();track row.id){<label class="wide-field">{{row.athlete1_name}} · {{row.race_name}}<select [ngModel]="options.line_modes?.[row.id]||'reimburse'" (ngModelChange)="setMode(row.id,$event)"><option value="reimburse">Quota da rimborsare</option><option value="deduct">Già pagata dalla società: detrai dal contributo</option></select></label>}
   <div class="wide-field contribution-controls"><label>Beneficiario contributo<select #contributionPerson>@for(person of baseAthletes();track person.key){<option [value]="person.key">{{person.name}} ({{person.card}})</option>}</select></label><label>Contributo complessivo (€)<input #contributionAmount type="number" min="0.01" step="0.01" placeholder="Es. 80"></label><button class="secondary-button" type="button" (click)="contribute(contributionPerson.value,contributionAmount.value)">Imposta contributo complessivo</button><p class="grid-help">Aggiunge il contributo e imposta come detrazioni le iscrizioni di questo atleta. Per un doppio il trattamento riguarda l’intera coppia.</p></div>
   <div class="wide-field"><button class="secondary-button" type="button" (click)="addAdjustment()">Aggiungi contributo / trattenuta</button></div>
   @for(a of options.adjustments||[];track $index){<div class="adjustment-row wide-field"><label>Beneficiario<select [(ngModel)]="a.beneficiary" (ngModelChange)="touch()">@for(person of baseAthletes();track person.key){<option [value]="person.key">{{person.name}} ({{person.card}})</option>}</select></label><label>Causale<input [(ngModel)]="a.reason" maxlength="500" (ngModelChange)="touch()"></label><label>Importo (+/− €)<input type="number" step="0.01" [ngModel]="a.cents/100" (ngModelChange)="a.cents=Math.round(Number($event)*100);touch()"></label><button class="text-button" (click)="removeAdjustment($index)">Rimuovi</button></div>}
  </section>
  <h2>Riepilogo rimborsi</h2>
  @if(responsive.mobile()){
   <div class="mobile-search-row"><label class="mobile-recap-search">Cerca nel recap<input type="search" [value]="view.search()" (input)="view.setSearch($any($event.target).value)"></label><fr-search-clear (clear)="view.setSearch('')" /></div><fr-mobile-list [rows]="athletes()" [search]="view.search()" label="Recap per atleta" [sort]="athleteSort"><ng-template let-row><article class="mobile-card"><h3><a [routerLink]="['/giocatori',playerKey(row.name,row.card==='—'?null:row.card)]">{{row.name}} ({{row.card}})</a></h3><dl class="card-fields"><dt>Iscrizioni</dt><dd>{{row.quotes}}</dd><dt>Da rimborsare</dt><dd>{{money(row.pending_cents)}}</dd></dl></article></ng-template></fr-mobile-list>
  }@else{  <ag-grid-angular id="cart-athletes-grid" class="athlete-grid" [theme]="presentation.theme()" [domLayout]="presentation.layout()" [class.auto-height]="presentation.autoHeight()" [rowData]="athletes()" [columnDefs]="athleteColumns" [defaultColDef]="defaults" [autoSizeStrategy]="view.initialState?.columnSizing?undefined:autoSize" [initialState]="view.initialState" (stateUpdated)="!responsive.mobile() && view.updated($event)" (gridReady)="recapApi=$event.api;view.restore($event.api)" [pagination]="true" [paginationPageSize]="100" [paginationPageSizeSelector]="[25,50,100]" /> }

  <p class="grid-help">Emetti registra il rimborso e segna le quote come rimborsate. Dopo l’emissione scegli come stampare le ricevute dal dettaglio del rimborso. Vengono emesse solo le iscrizioni della stagione selezionata; le altre rimangono nella bozza rimborso.</p>
  @if(editingId){<a class="secondary-button" [routerLink]="'/rimborsi/'+editingId">Annulla modifiche</a>}
  @if(total()){<p>Totale {{options.payment_method==='cash'?'contanti':'bonifico'}}: {{money(total())}}</p>}<div class="issue-footer"><span>{{money(total())}}</span><button class="sync-button" [disabled]="portal.data.locked()||invalid()||!issuer().trim()||!options.date" (click)="emit()">{{portal.data.busy()?'Salvataggio…':editingId?'Salva modifiche':'Emetti rimborsi'}}</button></div>
 }
 }
`})
export class CartComponent implements OnInit, OnDestroy {
 readonly route=inject(ActivatedRoute);readonly editingId=Number(this.route.snapshot.paramMap.get('id'))||0;private editingRevision=0;
 readonly editedRows=signal<import('./data.service').Row[]>([]);readonly rows=computed(()=>this.editingId?this.editedRows():this.portal.cartRows());
 readonly available=computed(()=>this.portal.entries().filter(r=>!r.reimbursed&&!this.editedRows().some(e=>e.id===r.id)));
 readonly Number=Number;readonly Math=Math;
 readonly removeEdited=(row:any)=>{this.editedRows.update(rows=>rows.filter(r=>r.id!==row.id));};
 readonly noteEdited=(row:any,notes:string)=>{this.editedRows.update(rows=>rows.map(r=>r.id===row.id?{...r,refund_notes:notes}:r));};
 addEdited(id:number){const row=this.available().find(r=>r.id===id);if(row)this.editedRows.update(rows=>[...rows,{...row}]);}
 touch(){this.revision.update(v=>v+1);}
 setMode(id:number,mode:'reimburse'|'deduct'){this.options.line_modes={...this.options.line_modes,[id]:mode};this.touch();}
 contribute(key:string,value:string){const cents=Math.round(Number(value)*100),person=this.baseAthletes().find(p=>p.key===key);if(!person||!Number.isSafeInteger(cents)||cents<=0){this.portal.data.error.set('Indica beneficiario e contributo maggiore di zero.');return;}const modes={...this.options.line_modes};for(const line of person.lines)modes[line.entry.id]='deduct';this.options.line_modes=modes;this.options.adjustments=[...(this.options.adjustments||[]),{beneficiary:key,reason:'Contributo complessivo',cents}];this.touch();}
 addAdjustment(){const beneficiary=this.baseAthletes()[0]?.key;if(beneficiary){this.options.adjustments=[...(this.options.adjustments||[]),{beneficiary,reason:'',cents:0}];this.touch();}}
 removeAdjustment(index:number){this.options.adjustments=this.options.adjustments?.filter((_,i)=>i!==index);this.touch();}
 readonly presentation=inject(GridPresentation);
 readonly portal=inject(PortalService);private readonly auth=inject(AuthService);private readonly router=inject(Router);readonly money=money;
 readonly responsive=inject(ResponsiveService);readonly playerKey=playerKey;readonly athleteSort=(a:any,b:any)=>a.name.localeCompare(b.name,'it');
 readonly initializing=signal(true);readonly issuer=signal('');
 readonly allocation=signal<'half'|'first'|'second'>('half');readonly lastEmission=signal<number|null>(null);
 readonly revision=signal(0);readonly total=computed(()=>{this.revision();const people=this.athletes();return people.some(p=>p.pending_cents===null)?null:people.reduce((n,p)=>n+p.pending_cents!,0);});
 readonly invalid=computed(()=>!this.rows().length||this.total()===null||this.athletes().some(p=>(p.pending_cents??0)<0)||this.rows().some(r=>(!this.editingId&&r.reimbursed)||(r as any).stale));
 readonly baseAthletes=computed(()=>athleteSummary(this.rows().map(r=>({...r,reimbursed:false})),this.allocation()));
 readonly athletes=computed(()=>{this.revision();return settlement(this.rows(),{...this.options,allocation:this.allocation()});});
 readonly theme=gridTheme;readonly defaults:any=defaultColDef;readonly view=new TableView('portal-cart-athletes');
 readonly autoSize:any={type:'fitCellContents',defaultMaxWidth:420};
 readonly athleteColumns:any[]=[{field:'name',headerName:'Atleta',flex:2,minWidth:220,valueFormatter:(p:any)=>p.data.name+' ('+p.data.card+')'},{field:'quotes',headerName:'Iscrizioni'},{field:'pending_cents',headerName:'Da rimborsare',valueFormatter:(p:any)=>money(p.value)}];
 options:ReceiptOptions={issuer:'',date:new Date().toLocaleDateString('en-CA'),notes:'',format:'total',allocation:'half'};
 recapApi?:import('ag-grid-community').GridApi;private unwatch=()=>{};ngOnDestroy(){this.unwatch();}
 private requestKey=crypto.randomUUID().replaceAll('-','');
 async ngOnInit(){this.unwatch=this.responsive.preserve(()=>this.recapApi,this.view);try{const [config]=await Promise.all([this.auth.publicConfig(),this.portal.refresh()]);this.issuer.set(config.society_name);this.options.payment_method=this.auth.gridPreferences().payment_method||'transfer';if(this.editingId){const e=await this.portal.emission(this.editingId);this.editingRevision=e.options.revision||0;this.options={...e.options};this.allocation.set(e.options.allocation);this.issuer.set(e.options.issuer);this.editedRows.set(e.items.map(r=>({...r,price_cents:r.paid_cents??r.price_cents})));}}catch(error){this.portal.data.error.set(errorMessage(error));}finally{this.initializing.set(false);}}
 async emit(){await this.portal.data.perform(async()=>{
   const rows=this.rows();if(!rows.length||this.invalid())throw new Error('Verifica le quote nella bozza rimborso.');
   const options={...this.options,issuer:this.issuer().trim(),allocation:this.allocation()};
   let emission;try{emission=this.editingId?await this.portal.revise(this.editingId,rows,options,this.editingRevision):await this.portal.issue(rows,options,this.requestKey);}catch(error){await this.portal.refresh();await this.portal.data.refresh();throw error;}
   this.lastEmission.set(emission.id);this.requestKey=crypto.randomUUID().replaceAll('-','');
   await this.portal.data.refresh();await this.portal.refresh();await this.router.navigateByUrl('/rimborsi/'+emission.id);this.portal.data.notice.set('Rimborso #'+emission.id+' emesso e registrato.');
 });}
}
