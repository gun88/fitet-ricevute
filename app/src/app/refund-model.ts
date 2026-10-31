import type { Row } from './data.service';

export type Allocation = 'half' | 'first' | 'second';
export interface AthleteLine { entry: Row; cents: number | null }
export interface AthleteSummary { key: string; name: string; card: string; quotes: number; total_cents: number | null; pending_cents: number | null; paid_cents: number; lines: AthleteLine[] }

function parts(cents: number | null, double: boolean, allocation: Allocation): [number | null, number | null] {
  if (!double) return [cents, 0];
  if (cents === null) return [null, null];
  if (allocation === 'first') return [cents, 0];
  if (allocation === 'second') return [0, cents];
  return [Math.ceil(cents / 2), Math.floor(cents / 2)];
}

export function athleteSummary(rows: Row[], allocation: Allocation): AthleteSummary[] {
  const map = new Map<string, AthleteSummary>();
  for (const row of rows) {
    // Paid figures use the recorded amount, never a subsequently changed tariff.
    const cents = row.reimbursed ? row.paid_cents : row.price_cents;
    const shares = parts(cents, !!row.athlete2_name, allocation);
    const people = [{ name: row.athlete1_name, card: row.athlete1_card }, { name: row.athlete2_name, card: row.athlete2_card }];
    people.forEach((person, index) => {
      if (!person.name || (row.athlete2_name && allocation === 'first' && index === 1) || (row.athlete2_name && allocation === 'second' && index === 0)) return;
      const key = person.card ? 'card:' + person.card : 'name:' + person.name.trim().toLocaleUpperCase('it-IT');
      const item = map.get(key) ?? { key, name: person.name, card: person.card || '—', quotes: 0, total_cents: 0, pending_cents: 0, paid_cents: 0, lines: [] };
      const share = shares[index];
      item.quotes++;
      item.total_cents = item.total_cents === null || share === null ? null : item.total_cents + share;
      if (row.reimbursed) item.paid_cents += share ?? 0;
      else item.pending_cents = item.pending_cents === null || share === null ? null : item.pending_cents + share;
      item.lines.push({ entry: row, cents: share });
      map.set(key, item);
    });
  }
  return [...map.values()].sort((a, b) => a.name.localeCompare(b.name, 'it'));
}

export function pendingTotal(rows: Row[]): number | null {
  const pending = rows.filter(row => !row.reimbursed);
  return pending.some(row => row.price_cents === null) ? null : pending.reduce((sum, row) => sum + row.price_cents!, 0);
}
