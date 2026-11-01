import { Injectable, computed, inject, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { AuthService, errorMessage } from './auth.service';
import { apiUrl } from './config';

export interface Row {
  refund_notes?: string;
  has_receipt?: boolean;
  id: number; race_id: number; society_code: string; tournament_key: string;
  source_committee: string; committee_name: string; committee_label: string; tournament_scope: 'national' | 'territorial' | 'unknown';
  tournament_name: string; tournament_date: string; tournament_place: string; tournament_ref: string;
  race_name: string; race_kind: string; entry_count: number; receipt_entries: number;
  receipt_total_cents: number; price_cents: number | null; automatic_cents: number | null;
  manual_cents: number | null; price_origin: string; auto_basis: string;
  calculated_total_cents: number | null; difference_cents: number | null; reconciliation: string;
  athlete1_name: string; athlete1_card: string | null; athlete2_name: string | null; athlete2_card: string | null;
  athlete1_category: string | null; athlete1_sector: string | null;
  reimbursed: boolean; paid_cents: number | null; refund_updated_at: string | null;
}
interface Listing { items: Row[]; page: number; total: number; per_page: number }
export interface Job { id: string; total: number; processed: number; imported: number; done: boolean; errors: { receipt: number; message: string }[] }

export { money, dateLabel, names, originLabel } from './format';

function normalise(row: Row): Row {
  const result = { ...row };
  for (const key of ['id', 'race_id', 'entry_count', 'receipt_entries', 'receipt_total_cents', 'price_cents', 'automatic_cents', 'manual_cents', 'calculated_total_cents', 'difference_cents', 'paid_cents'] as const) {
    if (result[key] != null) (result as unknown as Record<string, unknown>)[key] = Number(result[key]);
  }
  result.reimbursed = Number(row.reimbursed) === 1;
  return result;
}

@Injectable({ providedIn: 'root' })
export class DataService {
  private readonly http = inject(HttpClient);
  private readonly auth = inject(AuthService);
  readonly races = signal<Row[]>([]);
  readonly entries = signal<Row[]>([]);
  readonly selected = signal<number[]>([]);
  readonly error = signal('');
  readonly notice = signal('');
  readonly loading = signal(false);
  readonly busy = signal(false);
  readonly syncing = signal(false);
  readonly job = signal<Job | null>(null);
  readonly syncProgress = computed(() => {
    const job = this.job();
    if (!job) return 0;
    if (job.total <= 0) return job.done ? 100 : 0;
    return Math.max(0, Math.min(100, Math.floor(job.processed / job.total * 100)));
  });
  readonly lastSyncAt = signal<string | null>(null);
  readonly lastSyncLabel = computed(() => {
    const value = this.lastSyncAt();
    return value ? new Intl.DateTimeFormat('it-IT', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value)) : 'Mai effettuata';
  });
  readonly locked = computed(() => this.loading() || this.busy() || this.syncing());

  private draftKey(): string { return 'fr-refund-selection-' + this.auth.user()?.id; }
  setSelection(ids: number[]): void {
    this.selected.set([...new Set(ids)]);
    try { sessionStorage.setItem(this.draftKey(), JSON.stringify(this.selected())); } catch { /* storage may be disabled */ }
  }
  restoreSelection(): number[] {
    try {
      const ids: unknown = JSON.parse(sessionStorage.getItem(this.draftKey()) || '[]');
      if (Array.isArray(ids) && ids.length <= 1000 && ids.every(id => Number.isSafeInteger(id) && id > 0)) this.selected.set(ids);
    } catch { this.selected.set([]); }
    return this.selected();
  }
  clear(userId: number | undefined): void {
    try { sessionStorage.removeItem('fr-refund-selection-' + userId); } catch { /* storage may be disabled */ }
    this.selected.set([]); this.races.set([]); this.entries.set([]); this.lastSyncAt.set(null); this.job.set(null);
  }

  private async all(kind: string): Promise<Row[]> {
    const rows: Row[] = [];
    let page = 1;
    while (true) {
      const data = await firstValueFrom(this.http.get<Listing>(apiUrl(kind), { params: { page } }));
      rows.push(...data.items.map(normalise));
      if (rows.length >= data.total) return rows;
      if (!data.items.length) throw new Error('Dataset cambiato durante il caricamento. Aggiorna i dati.');
      page++;
    }
  }
  async refresh(): Promise<void> {
    this.loading.set(true);
    try {
      // Load every API page: AG Grid filtering and sorting cover the complete dataset.
      const [races, entries, sync] = await Promise.all([this.all('gare'), this.all('iscrizioni'), firstValueFrom(this.http.get<{last_sync_at: string | null}>(apiUrl('sincronizzazione/stato')))]);
      this.lastSyncAt.set(sync.last_sync_at);
      this.races.set(races); this.entries.set(entries);
      const active = new Set(entries.map(row => row.id));
      this.setSelection(this.selected().filter(id => active.has(id)));
    } finally { this.loading.set(false); }
  }
  async perform(action: () => Promise<void>): Promise<void> {
    if (this.locked()) return;
    this.busy.set(true); this.error.set(''); this.notice.set('');
    try { await action(); }
    catch (error) { this.error.set(error instanceof Error && !(error as {status?: number}).status ? error.message : errorMessage(error)); }
    finally { this.busy.set(false); }
  }
  async price(row: Row, raw: unknown): Promise<void> {
    await this.perform(async () => {
      const value = String(raw ?? '').trim().replace(',', '.');
      let cents: number | null = null;
      if (value !== '') {
        if (!/^\d{1,7}(?:\.\d{1,2})?$/.test(value)) throw new Error('Inserisci una quota positiva o zero, con al massimo due decimali. Lascia vuoto per ripristinare il calcolo automatico.');
        const [euros, decimals = ''] = value.split('.');
        cents = Number(euros) * 100 + Number(decimals.padEnd(2, '0'));
        if (cents > 100000000) throw new Error('Quota superiore al limite consentito.');
      }
      await firstValueFrom(this.http.patch(apiUrl('prezzi/' + row.id), { manual_cents: cents }));
      await this.refresh();
      this.notice.set(cents === null ? 'Ripristinato il prezzo automatico.' : 'Prezzo salvato. Aggiornate quote iscrizioni e verifica del totale.');
    });
  }
  async preview(ids: number[]): Promise<Row[]> {
    const data = await firstValueFrom(this.http.post<{items: Row[]}>(apiUrl('rimborsi/anteprima'), { ids }));
    return data.items.map(normalise);
  }
  async refundState(ids: number[], reimbursed: boolean): Promise<Row[]> {
    const data = await firstValueFrom(this.http.patch<{items: Row[]}>(apiUrl('rimborsi/stato'), { ids, reimbursed }));
    await this.refresh();
    this.notice.set(reimbursed ? 'Quote segnate come rimborsate.' : 'Quote riaperte come da rimborsare.');
    return data.items.map(normalise);
  }
  async mark(rows: Row[], reimbursed: boolean): Promise<void> {
    await this.perform(async () => { await this.refundState(rows.map(row => row.id), reimbursed); });
  }
  async synchronize(): Promise<void> {
    if (this.locked()) return;
    this.syncing.set(true); this.error.set(''); this.notice.set(''); this.job.set(null);
    try {
      this.job.set(await firstValueFrom(this.http.post<Job>(apiUrl('sincronizzazione'), {})));
      while (!this.job()!.done) this.job.set(await firstValueFrom(this.http.post<Job>(apiUrl('sincronizzazione/' + this.job()!.id + '/passo'), {})));
      await this.refresh();
      this.notice.set(this.job()!.imported + ' ricevute sincronizzate su ' + this.job()!.total + '.');
    } catch (error) { this.error.set(errorMessage(error)); }
    finally { this.syncing.set(false); }
  }
}
