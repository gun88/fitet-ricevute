import { Component, computed, inject } from '@angular/core';
import { Router } from '@angular/router';
import { AgGridAngular } from 'ag-grid-angular';
import { ColDef, GridApi, RowDataUpdatedEvent, RowSelectionOptions, SelectionChangedEvent, SelectionColumnDef } from 'ag-grid-community';
import { DataService, Row, dateLabel, money, names } from './data.service';
import { AthleteLinksComponent } from './player-links.component';
import { TableView } from './table-preferences';
import { GridActionComponent, defaultColDef, getRowId, gridTheme } from './grid';

export function entryColumns(data: DataService, action?: (row: Row) => void): ColDef<Row>[] {
  return [
    { field: 'tournament_date', headerName: 'Data', width: 130, valueFormatter: p => dateLabel(p.value), sort: 'desc' },
    { field: 'tournament_name', headerName: 'Torneo', width: 260 },
    { field: 'race_name', headerName: 'Gara', width: 220 },
    { colId: 'athletes', headerName: 'Atleta / Coppia', width: 265, valueGetter: p => p.data ? names(p.data) : '', cellRenderer: AthleteLinksComponent },
    { colId: 'cards', headerName: 'Tessera', width: 160, valueGetter: p => [p.data?.athlete1_card, p.data?.athlete2_card].filter(Boolean).join(' / ') },
    { field: 'price_cents', headerName: 'Quota (€)', width: 140, filter: 'agNumberColumnFilter', valueGetter: p => p.data?.price_cents == null ? null : p.data.price_cents / 100, valueFormatter: p => p.value == null ? 'Da definire' : money(Math.round(p.value * 100)) },
    { colId: 'status', headerName: 'Stato rimborso', width: 165, valueGetter: p => p.data?.reimbursed ? 'Rimborsata' : 'Da rimborsare' },
    { field: 'paid_cents', headerName: 'Importo rimborsato', width: 185, filter: 'agNumberColumnFilter', valueGetter: p => p.data?.paid_cents == null ? null : p.data.paid_cents / 100, valueFormatter: p => p.value == null ? '—' : money(Math.round(p.value * 100)) },
    { colId: 'refund', headerName: 'Aggiorna stato', width: 200, filter: false, sortable: false, cellRenderer: GridActionComponent, cellRendererParams: { label: (row: Row) => row.reimbursed ? 'Segna da rimborsare' : 'Segna rimborsata', disabled: () => data.locked(), action: action ?? ((row: Row) => data.mark([row], !row.reimbursed)) } },
  ];
}

@Component({ selector: 'fr-entries', imports: [AgGridAngular], template: `
  <div class="page-heading"><div><span class="eyebrow">02 · ISCRIZIONI</span><h1>Iscrizioni e rimborsi</h1><p class="dashboard-subtitle">Seleziona le quote da inserire nel recap di rimborso.</p></div><button class="sync-button" (click)="emit()" [disabled]="!selectedRows().length || selectedRows().length > 1000 || data.locked()">Emetti rimborso ({{ selectedRows().length }})</button></div>
  <div class="grid-toolbar"><label>Cerca in tutte le colonne<input type="search" aria-label="Cerca iscrizioni" [value]="view.search()" maxlength="1000" (input)="view.setSearch($any($event.target).value)" placeholder="Atleta, torneo, gara…"></label><div class="bulk-actions"><button class="text-button" (click)="view.reset(api)">Azzera filtri e ordine</button><button class="secondary-button" (click)="data.mark(selectedRows(), true)" [disabled]="!selectedRows().length || selectedRows().length > 1000 || data.locked()">Segna selezione rimborsata</button><button class="text-button" (click)="clearSelection()" [disabled]="!selectedRows().length">Deseleziona tutto</button></div></div>
  <ag-grid-angular id="entries-grid" class="main-grid" [theme]="theme" [rowData]="data.entries()" [columnDefs]="columns" [defaultColDef]="defaults" [getRowId]="rowId" [rowSelection]="selection" [selectionColumnDef]="selectionColumn" [pagination]="true" [paginationPageSize]="100" [paginationPageSizeSelector]="[25,50,100]" [quickFilterText]="view.search()" [initialState]="view.initialState" (stateUpdated)="view.updated($event)" (gridReady)="api = $event.api" (selectionChanged)="changed($event)" (rowDataUpdated)="restore($event)" [overlayNoRowsTemplate]="empty" />
  <p class="grid-help">{{ selectedRows().length }} quote selezionate, anche su altre pagine o nascoste dai filtri. Massimo 1000 per emissione. Le quote già rimborsate restano visibili nel recap e vengono escluse dai nuovi PDF. Nei doppi lo stato riguarda l’intera quota della coppia.</p>
` })
export class EntriesComponent {
  readonly data = inject(DataService); private readonly router = inject(Router);
  readonly theme = gridTheme; readonly defaults = defaultColDef; readonly rowId = getRowId;
  readonly columns = entryColumns(this.data);
  readonly selectedRows = computed(() => this.data.entries().filter(row => this.data.selected().includes(row.id)));
  readonly selection: RowSelectionOptions<Row> = { mode: 'multiRow', checkboxes: true, headerCheckbox: true, selectAll: 'filtered', enableClickSelection: false };
  readonly selectionColumn: SelectionColumnDef = { pinned:'left',width:48,minWidth:48,maxWidth:48,resizable:false };
  readonly empty = '<span>Nessuna iscrizione disponibile. Modifica i filtri o sincronizza le ricevute.</span>';
  readonly view = new TableView('entries'); api?: GridApi<Row>; private restoring = false;
  changed(event: SelectionChangedEvent<Row>): void {
    if (!this.restoring && event.source !== 'rowDataChanged') this.data.setSelection(event.api.getSelectedRows().map(row => row.id));
  }
  restore(event: RowDataUpdatedEvent<Row>): void {
    this.restoring = true;
    const ids = new Set(this.data.selected());
    event.api.forEachNode(node => node.setSelected(ids.has(node.data!.id)));
    this.restoring = false;
  }
  clearSelection(): void { this.api?.deselectAll(); this.data.setSelection([]); }
  emit(): void { this.data.setSelection(this.selectedRows().map(row => row.id)); void this.router.navigateByUrl('/rimborsi'); }
}
