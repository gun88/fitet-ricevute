import { GridSizingDirective } from './grid-sizing.directive';
import { GridPresentation } from './grid-presentation.service';
import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { AgGridAngular } from 'ag-grid-angular';
import { ColDef } from 'ag-grid-community';
import { AuthService, errorMessage } from './auth.service';
import { DataService, Row, money } from './data.service';
import { TableView } from './table-preferences';
import { defaultColDef, getRowId, gridTheme } from './grid';
import { entryColumns } from './entries.component';
import { Allocation, AthleteSummary, athleteSummary, pendingTotal } from './refund-model';

@Component({ selector: 'fr-refunds', imports: [GridSizingDirective,FormsModule, RouterLink, AgGridAngular], template: `
  <div class="page-heading"><div><span class="eyebrow">03 · RIMBORSI</span><h1>Emissione rimborso</h1><p class="dashboard-subtitle">Controlla le quote selezionate e prepara le ricevute.</p></div><a class="secondary-button" routerLink="/iscrizioni">Torna alle iscrizioni</a></div>
  @if (loading()) { <p role="status">Caricamento del recap…</p> }
  @if (!loading() && !rows().length) { <div class="empty-state">Seleziona le quote nella pagina Iscrizioni per creare un rimborso.</div> }
  @if (rows().length) {
    <div class="recap-totals"><div><span>Quote selezionate</span><strong>{{ rows().length }}</strong></div><div><span>Quote già rimborsate</span><strong>{{ paidCount() }}</strong></div><div><span>Da rimborsare</span><strong>{{ money(total()) }}</strong></div></div>
    <section class="refund-settings" aria-label="Impostazioni della ricevuta">
      <label>Società emittente<input name="issuer" [(ngModel)]="issuer" maxlength="180" required></label>
      <label>Data ricevuta<input type="date" name="issue-date" [(ngModel)]="issueDate" required></label>
      <label>Ripartizione quote doppio<select name="allocation" aria-label="Ripartizione quote doppio" [ngModel]="allocation()" (ngModelChange)="allocation.set($event)"><option value="half">Metà per atleta</option><option value="first">Tutta al primo atleta</option><option value="second">Tutta al secondo atleta</option></select></label>
      <label>Formato PDF<select name="format" aria-label="Formato PDF" [(ngModel)]="format"><option value="total">Ricevuta cumulativa</option><option value="athletes">Ricevute separate per atleta (ZIP)</option><option value="both">Cumulativa e separate (ZIP)</option></select></label>
      <label class="wide-field">Note facoltative<input name="notes" [(ngModel)]="notes" maxlength="500" placeholder="Es. rimborso quote tornei ottobre"></label>
    </section>
    <h2 class="recap-heading">Recap cumulativo</h2>
    <ag-grid-angular id="refund-grid" class="recap-grid" [theme]="presentation.theme()" [domLayout]="presentation.layout()" [class.auto-height]="presentation.autoHeight()" [rowData]="rows()" [columnDefs]="columns" [defaultColDef]="defaults" [getRowId]="rowId" [frGridSizing]="recapView" [initialState]="recapView.initialState" (stateUpdated)="recapView.updated($event)" [pagination]="true" [paginationPageSize]="100" [paginationPageSizeSelector]="[25,50,100]" />
    <div class="bulk-actions recap-actions"><button class="secondary-button" (click)="markAll(true)" [disabled]="data.locked() || loading() || !pendingCount()">Segna tutte rimborsate</button><button class="text-button" (click)="markAll(false)" [disabled]="data.locked() || loading() || !paidCount()">Segna tutte da rimborsare</button></div>
    <h2 class="recap-heading">Recap per atleta</h2>
    <ag-grid-angular id="athlete-grid" class="athlete-grid" [theme]="presentation.theme()" [domLayout]="presentation.layout()" [class.auto-height]="presentation.autoHeight()" [rowData]="athletes()" [frGridSizing]="athleteView" [initialState]="athleteView.initialState" (stateUpdated)="athleteView.updated($event)" [columnDefs]="athleteColumns" [defaultColDef]="summaryDefaults" [pagination]="true" [paginationPageSize]="100" [paginationPageSizeSelector]="[25,50,100]" />
    <p class="grid-help">Le quote già rimborsate sono escluse dai nuovi PDF. La generazione non cambia lo stato del rimborso. Nel doppio, un eventuale centesimo dispari è assegnato al primo atleta; la marcatura riguarda l’intera coppia.</p>
    @if (total() === null) { <p class="error-box" role="alert">Mancano delle quote: definisci prima i prezzi nella pagina Prezzi tornei.</p> }
    <div class="issue-footer"><span>{{ pendingCount() }} quote da inserire · {{ money(total()) }}</span><button class="sync-button" (click)="generate()" [disabled]="data.locked() || loading() || total() === null || !pendingCount() || !issuer.trim() || !issueDate">{{ data.busy() ? 'Preparazione…' : 'Genera PDF' }}</button></div>
  }
` })
export class RefundsComponent implements OnInit {
 readonly presentation=inject(GridPresentation);
  readonly data = inject(DataService); private readonly auth = inject(AuthService);
  readonly theme = gridTheme; readonly defaults = defaultColDef; readonly rowId = getRowId;
  readonly summaryDefaults = { sortable: true, filter: true, floatingFilter: false, resizable: true, minWidth:64 };
  readonly recapView = new TableView('refund-recap'); readonly athleteView = new TableView('refund-athletes');
  readonly rows = signal<Row[]>([]); readonly loading = signal(true); readonly allocation = signal<Allocation>('half');
  readonly athletes = computed(() => athleteSummary(this.rows(), this.allocation()));
  readonly total = computed(() => pendingTotal(this.rows()));
  readonly paidCount = computed(() => this.rows().filter(row => row.reimbursed).length);
  readonly pendingCount = computed(() => this.rows().length - this.paidCount());
  readonly money = money;
  readonly columns = entryColumns(this.data, row => this.markOne(row));
  readonly athleteColumns: ColDef<AthleteSummary>[] = [
    { field: 'name', headerName: 'Atleta',  minWidth:64 },
    { field: 'card', headerName: 'Tessera' },
    { field: 'quotes', headerName: 'Partecipazioni', filter: 'agNumberColumnFilter' },
    { field: 'total_cents', headerName: 'Totale quote',  valueGetter: p => p.data?.total_cents == null ? null : p.data.total_cents / 100, valueFormatter: p => p.value == null ? 'Da definire' : money(Math.round(p.value * 100)), filter: 'agNumberColumnFilter' },
    { field: 'paid_cents', headerName: 'Già rimborsato',  valueGetter: p => (p.data?.paid_cents ?? 0) / 100, valueFormatter: p => money(Math.round(p.value * 100)), filter: 'agNumberColumnFilter' },
    { field: 'pending_cents', headerName: 'Da rimborsare',  valueGetter: p => p.data?.pending_cents == null ? null : p.data.pending_cents / 100, valueFormatter: p => p.value == null ? 'Da definire' : money(Math.round(p.value * 100)), filter: 'agNumberColumnFilter' },
  ];
  issuer = ''; issueDate = new Date().toLocaleDateString('en-CA'); notes = ''; format: 'total' | 'athletes' | 'both' = 'total';
  async ngOnInit(): Promise<void> {
    const ids = this.data.restoreSelection();
    try {
      this.issuer = (await this.auth.publicConfig()).society_name;
      if (ids.length) this.rows.set(await this.data.preview(ids));
    } catch (error) { this.data.error.set(errorMessage(error)); }
    finally { this.loading.set(false); }
  }
  private async update(ids: number[], state: boolean): Promise<void> {
    await this.data.perform(async () => {
      await this.data.refundState(ids, state);
      this.rows.set(await this.data.preview(this.rows().map(row => row.id)));
    });
  }
  markOne(row: Row): void { void this.update([row.id], !row.reimbursed); }
  markAll(state: boolean): void { void this.update(this.rows().map(row => row.id), state); }
  async generate(): Promise<void> {
    await this.data.perform(async () => {
      const current = await this.data.preview(this.rows().map(row => row.id));
      const snapshot = (rows: Row[]) => JSON.stringify(rows.map(row => [row.id,row.price_cents,row.reimbursed,row.paid_cents,row.athlete1_name,row.athlete2_name,row.race_name,row.tournament_name]));
      const changed = snapshot(current) !== snapshot(this.rows());
      this.rows.set(current);
      if (changed) throw new Error('Quote o stato dei rimborsi aggiornati da un’altra operazione. Controlla il nuovo recap e premi nuovamente Genera PDF.');
      if (pendingTotal(current) === null || !current.some(row => !row.reimbursed)) throw new Error('Non ci sono quote complete da emettere.');
      const { downloadRefunds } = await import('./refund-pdf');
      await downloadRefunds(current.filter(row => !row.reimbursed), { issuer: this.issuer.trim(), date: this.issueDate, notes: this.notes.trim(), format: this.format, allocation: this.allocation() });
      this.data.notice.set('PDF preparati. Segna le quote come rimborsate quando il rimborso è avvenuto.');
    });
  }
}
