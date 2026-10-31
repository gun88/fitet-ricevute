import { Component, computed, inject } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { AgGridAngular } from 'ag-grid-angular';
import { ColDef, GridApi } from 'ag-grid-community';
import { DataService, Row, dateLabel, money } from './data.service';
import { scopeLabel } from './format';
import { GridActionComponent, defaultColDef, getRowId, gridTheme } from './grid';
import { inSeason, playerKey, playersFor, seasonFor, seasonsFor } from './players-model';
import { TableView } from './table-preferences';

@Component({ selector:'fr-player',imports:[AgGridAngular,RouterLink],template:`
  <div class="page-heading"><div><span class="eyebrow">SCHEDA GIOCATORE</span><h1>{{ player()?.name || 'Giocatore' }}</h1><p class="dashboard-subtitle">Tessera {{ player()?.card || '—' }} · Partecipazioni ai tornei e stato dei rimborsi.</p></div><a class="secondary-button" routerLink="/giocatori">Tutti i giocatori</a></div>
  @if (!data.loading() && !player()) { <p class="empty-state">Giocatore non presente nelle ricevute disponibili.</p> }
  @if (player()) {
    <div class="grid-toolbar player-filters">
      <label>Annata agonistica<select aria-label="Annata agonistica" [value]="view.season()" (change)="view.setSeason($any($event.target).value)"><option value="latest">Ultima disponibile ({{ latest() || '—' }})</option><option value="all">Tutte le annate</option>@for (season of seasons(); track season) { <option [value]="season">{{ season }}</option> }</select></label>
      <label>Cerca nelle partecipazioni<input aria-label="Cerca partecipazioni" type="search" maxlength="1000" [value]="view.search()" (input)="view.setSearch($any($event.target).value)" placeholder="Torneo, gara, stato…"></label>
      <button class="secondary-button" (click)="reset()">Azzera filtri e ordine</button>
    </div>
    <div class="recap-totals player-totals"><div><span>Tornei nell’annata scelta</span><strong>{{ tournaments() }}</strong></div><div><span>Partecipazioni</span><strong>{{ rows().length }}</strong></div><div><span>Quote rimborsate</span><strong>{{ paid() }}</strong></div><div><span>Da rimborsare</span><strong>{{ rows().length - paid() }}</strong></div></div>
    <ag-grid-angular id="player-grid" class="main-grid" [theme]="theme" [rowData]="rows()" [columnDefs]="columns" [defaultColDef]="defaults" [getRowId]="rowId" [pagination]="true" [paginationPageSize]="100" [paginationPageSizeSelector]="[25,50,100]" [quickFilterText]="view.search()" [initialState]="view.initialState" (stateUpdated)="view.updated($event)" (gridReady)="api = $event.api" />
    <p class="grid-help">Ogni riga è una partecipazione a una gara. Le quote dei doppi sono per coppia e lo stato riguarda l’intera iscrizione. I contatori si riferiscono all’annata scelta; i filtri della griglia restringono le righe mostrate. Annata, filtri e ordinamento restano salvati nel browser.</p>
  }
` })
export class PlayerComponent {
  readonly data = inject(DataService); private readonly route = inject(ActivatedRoute);
  private readonly params = toSignal(this.route.paramMap,{initialValue:this.route.snapshot.paramMap});
  readonly view = new TableView('player-history'); readonly theme = gridTheme; readonly defaults = defaultColDef; readonly rowId = getRowId;
  readonly player = computed(()=>playersFor(this.data.entries()).find(player=>player.key===this.params().get('key')));
  readonly seasons = computed(()=>seasonsFor(this.data.entries()));
  readonly latest = computed(()=>this.seasons()[0] ?? '');
  readonly rows = computed(()=>inSeason(this.player()?.rows ?? [],this.view.season(),this.latest()));
  readonly tournaments = computed(()=>new Set(this.rows().map(row=>row.society_code+':'+row.tournament_key)).size);
  readonly paid = computed(()=>this.rows().filter(row=>row.reimbursed).length);
  api?: GridApi<Row>;
  readonly columns: ColDef<Row>[] = [
    {field:'tournament_date',headerName:'Data',width:130,sort:'desc',valueFormatter:p=>dateLabel(p.value)},
    {colId:'season',headerName:'Annata',width:145,valueGetter:p=>p.data?seasonFor(p.data.tournament_date):''},
    {field:'tournament_name',headerName:'Torneo',width:260},
    {field:'race_name',headerName:'Gara',width:230},
    {field:'tournament_scope',headerName:'Ambito torneo',width:165,valueGetter:p=>p.data?scopeLabel(p.data):''},
    {field:'committee_label',headerName:'Comitato',width:185},
    {field:'tournament_place',headerName:'Località',width:180},
    {colId:'partner',headerName:'Compagno di doppio',width:230,valueGetter:p=>{
      if (!p.data?.athlete2_name) return '—';
      return this.player()?.key===playerKey(p.data.athlete1_name,p.data.athlete1_card) ? p.data.athlete2_name : p.data.athlete1_name;
    }},
    {field:'price_cents',headerName:'Quota iscrizione (€)',width:185,filter:'agNumberColumnFilter',valueGetter:p=>p.data?.price_cents==null?null:p.data.price_cents/100,valueFormatter:p=>p.value==null?'Da definire':money(Math.round(p.value*100))},
    {colId:'status',headerName:'Stato rimborso',width:170,valueGetter:p=>p.data?.reimbursed?'Rimborsata':'Da rimborsare'},
    {field:'paid_cents',headerName:'Importo rimborsato',width:190,filter:'agNumberColumnFilter',valueGetter:p=>p.data?.paid_cents==null?null:p.data.paid_cents/100,valueFormatter:p=>p.value==null?'—':money(Math.round(p.value*100))},
    {field:'refund_updated_at',headerName:'Aggiornamento rimborso',width:210},
    {colId:'refund',headerName:'Aggiorna stato',width:205,filter:false,sortable:false,cellRenderer:GridActionComponent,cellRendererParams:{label:(row:Row)=>row.reimbursed?'Segna da rimborsare':'Segna rimborsata',disabled:()=>this.data.locked(),action:(row:Row)=>this.data.mark([row],!row.reimbursed)}},
  ];
  reset():void { this.view.setSeason('latest'); this.view.reset(this.api); }
}
