import { PortalService } from './portal.service';
import { Component, computed, inject } from '@angular/core';
import { AgGridAngular } from 'ag-grid-angular';
import { ColDef, GridApi } from 'ag-grid-community';
import { DataService, dateLabel } from './data.service';
import { gridTheme } from './grid';
import { Player, playersFor } from './players-model';
import { PlayerLinkComponent } from './player-links.component';
import { TableView } from './table-preferences';

@Component({ selector:'fr-players', imports:[AgGridAngular], template:`
  <div class="page-heading"><div><span class="eyebrow">04 · GIOCATORI</span><h1>Giocatori</h1><p class="dashboard-subtitle">Apri la scheda di un atleta per consultare tornei, quote e rimborsi.</p></div></div>
  <div class="grid-toolbar"><label>Cerca giocatore<input type="search" aria-label="Cerca giocatore" maxlength="1000" [value]="view.search()" (input)="view.setSearch($any($event.target).value)" placeholder="Nome o tessera…"></label><button class="secondary-button" (click)="view.reset(api)">Azzera filtri e ordine</button></div>
  <ag-grid-angular id="players-grid" class="main-grid" [theme]="theme" [rowData]="players()" [columnDefs]="columns" [defaultColDef]="defaults" [getRowId]="rowId" [pagination]="true" [paginationPageSize]="100" [paginationPageSizeSelector]="[25,50,100]" [quickFilterText]="view.search()" [initialState]="view.initialState" (stateUpdated)="view.updated($event)" (gridReady)="api = $event.api" />
  <p class="grid-help">{{ players().length }} giocatori nelle ricevute disponibili. Filtri e ordinamento vengono conservati in questo browser per il tuo account.</p>
` })
export class PlayersComponent {
  readonly data = inject(DataService); readonly theme = gridTheme; readonly view = new TableView('players');
  readonly portal = inject(PortalService);
  readonly players = computed(() => playersFor(this.portal.entries()));
  readonly rowId = (params:{data:Player}) => params.data.key;
  readonly defaults = { sortable:true,filter:true,floatingFilter:true,resizable:true,minWidth:140 };
  api?: GridApi<Player>;
  readonly columns: ColDef<Player>[] = [
    { field:'name',headerName:'Giocatore',minWidth:240,flex:2,sort:'asc',cellRenderer:PlayerLinkComponent },
    { field:'card',headerName:'Tessera',width:140 },
    { field:'tournaments',headerName:'Tornei',width:130,filter:'agNumberColumnFilter' },
    { field:'entries',headerName:'Partecipazioni',width:155,filter:'agNumberColumnFilter' },
    { field:'reimbursed',headerName:'Quote rimborsate',width:180,filter:'agNumberColumnFilter' },
    { field:'last_date',headerName:'Ultima partecipazione',width:195,valueFormatter:p=>dateLabel(p.value) },
  ];
}
