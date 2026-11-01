import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { AgGridAngular } from 'ag-grid-angular';
import { PortalService, ReceiptOptions } from './portal.service';
import { AuthService } from './auth.service';
import { EntityGridComponent } from './entity-grid.component';
import { money } from './format';
import { athleteSummary, pendingTotal } from './refund-model';
import { gridTheme, defaultColDef } from './grid';
import { TableView } from './table-preferences';
@Component({selector:'fr-cart',imports:[FormsModule,RouterLink,EntityGridComponent,AgGridAngular],template:`
 <div class="page-heading"><div><h1>Carrello</h1><p class="dashboard-subtitle">Rivedi le iscrizioni e registra l’emissione dei rimborsi.</p></div><a class="secondary-button" routerLink="/iscrizioni">Aggiungi iscrizioni</a></div>
 <p>{{portal.cartRows().length}} iscrizioni nella stagione selezionata · {{portal.cart().length}} totali nel carrello.</p>
 @if(lastEmission()){<p class="notice">Rimborso registrato. <a [routerLink]="'/rimborsi/'+lastEmission()">Apri il dettaglio e riscarica le ricevute</a>.</p>}
 <fr-entity-grid kind="entries" [rows]="portal.cartRows()" viewKey="cart" label="carrello" [cartMode]="true" />
 @if(portal.cartRows().length){
  <div class="recap-totals"><div><span>Iscrizioni</span><strong>{{portal.cartRows().length}}</strong></div><div><span>Totale</span><strong>{{money(total())}}</strong></div></div>
  @if(invalid()){<p class="error-box">Ci sono quote già rimborsate, non più disponibili o senza prezzo. Rimuovile o aggiorna i prezzi prima di emettere.</p>}
  <section class="refund-settings" aria-label="Impostazioni rimborso">
   <label>Società emittente<input name="issuer" [(ngModel)]="options.issuer" maxlength="180"></label>
   <label>Data ricevuta<input type="date" name="date" [(ngModel)]="options.date"></label>
   <label>Formato PDF<select [(ngModel)]="options.format" aria-label="Formato PDF"><option value="total">Ricevuta cumulativa</option><option value="athletes">Separate per atleta (ZIP)</option><option value="both">Cumulativa e separate (ZIP)</option></select></label>
   <label>Ripartizione doppi<select [ngModel]="allocation()" (ngModelChange)="allocation.set($event)" aria-label="Ripartizione doppi"><option value="half">Metà per atleta</option><option value="first">Tutta al primo atleta</option><option value="second">Tutta al secondo atleta</option></select></label>
   <label class="wide-field">Note<input [(ngModel)]="options.notes" maxlength="500"></label>
  </section>
  <h2>Recap per atleta</h2>
  <ag-grid-angular id="cart-athletes-grid" class="athlete-grid" [theme]="theme" [rowData]="athletes()" [columnDefs]="athleteColumns" [defaultColDef]="defaults" [initialState]="view.initialState" (stateUpdated)="view.updated($event)" [pagination]="true" [paginationPageSize]="100" [paginationPageSizeSelector]="[25,50,100]" />
  <p class="grid-help">Emetti registra il rimborso e segna le quote come rimborsate. Il PDF e il dettaglio restano riscaricabili dal registro. Vengono emesse solo le iscrizioni della stagione selezionata; le altre rimangono nel carrello.</p>
  <div class="issue-footer"><span>{{money(total())}}</span><button class="sync-button" [disabled]="portal.data.locked()||invalid()||!options.issuer.trim()||!options.date" (click)="emit()">{{portal.data.busy()?'Emissione…':'Emetti rimborsi'}}</button></div>
 }
`})
export class CartComponent implements OnInit {
 readonly portal=inject(PortalService);private readonly auth=inject(AuthService);readonly money=money;
 readonly allocation=signal<'half'|'first'|'second'>('half');readonly lastEmission=signal<number|null>(null);
 readonly total=computed(()=>pendingTotal(this.portal.cartRows()));
 readonly invalid=computed(()=>this.total()===null||this.portal.cartRows().some(r=>r.reimbursed||(r as any).stale));
 readonly athletes=computed(()=>athleteSummary(this.portal.cartRows(),this.allocation()));
 readonly theme=gridTheme;readonly defaults:any=defaultColDef;readonly view=new TableView('portal-cart-athletes');
 readonly athleteColumns:any[]=[{field:'name',headerName:'Atleta',flex:2,minWidth:220},{field:'card',headerName:'Tessera'},{field:'quotes',headerName:'Iscrizioni'},{field:'pending_cents',headerName:'Da rimborsare',valueFormatter:(p:any)=>money(p.value)}];
 options:ReceiptOptions={issuer:'',date:new Date().toLocaleDateString('en-CA'),notes:'',format:'total',allocation:'half'};
 private requestKey=crypto.randomUUID().replaceAll('-','');
 async ngOnInit(){try{await this.portal.refresh();this.options.issuer=(await this.auth.publicConfig()).society_name;}catch{}}
 async emit(){await this.portal.data.perform(async()=>{
   const rows=this.portal.cartRows();if(!rows.length||this.invalid())throw new Error('Verifica le quote nel carrello.');
   const options={...this.options,issuer:this.options.issuer.trim(),allocation:this.allocation(),signature:await this.portal.signature()};
   // Build files first; a font or PDF failure must not create a server-side emission.
   const {prepareRefundDownload}=await import('./refund-pdf');const save=await prepareRefundDownload(rows,options);
   let emission;try{emission=await this.portal.issue(rows,options,this.requestKey);}catch(error){await this.portal.refresh();await this.portal.data.refresh();throw error;}this.lastEmission.set(emission.id);this.requestKey=crypto.randomUUID().replaceAll('-','');
   save();await this.portal.data.refresh();await this.portal.refresh();this.portal.data.notice.set('Rimborso #'+emission.id+' emesso e registrato.');
 });}
}
