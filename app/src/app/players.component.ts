import { GridSizingDirective } from './grid-sizing.directive';
import { GridPresentation } from './grid-presentation.service';
import { ClearSearchComponent } from './clear-search.component';
import { ResponsiveService } from './responsive.service';
import { MobileListComponent } from './mobile-list.component';
import { RouterLink } from '@angular/router';
import { PortalService } from './portal.service';
import { Component, OnDestroy, computed, inject } from '@angular/core';
import { AgGridAngular } from 'ag-grid-angular';
import { ColDef, GridApi } from 'ag-grid-community';
import { DataService, dateLabel } from './data.service';
import { gridTheme } from './grid';
import { Player, playersFor } from './players-model';
import { PlayerLinkComponent } from './player-links.component';
import { TableView } from './table-preferences';

@Component({ selector:'fr-players', imports:[GridSizingDirective,AgGridAngular,MobileListComponent,RouterLink,ClearSearchComponent], template:`
  <div class="page-heading"><div><h1>Giocatori</h1><p class="dashboard-subtitle">Apri la scheda di un atleta per consultare tornei, quote e rimborsi.</p></div></div>
  <div class="grid-toolbar"><label class="grid-search">Cerca giocatore<input type="search" aria-label="Cerca giocatore" maxlength="1000" [value]="view.search()" (input)="view.setSearch($any($event.target).value)" placeholder="Nome o tessera…"></label>@if(responsive.mobile()){<fr-search-clear (clear)="view.setSearch('')" />}@else{<button class="secondary-button" (click)="view.reset(api)">Azzera filtri e ordine</button>}</div>
  @if(responsive.mobile()){
   <fr-mobile-list [rows]="players()" [search]="view.search()" [sort]="playerSort" label="Giocatori"><ng-template let-row><article class="mobile-card"><h3><a [routerLink]="['/giocatori',row.key]">{{row.name}} ({{row.card||'—'}})</a></h3><dl class="card-fields"><dt>Tornei</dt><dd>{{row.tournaments}}</dd><dt>Iscrizioni</dt><dd>{{row.entries}}</dd><dt>Quote rimborsate</dt><dd>{{row.reimbursed}}</dd><dt>Ultima partecipazione</dt><dd>{{dateLabel(row.last_date)}}</dd></dl></article></ng-template></fr-mobile-list>
  }@else{  <ag-grid-angular id="players-grid" class="main-grid" [theme]="presentation.theme()" [domLayout]="presentation.layout()" [class.auto-height]="presentation.autoHeight()" [rowData]="players()" [columnDefs]="columns" [defaultColDef]="defaults" [getRowId]="rowId" [pagination]="true" [paginationPageSize]="100" [paginationPageSizeSelector]="[25,50,100]" [quickFilterText]="view.search()" [frGridSizing]="view" [initialState]="view.initialState" (stateUpdated)="!responsive.mobile() && view.updated($event)" (gridReady)="api = $event.api;view.restore($event.api)" /> }

  <p class="grid-help">{{ players().length }} giocatori nelle ricevute disponibili. Filtri e ordinamento vengono conservati in questo browser per il tuo account.</p>
` })
export class PlayersComponent implements OnDestroy {
 readonly presentation=inject(GridPresentation);
 private unwatch=()=>{};constructor(){this.unwatch=this.responsive.preserve(()=>this.api,this.view);}
 ngOnDestroy(){this.unwatch();}
  readonly responsive=inject(ResponsiveService);readonly dateLabel=dateLabel;readonly playerSort=(a:any,b:any)=>a.name.localeCompare(b.name,'it');
  readonly data = inject(DataService); readonly theme = gridTheme; readonly view = new TableView('players');
  readonly portal = inject(PortalService);
  readonly players = computed(() => playersFor(this.portal.entries()));
  readonly rowId = (params:{data:Player}) => params.data.key;
  readonly defaults = { sortable:true,filter:true,floatingFilter:false,resizable:true,minWidth:64 };
  api?: GridApi<Player>;
  readonly columns: ColDef<Player>[] = [
    { field:'name',headerName:'Giocatore',valueGetter:p=>p.data!.name+' ('+p.data!.card+')',minWidth:64,sort:'asc',cellRenderer:PlayerLinkComponent },

    { field:'tournaments',headerName:'Tornei',width:130,filter:'agNumberColumnFilter' },
    { field:'entries',headerName:'Iscrizioni',width:155,filter:'agNumberColumnFilter' },
    { field:'reimbursed',headerName:'Quote rimborsate',width:180,filter:'agNumberColumnFilter' },
    { field:'last_date',headerName:'Ultima partecipazione',width:195,valueFormatter:p=>dateLabel(p.value) },
  ];
}
