import { Component, Input, signal, computed, inject, OnDestroy } from '@angular/core';
import { RouterLink } from '@angular/router';
import { AgGridAngular } from 'ag-grid-angular';
import { GridApi } from 'ag-grid-community';
import { GridSizingDirective } from './grid-sizing.directive';
import { GridPresentation } from './grid-presentation.service';
import { defaultColDef } from './grid';
import { TableView } from './table-preferences';
import { ResponsiveService } from './responsive.service';
import { MobileListComponent } from './mobile-list.component';
import { ClearSearchComponent } from './clear-search.component';
import { settlement, paymentLabel } from './refund-settlement';
import { money } from './format';
@Component({selector:'fr-refund-recap',imports:[AgGridAngular,GridSizingDirective,RouterLink,MobileListComponent,ClearSearchComponent],template:`
 <div class="grid-toolbar"><label class="grid-search">Cerca nel riepilogo<input type="search" aria-label="Cerca nel riepilogo" [value]="view.search()" (input)="view.setSearch($any($event.target).value)"></label>@if(responsive.mobile()){<fr-search-clear (clear)="view.setSearch('')" />}@else{<button class="text-button" (click)="view.reset(api)">Azzera filtri e ordine</button>}</div>
 @if(responsive.mobile()){<fr-mobile-list [rows]="rows()" [search]="view.search()" label="Riepilogo rimborsi" [text]="searchText"><ng-template let-row><article class="mobile-card"><h3><a [routerLink]="['/giocatori',row.key]">{{row.name}} ({{row.card}})</a></h3><dl class="card-fields"><dt>Iscrizioni</dt><dd>{{row.quotes}}</dd><dt>Da consegnare</dt><dd>{{money(row.pending_cents)}}</dd><dt>Modalità</dt><dd>{{paymentLabel(row.payment_method)}}</dd></dl></article></ng-template></fr-mobile-list>}
 @else{<ag-grid-angular id="emission-recap-grid" class="athlete-grid" [theme]="presentation.theme()" [domLayout]="presentation.layout()" [class.auto-height]="presentation.autoHeight()" [rowData]="rows()" [columnDefs]="columns" [defaultColDef]="defaults" [getRowId]="rowId" [quickFilterText]="view.search()" [frGridSizing]="view" [initialState]="view.initialState" (gridReady)="api=$event.api;view.restore($event.api)" (stateUpdated)="view.updated($event)" [pagination]="true" [paginationPageSize]="100" [paginationPageSizeSelector]="[25,50,100]" />}
`})
export class RefundRecapComponent implements OnDestroy {
 private readonly source=signal<any>(null);@Input() set emission(value:any){this.source.set(value);}
 readonly rows=computed(()=>{const e=this.source();return e?settlement(e.items,e.options):[];});readonly presentation=inject(GridPresentation);readonly responsive=inject(ResponsiveService);readonly view=new TableView('emission-settlement');readonly defaults:any=defaultColDef;readonly money=money;readonly paymentLabel=paymentLabel;readonly rowId=(p:any)=>p.data.key;
 readonly searchText=(p:any)=>[p.name,p.card,paymentLabel(p.payment_method),money(p.pending_cents)].join(' ');
 readonly columns:any[]=[{field:'name',headerName:'Atleta',valueGetter:(p:any)=>p.data.name+' ('+p.data.card+')'},{field:'quotes',headerName:'Iscrizioni',filter:'agNumberColumnFilter'},{field:'pending_cents',headerName:'Da consegnare',valueFormatter:(p:any)=>money(p.value),filter:'agNumberColumnFilter'},{field:'payment_method',headerName:'Modalità',valueGetter:(p:any)=>paymentLabel(p.data.payment_method)}];
 api?:GridApi;private unwatch=()=>{};constructor(){this.unwatch=this.responsive.preserve(()=>this.api,this.view);}ngOnDestroy(){this.unwatch();}
}
