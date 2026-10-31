import { Component, inject } from '@angular/core';
import { AgGridAngular } from 'ag-grid-angular';
import { CellEditRequestEvent, ColDef, GridApi } from 'ag-grid-community';
import { DataService, Row, dateLabel, money, originLabel } from './data.service';
import { scopeLabel } from './format';
import { TableView } from './table-preferences';
import { GridActionComponent, defaultColDef, getRowId, gridTheme } from './grid';

@Component({ selector: 'fr-prices', imports: [AgGridAngular], template: `
  <div class="page-heading"><div><span class="eyebrow">01 · TORNEI E GARE</span><h1>Prezzi tornei</h1><p class="dashboard-subtitle">Modifica le quote e confronta il risultato con le ricevute FITeT.</p></div><div class="sync-controls"><button class="sync-button" (click)="data.synchronize()" [disabled]="data.locked()">{{ data.syncing() ? 'Sincronizzazione…' : 'Sincronizza ricevute' }}</button><span class="last-sync" aria-live="polite">Ultima sincronizzazione: @if (data.lastSyncAt()) { <time [attr.datetime]="data.lastSyncAt()">{{ data.lastSyncLabel() }}</time> } @else { Mai effettuata }</span></div></div>
  <div class="grid-toolbar"><label>Cerca in tutte le colonne<input type="search" aria-label="Cerca prezzi" [value]="view.search()" maxlength="1000" (input)="view.setSearch($any($event.target).value)" placeholder="Torneo, gara, località…"></label><button class="secondary-button" (click)="view.reset(api)">Azzera filtri e ordine</button><span>{{ data.races().length }} gare · Tocca o fai clic sulla quota per modificarla.<br>Invio salva; cella vuota ripristina il prezzo calcolato.</span></div>
  <ag-grid-angular id="prices-grid" class="main-grid" [theme]="theme" [rowData]="data.races()" [columnDefs]="columns" [defaultColDef]="defaults" [getRowId]="rowId" [pagination]="true" [paginationPageSize]="25" [paginationPageSizeSelector]="[25,50,100]" [quickFilterText]="view.search()" [initialState]="view.initialState" (stateUpdated)="view.updated($event)" (gridReady)="api = $event.api" [readOnlyEdit]="true" [singleClickEdit]="true" [stopEditingWhenCellsLoseFocus]="true" (cellEditRequest)="edit($event)" [overlayNoRowsTemplate]="empty" />
  <p class="grid-help">La quota è per iscrizione; nel doppio è per coppia. Il totale PDF e il controllo si riferiscono all’intera ricevuta e si ripetono sulle sue gare. Lo scostamento è totale delle quote meno totale PDF.</p>
` })
export class PricesComponent {
  readonly data = inject(DataService);
  readonly theme = gridTheme; readonly defaults = defaultColDef; readonly rowId = getRowId;
  readonly empty = '<span>Nessuna gara disponibile. Sincronizza le ricevute o modifica i filtri.</span>';
  readonly view = new TableView('prices'); api?: GridApi<Row>;
  readonly columns: ColDef<Row>[] = [
    { field: 'tournament_date', headerName: 'Data', width: 130, valueFormatter: p => dateLabel(p.value), sort: 'desc' },
    { field: 'tournament_name', headerName: 'Torneo', width: 260 },
    { field: 'tournament_scope', headerName: 'Ambito torneo', width: 165, valueGetter: p => p.data ? scopeLabel(p.data) : '' },
    { field: 'committee_label', headerName: 'Comitato', width: 185 },
    { field: 'source_committee', headerName: 'Codice comitato', width: 170, hide: true },
    { field: 'tournament_place', headerName: 'Località', width: 180 },
    { field: 'race_name', headerName: 'Gara', width: 220 },
    { field: 'race_kind', headerName: 'Tipo', width: 125, valueGetter: p => p.data?.race_kind === 'empty' ? 'Nessuna iscrizione' : p.data?.race_kind === 'double' ? 'Doppio' : 'Singolare' },
    { field: 'entry_count', headerName: 'Iscrizioni', filter: 'agNumberColumnFilter', width: 125 },
    { colId: 'quota', headerName: 'Quota (€) ✎', width: 150, filter: 'agNumberColumnFilter',
      valueGetter: p => p.data?.price_cents == null ? null : p.data.price_cents / 100,
      valueFormatter: p => p.data?.race_kind === 'empty' ? '—' : p.value == null ? 'Da definire' : money(Math.round(p.value * 100)),
      editable: p => p.data?.race_kind !== 'empty' && !this.data.locked(), cellEditor: 'agTextCellEditor', cellDataType: false, cellClass: 'editable-price' },
    { colId: 'origin', headerName: 'Origine prezzo', width: 165, valueGetter: p => p.data ? originLabel(p.data) : '' },
    { field: 'receipt_total_cents', headerName: 'Totale PDF', width: 145, filter: 'agNumberColumnFilter', valueGetter: p => (p.data?.receipt_total_cents ?? 0) / 100, valueFormatter: p => p.data?.has_receipt === false ? '—' : money(Math.round(p.value * 100)) },
    { field: 'calculated_total_cents', headerName: 'Totale quote', width: 155, filter: 'agNumberColumnFilter', valueGetter: p => p.data?.calculated_total_cents == null ? null : p.data.calculated_total_cents / 100, valueFormatter: p => p.value == null ? 'Da completare' : money(Math.round(p.value * 100)) },
    { colId: 'check', headerName: 'Verifica totale PDF', width: 185, valueGetter: p => p.data?.has_receipt === false ? 'Nessuna ricevuta' : p.data?.reconciliation === 'match' ? 'Coincide' : p.data?.reconciliation === 'mismatch' ? 'Non coincide' : 'Da completare', cellClassRules: { 'cell-warning': p => p.data?.reconciliation === 'mismatch' } },
    { field: 'difference_cents', headerName: 'Scostamento', width: 150, filter: 'agNumberColumnFilter', valueGetter: p => p.data?.difference_cents == null ? null : p.data.difference_cents / 100, valueFormatter: p => p.value == null ? '—' : money(Math.round(p.value * 100)) },
    { colId: 'reset', headerName: 'Ripristino', width: 180, sortable: false, filter: false, cellRenderer: GridActionComponent, cellRendererParams: { label: () => 'Ripristina automatico', disabled: (row: Row) => row.race_kind === 'empty' || this.data.locked(), action: (row: Row) => this.data.price(row, '') } },
  ];
  edit(event: CellEditRequestEvent<Row>): void { if (event.data) void this.data.price(event.data, event.newValue); }
}
