import { Component } from '@angular/core';
import { ICellRendererAngularComp } from 'ag-grid-angular';
import { AllCommunityModule, ColDef, ICellRendererParams, ModuleRegistry, provideGlobalGridOptions, themeQuartz } from 'ag-grid-community';
import { Row } from './data.service';

ModuleRegistry.registerModules([AllCommunityModule]);
provideGlobalGridOptions({ enableCellTextSelection: true, ensureDomOrder: true, localeText: {
  page: 'Pagina', more: 'altro', to: 'a', of: 'di', next: 'Successiva', last: 'Ultima', first: 'Prima', previous: 'Precedente', pageSizeSelectorLabel: 'Righe per pagina:',
  loadingOoo: 'Caricamento…', noRowsToShow: 'Nessuna riga da mostrare', noMatchingRows: 'Nessuna riga corrispondente', searchOoo: 'Cerca…', blanks: '(Vuote)',
  filterOoo: 'Filtra…', equals: 'Uguale a', notEqual: 'Diverso da', empty: 'Scegli un filtro',
  lessThan: 'Minore di', greaterThan: 'Maggiore di', lessThanOrEqual: 'Minore o uguale', greaterThanOrEqual: 'Maggiore o uguale', inRange: 'Tra', inRangeStart: 'Da', inRangeEnd: 'A',
  contains: 'Contiene', notContains: 'Non contiene', startsWith: 'Inizia con', endsWith: 'Termina con', blank: 'Vuoto', notBlank: 'Non vuoto',
  andCondition: 'E', orCondition: 'O', applyFilter: 'Applica', resetFilter: 'Ripristina', clearFilter: 'Cancella', cancelFilter: 'Annulla',
  selectAll: 'Seleziona tutto', selectAllSearchResults: 'Seleziona risultati', ariaRowSelect: 'Seleziona riga', ariaRowDeselect: 'Deseleziona riga',
  ariaFilterInput: 'Filtro', ariaSearch: 'Cerca', ariaFilterColumn: 'Filtra colonna', ariaSortableColumn: 'Colonna ordinabile',
} });
export const gridTheme = themeQuartz.withParams({ accentColor: '#24563d', backgroundColor: '#ffffff', headerBackgroundColor: '#f1f5ee', headerTextColor: '#365440', foregroundColor: '#263b2e', borderColor: '#dfe7dc', fontFamily: 'Arial, sans-serif', fontSize: 12, spacing: 8, wrapperBorderRadius: 10 });
export const defaultColDef: ColDef<Row> = { sortable: true, filter: true, floatingFilter: true, resizable: true, minWidth: 120 };
export const getRowId = (params: { data: Row }) => String(params.data.id);

interface ActionParams extends ICellRendererParams<Row> { label: (row: Row) => string; action: (row: Row) => void; disabled: (row: Row) => boolean }
@Component({ selector: 'fr-grid-action', template: '<button class="grid-action" type="button" [disabled]="params.disabled(params.data!)" (click)="run($event)">{{ params.label(params.data!) }}</button>' })
export class GridActionComponent implements ICellRendererAngularComp {
  params!: ActionParams;
  agInit(params: ActionParams): void { this.params = params; }
  refresh(params: ActionParams): boolean { this.params = params; return true; }
  run(event: Event): void { event.stopPropagation(); this.params.action(this.params.data!); }
}
