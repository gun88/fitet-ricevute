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
 <div class="page-heading"><div><h1>Bozza rimborso</h1><p class="dashboard-subtitle">Rivedi le iscrizioni e registra l’emissione dei rimborsi.</p></div></div>
 @if(initializing()){<p class="load-note" role="status">Caricamento bozza…</p>}@else{
 <p>{{portal.cartRows().length}} iscrizioni nella stagione selezionata · {{portal.cart().length}} totali nella bozza rimborso.</p>
 @if(lastEmission()){<p class="notice">Rimborso registrato. <a [routerLink]="'/rimborsi/'+lastEmission()">Apri il dettaglio e riscarica le ricevute</a>.</p>}
 <fr-entity-grid kind="entries" [rows]="portal.cartRows()" viewKey="cart" label="bozza rimborso" [cartMode]="true" />
 @if(portal.cartRows().length){
  <div class="recap-totals"><div><span>Iscrizioni</span><strong>{{portal.cartRows().length}}</strong></div><div><span>Giocatori</span><strong>{{athletes().length}}</strong></div><div><span>Totale</span><strong>{{money(total())}}</strong></div></div>
  @if(invalid()){<p class="error-box">Ci sono quote già rimborsate, non più disponibili o senza prezzo. Rimuovile o aggiorna i prezzi prima di emettere.</p>}
  <section class="refund-settings draft-refund-settings" aria-label="Impostazioni rimborso">
   <label>Società<input type="text" name="issuer" [value]="issuer()||'Da configurare in wp-admin'" readonly></label>
   <label>Data ricevuta<input type="date" name="date" [(ngModel)]="options.date"></label>

   <label>Ripartizione doppi<select [ngModel]="allocation()" (ngModelChange)="allocation.set($event)" aria-label="Ripartizione doppi"><option value="half">Metà per atleta</option><option value="first">Tutta al primo atleta</option><option value="second">Tutta al secondo atleta</option></select></label>
   <label class="wide-field">Note generali<textarea [(ngModel)]="options.notes" maxlength="2000" rows="4"></textarea></label>
  </section>
  <h2>Recap per atleta</h2>
  @if(responsive.mobile()){
   <div class="mobile-search-row"><label class="mobile-recap-search">Cerca nel recap<input type="search" [value]="view.search()" (input)="view.setSearch($any($event.target).value)"></label><fr-search-clear (clear)="view.setSearch('')" /></div><fr-mobile-list [rows]="athletes()" [search]="view.search()" label="Recap per atleta" [sort]="athleteSort"><ng-template let-row><article class="mobile-card"><h3><a [routerLink]="['/giocatori',playerKey(row.name,row.card==='—'?null:row.card)]">{{row.name}}</a></h3><dl class="card-fields"><dt>Tessera</dt><dd>{{row.card}}</dd><dt>Iscrizioni</dt><dd>{{row.quotes}}</dd><dt>Da rimborsare</dt><dd>{{money(row.pending_cents)}}</dd></dl></article></ng-template></fr-mobile-list>
  }@else{  <ag-grid-angular id="cart-athletes-grid" class="athlete-grid" [theme]="presentation.theme()" [domLayout]="presentation.layout()" [class.auto-height]="presentation.autoHeight()" [rowData]="athletes()" [columnDefs]="athleteColumns" [defaultColDef]="defaults" [initialState]="view.initialState" (stateUpdated)="!responsive.mobile() && view.updated($event)" (gridReady)="recapApi=$event.api;view.restore($event.api)" [pagination]="true" [paginationPageSize]="100" [paginationPageSizeSelector]="[25,50,100]" /> }

  <p class="grid-help">Emetti registra il rimborso e segna le quote come rimborsate. Dopo l’emissione scegli come stampare le ricevute dal dettaglio del rimborso. Vengono emesse solo le iscrizioni della stagione selezionata; le altre rimangono nella bozza rimborso.</p>
  <div class="issue-footer"><span>{{money(total())}}</span><button class="sync-button" [disabled]="portal.data.locked()||invalid()||!issuer().trim()||!options.date" (click)="emit()">{{portal.data.busy()?'Emissione…':'Emetti rimborsi'}}</button></div>
 }
 }
`})
export class CartComponent implements OnInit, OnDestroy {
 readonly presentation=inject(GridPresentation);
 readonly portal=inject(PortalService);private readonly auth=inject(AuthService);private readonly router=inject(Router);readonly money=money;
 readonly responsive=inject(ResponsiveService);readonly playerKey=playerKey;readonly athleteSort=(a:any,b:any)=>a.name.localeCompare(b.name,'it');
 readonly initializing=signal(true);readonly issuer=signal('');
 readonly allocation=signal<'half'|'first'|'second'>('half');readonly lastEmission=signal<number|null>(null);
 readonly total=computed(()=>pendingTotal(this.portal.cartRows()));
 readonly invalid=computed(()=>this.total()===null||this.portal.cartRows().some(r=>r.reimbursed||(r as any).stale));
 readonly athletes=computed(()=>athleteSummary(this.portal.cartRows(),this.allocation()));
 readonly theme=gridTheme;readonly defaults:any=defaultColDef;readonly view=new TableView('portal-cart-athletes');
 readonly athleteColumns:any[]=[{field:'name',headerName:'Atleta',flex:2,minWidth:220},{field:'card',headerName:'Tessera'},{field:'quotes',headerName:'Iscrizioni'},{field:'pending_cents',headerName:'Da rimborsare',valueFormatter:(p:any)=>money(p.value)}];
 options:ReceiptOptions={issuer:'',date:new Date().toLocaleDateString('en-CA'),notes:'',format:'total',allocation:'half'};
 recapApi?:import('ag-grid-community').GridApi;private unwatch=()=>{};ngOnDestroy(){this.unwatch();}
 private requestKey=crypto.randomUUID().replaceAll('-','');
 async ngOnInit(){this.unwatch=this.responsive.preserve(()=>this.recapApi,this.view);try{const [config]=await Promise.all([this.auth.publicConfig(),this.portal.refresh()]);this.issuer.set(config.society_name);}catch(error){this.portal.data.error.set(errorMessage(error));}finally{this.initializing.set(false);}}
 async emit(){await this.portal.data.perform(async()=>{
   const rows=this.portal.cartRows();if(!rows.length||this.invalid())throw new Error('Verifica le quote nella bozza rimborso.');
   const options={...this.options,issuer:this.issuer().trim(),allocation:this.allocation()};
   let emission;try{emission=await this.portal.issue(rows,options,this.requestKey);}catch(error){await this.portal.refresh();await this.portal.data.refresh();throw error;}
   this.lastEmission.set(emission.id);this.requestKey=crypto.randomUUID().replaceAll('-','');
   await this.portal.data.refresh();await this.portal.refresh();await this.router.navigateByUrl('/rimborsi/'+emission.id);this.portal.data.notice.set('Rimborso #'+emission.id+' emesso e registrato.');
 });}
}
